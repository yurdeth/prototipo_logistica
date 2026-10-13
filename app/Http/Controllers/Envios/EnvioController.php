<?php

namespace App\Http\Controllers\Envios;

use App\Enums\EstadoEnvioEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Envios\CambiarEstadoEnvioRequest;
use App\Http\Requests\Envios\StoreEnvioRequest;
use App\Http\Requests\Envios\UpdateEnvioRequest;
use App\Models\Envio;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnvioController extends Controller
{
    /** Fuente de verdad de los estados: el enum que espeja el catálogo. */
    public const ESTADOS = EstadoEnvioEnum::VALUES;

    /** Fragmentos SQL reutilizados en los indicadores (e = envio, s = estado, fe.fecha = fecha real de entrega). */
    public const ABIERTO = "s.nombre_estado NOT IN ('ENTREGADO','DEVUELTO','FALLIDO')";

    public const INCIDENCIA = "(s.nombre_estado IN ('FALLIDO','DEVUELTO')
        OR (s.nombre_estado NOT IN ('ENTREGADO','DEVUELTO','FALLIDO') AND e.fecha_comprometida < now())
        OR (s.nombre_estado = 'ENTREGADO' AND fe.fecha > e.fecha_comprometida))";

    /* ===================== LISTAR ===================== */

    public function index(Request $request): Response
    {
        $f = $this->filtros($request);

        $envios = $this->aplicar($this->base(), $f)
            ->select([
                'e.id_envio',
                'e.codigo_guia',
                'r.nombre as remitente',
                'd.nombre as destinatario',
                't.nombre_territorio as territorio_destino',
                's.nombre_estado as estado',
                'e.prioridad',
                'e.peso_kg',
                'e.fecha_registro',
                'e.fecha_comprometida',
                'fe.fecha as fecha_entrega',
            ])
            ->orderByDesc('e.fecha_registro')
            ->orderByDesc('e.id_envio')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($e) => [
                'id_envio' => $e->id_envio,
                'codigo_guia' => $e->codigo_guia,
                'remitente' => $e->remitente,
                'destinatario' => $e->destinatario,
                'territorio_destino' => $e->territorio_destino,
                'estado' => $e->estado,
                'prioridad' => $e->prioridad,
                'peso_kg' => (float) $e->peso_kg,
                'fecha_registro' => $this->iso($e->fecha_registro),
                'fecha_comprometida' => $this->iso($e->fecha_comprometida),
                'fecha_entrega' => $this->iso($e->fecha_entrega),
            ]);

        return Inertia::render('shipments/EnviosIndex', [
            'envios' => $envios,
            'filtros' => $f,
            'catalogos' => [
                'estados' => DB::table('envios.estados_envios')->orderBy('id_estado')->pluck('nombre_estado'),
                'sucursales' => $this->sucursales(),
                'territorios' => $this->territorios(),
            ],
            'resumen' => $this->resumen($f),
            'permisos' => $this->permisos(),
        ]);
    }

    /** CSV con todos los envíos que cumplen los filtros (mismos parámetros que el índice). */
    public function exportar(Request $request): StreamedResponse
    {
        $f = $this->filtros($request);

        $consulta = $this->aplicar($this->base(), $f)
            ->select([
                'e.codigo_guia',
                'r.nombre as remitente',
                'd.nombre as destinatario',
                'su.nombre_sucursal as sucursal_origen',
                't.nombre_territorio as territorio_destino',
                's.nombre_estado as estado',
                'e.prioridad',
                'e.peso_kg',
                'e.fecha_registro',
                'e.fecha_comprometida',
                'fe.fecha as fecha_entrega',
            ])
            ->orderByDesc('e.fecha_registro')
            ->orderByDesc('e.id_envio');

        // Evita que Excel interprete como fórmula un texto que empieza con = + - @
        $seguro = fn ($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;

        return response()->streamDownload(function () use ($consulta, $seguro): void {
            $salida = fopen('php://output', 'w');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos

            fputcsv($salida, [
                'Guía', 'Remitente', 'Destinatario', 'Sucursal de origen', 'Destino',
                'Estado', 'Prioridad', 'Peso (kg)', 'Registrado', 'Compromiso', 'Entregado',
            ]);

            foreach ($consulta->cursor() as $e) {
                /** @var object{id_envio: int, codigo_guia: string, remitente: string, destinatario: string, sucursal_origen: string, territorio_destino: string, estado: string, prioridad: string, peso_kg: string, fecha_registro: string, fecha_comprometida: string, fecha_entrega: string|null} $e */
                fputcsv($salida, [
                    $e->codigo_guia,
                    $seguro($e->remitente),
                    $seguro($e->destinatario),
                    $seguro($e->sucursal_origen),
                    $seguro($e->territorio_destino),
                    $e->estado,
                    $e->prioridad,
                    $e->peso_kg,
                    $this->iso($e->fecha_registro),
                    $this->iso($e->fecha_comprometida),
                    $this->iso($e->fecha_entrega),
                ]);
            }

            fclose($salida);
        }, 'envios-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ===================== CREAR ===================== */

    public function create(): Response
    {
        return Inertia::render('shipments/EnviosCreate', $this->catalogosFormulario());
    }

    public function store(StoreEnvioRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // El modelo Envio genera la guía definitiva y el evento inicial dentro de la misma
        // transacción (ver Envio::booted y Envio::save): si algo falla, no queda nada a medias.
        $envio = new Envio([
            'cliente_remitente_id' => $data['cliente_remitente_id'],
            'cliente_destinatario_id' => $data['cliente_destinatario_id'],
            'sucursal_origen_id' => $data['sucursal_origen_id'],
            'direccion_destino' => $data['direccion_destino'],
            'territorio_destino_id' => $data['territorio_destino_id'],
            'peso_kg' => $data['peso_kg'],
            'volumen_m3' => $data['volumen_m3'] ?? null,
            'estado_envio_id' => $this->estadoId(EstadoEnvioEnum::REGISTRADO->value),
            'prioridad' => $data['prioridad'],
            'fecha_comprometida' => Carbon::parse($data['fecha_comprometida']),
        ]);
        $envio->latitudEvento = $data['latitud'] ?? null;
        $envio->longitudEvento = $data['longitud'] ?? null;
        $envio->save();

        $id = $envio->getKey();

        return to_route('envios.show', $id)->with('success', 'Envío registrado correctamente.');
    }

    /* ===================== VER ===================== */

    public function show(int $envio): Response
    {
        $detalle = $this->detalle($envio) ?? abort(404);

        $eventos = DB::table('envios.envio_eventos as ev')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'ev.estado_envio_id')
            ->leftJoin('users as u', 'u.id', '=', 'ev.usuario_id')
            ->where('ev.envio_id', $envio)
            ->orderBy('ev.ocurrido_en')
            ->orderBy('ev.id_evento')
            ->selectRaw('ev.id_evento, s.nombre_estado as estado, ev.ocurrido_en, ev.nota,
                ST_Y(ev.ubicacion::geometry) as latitud, ST_X(ev.ubicacion::geometry) as longitud,
                u.name as responsable')
            ->get()
            ->map(fn ($ev) => [
                'id_evento' => $ev->id_evento,
                'estado' => $ev->estado,
                'ocurrido_en' => $this->iso($ev->ocurrido_en),
                'nota' => $ev->nota,
                'responsable' => $ev->responsable,
                'latitud' => $ev->latitud !== null ? (float) $ev->latitud : null,
                'longitud' => $ev->longitud !== null ? (float) $ev->longitud : null,
            ]);

        return Inertia::render('shipments/EnviosShow', [
            'envio' => [
                'id_envio' => $detalle['id_envio'],
                'codigo_guia' => $detalle['codigo_guia'],
                'remitente' => $detalle['remitente'],
                'destinatario' => $detalle['destinatario'],
                'sucursal_origen' => $detalle['sucursal_origen'],
                'territorio_destino' => $detalle['territorio_destino'],
                'direccion_destino' => $detalle['direccion_destino'],
                'peso_kg' => (float) $detalle['peso_kg'],
                'volumen_m3' => $detalle['volumen_m3'] !== null ? (float) $detalle['volumen_m3'] : null,
                'estado' => $detalle['estado'],
                'prioridad' => $detalle['prioridad'],
                'fecha_registro' => $this->iso($detalle['fecha_registro']),
                'fecha_comprometida' => $this->iso($detalle['fecha_comprometida']),
                'fecha_entrega' => $this->iso($detalle['fecha_entrega']),
            ],
            'eventos' => $eventos,
            'estados' => self::ESTADOS,
            'permisos' => $this->permisos(),
        ]);
    }

    /* ===================== EDITAR ===================== */

    public function edit(int $envio): Response
    {
        $e = DB::table('envios.envios as e')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->where('e.id_envio', $envio)
            ->first([
                'e.id_envio', 'e.codigo_guia', 'e.cliente_remitente_id', 'e.cliente_destinatario_id',
                'e.sucursal_origen_id', 'e.territorio_destino_id', 'e.direccion_destino',
                'e.peso_kg', 'e.volumen_m3', 'e.prioridad', 'e.fecha_comprometida',
                's.nombre_estado as estado',
            ]) ?? abort(404);

        return Inertia::render('shipments/EnviosEdit', [
            ...$this->catalogosFormulario(),
            'estados' => self::ESTADOS,
            'envio' => [
                'id_envio' => $e->id_envio,
                'codigo_guia' => $e->codigo_guia,
                'cliente_remitente_id' => $e->cliente_remitente_id,
                'cliente_destinatario_id' => $e->cliente_destinatario_id,
                'sucursal_origen_id' => $e->sucursal_origen_id,
                'territorio_destino_id' => $e->territorio_destino_id,
                'direccion_destino' => $e->direccion_destino,
                'peso_kg' => (float) $e->peso_kg,
                'volumen_m3' => $e->volumen_m3 !== null ? (float) $e->volumen_m3 : null,
                'prioridad' => $e->prioridad,
                'estado' => $e->estado,
                // formato que entiende <input type="datetime-local">
                'fecha_comprometida' => Carbon::parse($e->fecha_comprometida)->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    public function update(UpdateEnvioRequest $request, int $envio): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $envio): void {
            // Se bloquea la fila para evitar que dos personas cambien el estado a la vez
            $modelo = Envio::query()->lockForUpdate()->findOrFail($envio);

            $modelo->fill([
                'cliente_remitente_id' => $data['cliente_remitente_id'],
                'cliente_destinatario_id' => $data['cliente_destinatario_id'],
                'sucursal_origen_id' => $data['sucursal_origen_id'],
                'direccion_destino' => $data['direccion_destino'],
                'territorio_destino_id' => $data['territorio_destino_id'],
                'peso_kg' => $data['peso_kg'],
                'volumen_m3' => $data['volumen_m3'] ?? null,
                'estado_envio_id' => $this->estadoId($data['estado']),
                'prioridad' => $data['prioridad'],
                'fecha_comprometida' => Carbon::parse($data['fecha_comprometida']),
            ]);

            // Si el estado cambió, el modelo registra el evento con esta nota (Envio::booted)
            $modelo->notaEvento = $data['nota'] ?? null;
            $modelo->save();
        });

        return to_route('envios.show', $envio)->with('success', 'Envío actualizado correctamente.');
    }

    /** Cambia solo el estado desde el detalle; Inertia refresca el historial sin recargar. */
    public function cambiarEstado(CambiarEstadoEnvioRequest $request, int $envio): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $envio): void {
            $modelo = Envio::query()->lockForUpdate()->findOrFail($envio);
            $nuevo = $this->estadoId($data['estado']);

            if ((int) $modelo->estado_envio_id === $nuevo) {
                throw ValidationException::withMessages(['estado' => 'El envío ya se encuentra en ese estado.']);
            }

            $modelo->estado_envio_id = $nuevo;
            $modelo->notaEvento = $data['nota'] ?? null;
            $modelo->latitudEvento = $data['latitud'] ?? null;
            $modelo->longitudEvento = $data['longitud'] ?? null;
            $modelo->save();
        });

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    /* ===================== CONSULTAS DE APOYO ===================== */

    /** Consulta base con todos los joins; `fe.fecha` es la fecha real de entrega (evento ENTREGADO). */
    private function base(): Builder
    {
        return DB::table('envios.envios as e')
            ->join('envios.clientes as r', 'r.id_cliente', '=', 'e.cliente_remitente_id')
            ->join('envios.clientes as d', 'd.id_cliente', '=', 'e.cliente_destinatario_id')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->join('territorio.territorios as t', 't.id_territorio', '=', 'e.territorio_destino_id')
            ->join('territorio.sucursales as su', 'su.id_sucursal', '=', 'e.sucursal_origen_id')
            ->leftJoinLateral(
                DB::table('envios.envio_eventos as ev')
                    ->join('envios.estados_envios as se', 'se.id_estado', '=', 'ev.estado_envio_id')
                    ->whereColumn('ev.envio_id', 'e.id_envio')
                    ->where('se.nombre_estado', 'ENTREGADO')
                    ->selectRaw('max(ev.ocurrido_en) as fecha'),
                'fe'
            );
    }

    /** @return array<string, mixed>|null */
    private function detalle(int $id): ?array
    {
        $fila = $this->base()
            ->where('e.id_envio', $id)
            ->first([
                'e.id_envio', 'e.codigo_guia', 'r.nombre as remitente', 'd.nombre as destinatario',
                'su.nombre_sucursal as sucursal_origen', 't.nombre_territorio as territorio_destino',
                'e.direccion_destino', 'e.peso_kg', 'e.volumen_m3', 's.nombre_estado as estado',
                'e.prioridad', 'e.fecha_registro', 'e.fecha_comprometida', 'fe.fecha as fecha_entrega',
            ]);
        if ($fila === null) {
            return null;
        }

        return (array) $fila;
    }

    /** Filtros recibidos por URL (validados) con valores por defecto y tipos normalizados. */
    /** @return array<string, mixed> */
    private function filtros(Request $request): array
    {
        $validos = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:'.implode(',', self::ESTADOS)],
            'sucursal' => ['nullable', 'integer'],
            'prioridad' => ['nullable', 'in:BAJA,NORMAL,URGENTE'],
            'destino' => ['nullable', 'integer'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'alerta' => ['nullable', 'boolean'],
        ]);

        $f = array_merge(
            ['q' => null, 'estado' => null, 'sucursal' => null, 'prioridad' => null, 'destino' => null, 'desde' => null, 'hasta' => null, 'alerta' => false],
            $validos
        );

        $f['sucursal'] = $f['sucursal'] !== null ? (int) $f['sucursal'] : null;
        $f['destino'] = $f['destino'] !== null ? (int) $f['destino'] : null;
        $f['alerta'] = (bool) $f['alerta'];

        return $f;
    }

    /**
     * Aplica los filtros en SQL. `$omit` permite que un gráfico ignore su propio filtro
     * (filtro cruzado) y así siga mostrando las demás opciones.
     */
    /** @param array<string, mixed> $f */
    private function aplicar(Builder $q, array $f, ?string $omit = null): Builder
    {
        if ($f['q']) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w
                ->where('e.codigo_guia', 'ilike', $like)
                ->orWhere('r.nombre', 'ilike', $like)
                ->orWhere('d.nombre', 'ilike', $like));
        }
        if ($f['estado'] && $omit !== 'estado') {
            $q->where('s.nombre_estado', $f['estado']);
        }
        if ($f['prioridad'] && $omit !== 'prioridad') {
            $q->where('e.prioridad', $f['prioridad']);
        }
        if ($f['destino'] && $omit !== 'destino') {
            $q->where('e.territorio_destino_id', $f['destino']);
        }
        if ($f['sucursal']) {
            $q->where('e.sucursal_origen_id', $f['sucursal']);
        }
        if ($f['alerta']) {
            // Abiertos que ya vencieron o vencen en las próximas 24 h
            $q->whereRaw(self::ABIERTO." AND e.fecha_comprometida < now() + interval '24 hours'");
        }
        if ($f['desde']) {
            $q->where('e.fecha_registro', '>=', Carbon::parse($f['desde'])->startOfDay());
        }
        if ($f['hasta']) {
            $q->where('e.fecha_registro', '<=', Carbon::parse($f['hasta'])->endOfDay());
        }

        return $q;
    }

    /** Indicadores de BI calculados en la base, sobre TODO el conjunto filtrado (no solo la página).
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function resumen(array $f): array
    {
        $k = $this->aplicar($this->base(), $f)->selectRaw("
            count(*) as total,
            coalesce(sum(e.peso_kg), 0) as peso_total,
            count(*) FILTER (WHERE s.nombre_estado IN ('ENTREGADO','DEVUELTO','FALLIDO')) as cerrados,
            count(*) FILTER (WHERE s.nombre_estado = 'ENTREGADO' AND fe.fecha <= e.fecha_comprometida) as a_tiempo,
            count(*) FILTER (WHERE ".self::ABIERTO.' AND e.fecha_comprometida < now()) as vencidos,
            count(*) FILTER (WHERE '.self::ABIERTO." AND e.fecha_comprometida >= now()
                             AND e.fecha_comprometida < now() + interval '24 hours') as en_riesgo,
            avg(extract(epoch FROM (fe.fecha - e.fecha_registro)) / 3600)
                FILTER (WHERE s.nombre_estado = 'ENTREGADO' AND fe.fecha IS NOT NULL) as horas_promedio,
            count(*) FILTER (WHERE s.nombre_estado IN ('FALLIDO','DEVUELTO')) as fallos
        ")->first();

        $embudo = $this->aplicar($this->base(), $f, 'estado')
            ->selectRaw('s.nombre_estado as estado, count(*) as total')
            ->groupBy('s.nombre_estado')
            ->pluck('total', 'estado');

        $prioridad = $this->aplicar($this->base(), $f, 'prioridad')
            ->selectRaw('e.prioridad, count(*) as total,
                count(*) FILTER (WHERE '.self::ABIERTO." AND e.fecha_comprometida < now() + interval '24 hours') as criticos")
            ->groupBy('e.prioridad')
            ->get()
            ->keyBy('prioridad');

        $destinos = $this->aplicar($this->base(), $f, 'destino')
            ->selectRaw('t.id_territorio as id, t.nombre_territorio as destino, count(*) as total,
                count(*) FILTER (WHERE '.self::INCIDENCIA.') as incidencias')
            ->groupBy('t.id_territorio', 't.nombre_territorio')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        // Registros por día (últimos 14 días) con la parte que terminó en incidencia
        $inicio = now()->startOfDay()->subDays(13);
        $porDia = $this->aplicar($this->base(), $f)
            ->where('e.fecha_registro', '>=', $inicio)
            ->selectRaw('e.fecha_registro::date as dia, count(*) as total,
                count(*) FILTER (WHERE '.self::INCIDENCIA.') as incidencias')
            ->groupByRaw('e.fecha_registro::date')
            ->get()
            ->keyBy('dia');

        $tendencia = collect(range(0, 13))->map(function (int $i) use ($inicio, $porDia) {
            $dia = $inicio->copy()->addDays($i);
            $fila = $porDia->get($dia->toDateString());

            return [
                'label' => $dia->format('j/n'),
                'total' => (int) ($fila->total ?? 0),
                'incidencias' => (int) ($fila->incidencias ?? 0),
            ];
        })->values();

        return [
            'kpis' => [
                'total' => (int) $k->total,
                'peso_total' => (float) $k->peso_total,
                'cerrados' => (int) $k->cerrados,
                'a_tiempo' => (int) $k->a_tiempo,
                'vencidos' => (int) $k->vencidos,
                'en_riesgo' => (int) $k->en_riesgo,
                'horas_promedio' => $k->horas_promedio !== null ? (float) $k->horas_promedio : null,
                'fallos' => (int) $k->fallos,
            ],
            'embudo' => collect(self::ESTADOS)
                ->map(fn ($estado) => ['estado' => $estado, 'total' => (int) ($embudo[$estado] ?? 0)])
                ->values(),
            'prioridad' => collect(['URGENTE', 'NORMAL', 'BAJA'])
                ->map(fn ($p) => [
                    'prioridad' => $p,
                    'total' => (int) ($prioridad[$p]->total ?? 0),
                    'criticos' => (int) ($prioridad[$p]->criticos ?? 0),
                ])->values(),
            'destinos' => $destinos->map(fn ($d) => [
                'id' => $d->id,
                'destino' => $d->destino,
                'total' => (int) $d->total,
                'incidencias' => (int) $d->incidencias,
            ])->values(),
            'tendencia' => $tendencia,
        ];
    }

    /* ===================== UTILIDADES ===================== */

    /** @return array<string, mixed> */
    private function catalogosFormulario(): array
    {
        return [
            'clientes' => DB::table('envios.clientes')
                ->orderBy('nombre')
                ->get(['id_cliente as id', 'nombre', 'tipo_cliente']),
            'sucursales' => $this->sucursales(),
            'territorios' => $this->territorios(),
        ];
    }

    private function sucursales(): object
    {
        return DB::table('territorio.sucursales')
            ->where('activa', true)
            ->orderBy('nombre_sucursal')
            ->get(['id_sucursal as id', 'nombre_sucursal as nombre']);
    }

    private function territorios(): object
    {
        return DB::table('territorio.territorios')
            ->orderBy('nombre_territorio')
            ->get(['id_territorio as id', 'nombre_territorio as nombre']);
    }

    /** @return array<string, bool> */
    private function permisos(): array
    {
        return [
            'registrar' => Gate::allows('envios.registrar'),
            'editar' => Gate::allows('envios.editar'),
        ];
    }

    private function estadoId(string $nombre): int
    {
        return (int) DB::table('envios.estados_envios')->where('nombre_estado', $nombre)->value('id_estado');
    }

    private function iso(?string $valor): ?string
    {
        return $valor ? Carbon::parse($valor)->toIso8601String() : null;
    }
}
