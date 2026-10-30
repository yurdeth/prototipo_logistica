<?php

namespace App\Http\Controllers\Rutas;

use App\Enums\EstadoDespachoEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rutas\StoreDespachoRequest;
use App\Models\Despacho;
use App\Models\DespachoParada;
use App\Models\Empleado;
use App\Models\Sucursal;
use App\Models\Vehiculo;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Despachos operativos: programar salidas (vehículo + conductor + paradas con
 * envíos) y consultarlas junto a la red logística. Los shapes que devuelve
 * coinciden con los tipos de resources/js/config/Rutas (Despachoform y rutas),
 * que también consume el Dashboard en su pestaña analítica.
 */
class DespachoController extends Controller
{
    /** Cuántos despachos recientes alimentan los paneles (índice y dashboard). */
    private const LIMITE_PANEL = 60;

    /** Tamaño de página del listado paginado de envíos disponibles (scroll infinito). */
    private const TAM_PAGINA_ENVIOS = 20;

    /** Columnas mínimas del envío disponible que consume el formulario. */
    private const COLUMNAS_ENVIO = [
        'e.id_envio',
        'e.codigo_guia',
        't.nombre_territorio as destino',
        'e.peso_kg',
        'e.prioridad',
        'e.fecha_comprometida',
    ];

    public function index(): Response
    {
        return Inertia::render('rutas/RutasIndex', [
            'despachos' => self::paraPanel(),
            'conexiones' => self::conexiones(),
            'permisos' => $this->permisos(),
        ]);
    }

    /** @return array<string, bool> */
    private function permisos(): array
    {
        return [
            'gestionar' => Gate::allows('despachos.gestionar'),
        ];
    }

    public function create(): Response
    {
        return Inertia::render('rutas/RutasCreate', [
            'sucursales' => Sucursal::query()
                ->where('activa', true)
                ->orderBy('nombre_sucursal')
                ->get(['id_sucursal as id', 'nombre_sucursal as nombre']),
            'conductores' => Empleado::query()
                ->whereNotNull('numero_licencia')
                ->where('activo', true)
                ->whereNotExists(self::asignacionVigente('d.conductor_empleado_id', 'personal.empleados.id_empleado'))
                ->orderBy('apellidos')
                ->get()
                ->map(fn (Empleado $e) => ['id' => $e->id_empleado, 'nombre' => "{$e->nombres} {$e->apellidos}"]),
            'vehiculos' => Vehiculo::query()
                ->with('tipo')
                ->whereHas('estado', fn ($q) => $q->where('nombre_estado', 'DISPONIBLE'))
                ->whereNotExists(self::asignacionVigente('d.vehiculo_id', 'flota.vehiculos.id_vehiculo'))
                ->orderBy('placa')
                ->get()
                ->map(fn (Vehiculo $v) => [
                    'id' => $v->id_vehiculo,
                    'placa' => $v->placa,
                    'tipo' => $v->tipo->nombre,
                    'capacidad_kg' => (float) $v->tipo->capacidad_kg,
                    // la flota aún no registra costo por km; la vista lo tolera en null
                    'costo_km' => null,
                ]),
            'envios' => self::enviosDisponibles(),
            'ocupados' => self::ocupaciones(),
        ]);
    }

    /**
     * Subconsulta de asignaciones a despachos sin cerrar: un recurso ligado a un
     * despacho PLANIFICADO o EN_CURSO queda excluido de las opciones del formulario.
     *
     * @return Builder
     */
    private static function asignacionVigente(string $columnaDespacho, string $columnaRecurso)
    {
        return DB::table('rutas.despachos as d')
            ->join('rutas.estados_despachos as s', 's.id_estado', '=', 'd.estado_despacho_id')
            ->whereColumn($columnaDespacho, $columnaRecurso)
            ->whereIn('s.nombre_estado', ['PLANIFICADO', 'EN_CURSO']);
    }

    public function store(StoreDespachoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $despacho = DB::transaction(function () use ($datos): Despacho {
            $despacho = Despacho::create([
                ...$datos,
                'estado_despacho_id' => $this->estadoId(EstadoDespachoEnum::PLANIFICADO->value),
            ]);

            foreach ($datos['paradas'] as $parada) {
                $despacho->paradas()->create([
                    'envio_id' => $parada['envio_id'],
                    'orden' => $parada['orden'],
                    'hora_estimada' => $parada['hora_estimada'] ?? null,
                ]);
            }

            return $despacho;
        });

        return redirect()
            ->route('rutas.index')
            ->with('success', "Despacho {$this->codigo($despacho)} registrado con ".count($datos['paradas']).' paradas.');
    }

    /**
     * Despachos para los paneles, con paradas anidadas; el más reciente primero.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paraPanel(): array
    {
        return Despacho::query()
            ->with(['sucursalOrigen', 'conductor', 'vehiculo', 'estado', 'paradas.envio.territorioDestino'])
            ->orderByDesc('fecha')
            ->orderByDesc('id_despacho')
            ->limit(self::LIMITE_PANEL)
            ->get()
            ->map(function (Despacho $d): array {
                /** @var Collection<int, array<string, mixed>> $paradas */
                $paradas = $d->paradas
                    ->sortBy('orden')
                    ->values()
                    ->map(fn (DespachoParada $p): array => [
                        'id_parada' => $p->id_parada,
                        'orden' => $p->orden,
                        'codigo_guia' => $p->envio->codigo_guia,
                        'destino' => $p->envio->territorioDestino->nombre_territorio,
                        'hora_estimada' => $p->hora_estimada?->toISOString(),
                        'hora_real' => $p->hora_real?->toISOString(),
                    ]);

                return [
                    'id_despacho' => $d->id_despacho,
                    'codigo' => self::codigo($d),
                    'fecha' => $d->fecha->toDateString(),
                    'sucursal_origen' => $d->sucursalOrigen->nombre_sucursal,
                    'conductor' => "{$d->conductor->nombres} {$d->conductor->apellidos}",
                    'vehiculo' => $d->vehiculo->placa,
                    'estado' => $d->estado->nombre_estado,
                    'km_planificados' => $d->km_planificados !== null ? (float) $d->km_planificados : null,
                    'km_recorridos' => $d->km_recorridos !== null ? (float) $d->km_recorridos : null,
                    'costo_estimado' => $d->costo_estimado !== null ? (float) $d->costo_estimado : null,
                    'paradas' => $paradas->values()->all(),
                ];
            })
            ->all();
    }

    /**
     * Red logística para los paneles (conexiones con su peso real).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function conexiones(): array
    {
        return DB::table('rutas.conexiones as c')
            ->join('territorio.sucursales as o', 'o.id_sucursal', '=', 'c.origen_sucursal_id')
            ->join('territorio.sucursales as d', 'd.id_sucursal', '=', 'c.destino_sucursal_id')
            ->orderBy('c.id_conexion')
            ->get([
                'c.id_conexion',
                'o.nombre_sucursal as origen',
                'd.nombre_sucursal as destino',
                'c.distancia_km',
                'c.tiempo_min',
                'c.activa',
            ])
            ->map(fn ($c) => [
                'id_conexion' => $c->id_conexion,
                'origen' => $c->origen,
                'destino' => $c->destino,
                'distancia_km' => (float) $c->distancia_km,
                'tiempo_min' => (int) $c->tiempo_min,
                'activa' => (bool) $c->activa,
            ])
            ->all();
    }

    /**
     * Primera página de envíos que aún pueden despacharse: abiertos y sin
     * despacho asignado; el resto llega paginado por el endpoint de búsqueda.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function enviosDisponibles(): array
    {
        return self::consultaEnviosDisponibles()
            ->limit(self::TAM_PAGINA_ENVIOS)
            ->get(self::COLUMNAS_ENVIO)
            ->map(self::mapearEnvio(...))
            ->all();
    }

    /**
     * Página del listado de envíos disponibles (guía o destino como filtro);
     * alimenta el scroll infinito del formulario sin cargar toda la tabla.
     */
    public function buscarEnvios(Request $request): JsonResponse
    {
        $validos = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $pagina = max(1, (int) ($validos['page'] ?? 1));

        // Pide una fila más para saber si hay página siguiente sin COUNT(*)
        $filas = self::consultaEnviosDisponibles($validos['q'] ?? null)
            ->offset(($pagina - 1) * self::TAM_PAGINA_ENVIOS)
            ->limit(self::TAM_PAGINA_ENVIOS + 1)
            ->get(self::COLUMNAS_ENVIO);

        return response()->json([
            'data' => $filas->take(self::TAM_PAGINA_ENVIOS)->map(self::mapearEnvio(...))->values()->all(),
            'hay_mas' => $filas->count() > self::TAM_PAGINA_ENVIOS,
        ]);
    }

    /**
     * Consulta base de envíos disponibles; con término, filtra por guía o destino.
     *
     * @return Builder
     */
    private static function consultaEnviosDisponibles(?string $termino = null)
    {
        return DB::table('envios.envios as e')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->leftJoin('rutas.despacho_paradas as p', 'p.envio_id', '=', 'e.id_envio')
            ->join('territorio.territorios as t', 't.id_territorio', '=', 'e.territorio_destino_id')
            ->whereNull('p.id_parada')
            ->whereRaw("s.nombre_estado NOT IN ('ENTREGADO','DEVUELTO','FALLIDO')")
            ->when($termino !== null && $termino !== '', function ($consulta) use ($termino) {
                $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $termino).'%';

                $consulta->where(function ($sub) use ($patron) {
                    $sub->where('e.codigo_guia', 'ilike', $patron)
                        ->orWhere('t.nombre_territorio', 'ilike', $patron);
                });
            })
            ->orderBy('e.fecha_comprometida');
    }

    /** @return array<string, mixed> */
    private static function mapearEnvio(\stdClass $e): array
    {
        return [
            'id_envio' => $e->id_envio,
            'codigo_guia' => $e->codigo_guia,
            'destino' => $e->destino,
            'peso_kg' => (float) $e->peso_kg,
            'prioridad' => $e->prioridad,
            'fecha_comprometida' => Carbon::parse($e->fecha_comprometida)->toISOString(),
        ];
    }

    /**
     * Doble reserva de recursos: conductor y vehículo por día en despachos no completados.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function ocupaciones(): array
    {
        return DB::table('rutas.despachos as d')
            ->join('rutas.estados_despachos as s', 's.id_estado', '=', 'd.estado_despacho_id')
            ->whereIn('s.nombre_estado', ['PLANIFICADO', 'EN_CURSO'])
            ->get(['d.fecha', 'd.conductor_empleado_id as conductor_id', 'd.vehiculo_id as vehiculo_id'])
            ->map(fn ($o) => [
                'fecha' => $o->fecha,
                'conductor_id' => $o->conductor_id,
                'vehiculo_id' => $o->vehiculo_id,
            ])
            ->all();
    }

    /** Identificador de presentación DSP-0001 (la tabla no guarda código). */
    private static function codigo(Despacho $despacho): string
    {
        return 'DSP-'.str_pad((string) $despacho->id_despacho, 4, '0', STR_PAD_LEFT);
    }

    private function estadoId(string $nombre): int
    {
        return (int) DB::table('rutas.estados_despachos')->where('nombre_estado', $nombre)->value('id_estado');
    }
}
