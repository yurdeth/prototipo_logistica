<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\StoreClienteRequest;
use App\Http\Requests\Clientes\UpdateClienteRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClienteController extends Controller
{
    public const TIPOS = ['PERSONA', 'EMPRESA'];

    /** Un cliente sin envíos en este número de días se considera inactivo (igual en Clientes.vue). */
    public const DIAS_INACTIVO = 90;

    /** Envíos donde el cliente aparece como remitente o destinatario (c = cliente). */
    private const ENVIOS = '(SELECT count(*) FROM envios.envios x
        WHERE x.cliente_remitente_id = c.id_cliente OR x.cliente_destinatario_id = c.id_cliente)';

    /**
     * Fecha del último envío del cliente (c = cliente).
     * SUPUESTO: la fecha del envío es x.created_at; cámbiala si usas otra columna (p. ej. fecha de registro).
     */
    private const ULTIMO_ENVIO = '(SELECT max(x.created_at) FROM envios.envios x
        WHERE x.cliente_remitente_id = c.id_cliente OR x.cliente_destinatario_id = c.id_cliente)';

    /* ===================== LISTAR ===================== */

    public function index(Request $request): Response
    {
        $f = $this->filtros($request);
        $limite = now()->subDays(self::DIAS_INACTIVO);

        $clientes = $this->aplicar($this->base(), $f)
            ->select([
                'c.id_cliente',
                'c.nombre',
                'c.tipo_cliente',
                'c.contacto',
                't.nombre_territorio as territorio',
                'c.created_at',
            ])
            ->selectRaw(self::ENVIOS.' as total_envios')
            ->selectRaw(self::ULTIMO_ENVIO.' as ultimo_envio')
            ->orderBy('c.nombre')
            ->orderBy('c.id_cliente')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($c) => [
                'id_cliente' => $c->id_cliente,
                'nombre' => $c->nombre,
                'tipo_cliente' => $c->tipo_cliente,
                'contacto' => $c->contacto,
                'territorio' => $c->territorio,
                'total_envios' => (int) $c->total_envios,
                'created_at' => $c->created_at ? Carbon::parse($c->created_at)->toIso8601String() : null,
                'ultimo_envio' => $c->ultimo_envio ? Carbon::parse($c->ultimo_envio)->toIso8601String() : null,
                'inactivo' => $c->ultimo_envio !== null && Carbon::parse($c->ultimo_envio)->lt($limite),
            ]);

        return Inertia::render('clientes/ClientesIndex', [
            'clientes' => $clientes,
            'filtros' => $f,
            'catalogos' => ['territorios' => $this->territorios()],
            'resumen' => $this->resumen($f),
            'analisis' => $this->analisis($f),
            'permisos' => $this->permisos(),
        ]);
    }

    /** CSV con todos los clientes que cumplen los filtros (mismos parámetros que el índice). */
    public function exportar(Request $request): StreamedResponse
    {
        $f = $this->filtros($request);

        $consulta = $this->aplicar($this->base(), $f)
            ->select(['c.nombre', 'c.tipo_cliente', 'c.contacto', 't.nombre_territorio as territorio'])
            ->selectRaw(self::ENVIOS.' as total_envios')
            ->selectRaw(self::ULTIMO_ENVIO.' as ultimo_envio')
            ->orderBy('c.nombre');

        // Evita que Excel interprete como fórmula un texto que empieza con = + - @
        $seguro = fn ($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;

        return response()->streamDownload(function () use ($consulta, $seguro): void {
            $salida = fopen('php://output', 'w');

            if ($salida === false) {
                return;
            }

            fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos
            fputcsv($salida, ['Nombre', 'Tipo', 'Contacto', 'Territorio', 'Envíos', 'Último envío']);

            foreach ($consulta->cursor() as $c) {
                fputcsv($salida, [
                    $seguro($c->nombre),
                    $c->tipo_cliente,
                    $seguro($c->contacto),
                    $seguro($c->territorio),
                    $c->total_envios,
                    $c->ultimo_envio ? Carbon::parse($c->ultimo_envio)->format('Y-m-d') : '',
                ]);
            }

            fclose($salida);
        }, 'clientes-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ===================== CREAR ===================== */

    public function create(): Response
    {
        return Inertia::render('clientes/ClientesCreate', [
            'territorios' => $this->territorios(),
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::table('envios.clientes')->insert([
            'nombre' => $data['nombre'],
            'tipo_cliente' => $data['tipo_cliente'],
            'contacto' => $data['contacto'] ?? null,
            'territorio_id' => $data['territorio_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return to_route('clientes.index')->with('success', 'Cliente registrado correctamente.');
    }

    /* ===================== EDITAR ===================== */

    public function edit(int $cliente): Response
    {
        $c = DB::table('envios.clientes as c')
            ->where('c.id_cliente', $cliente)
            ->selectRaw('c.id_cliente, c.nombre, c.tipo_cliente, c.contacto, c.territorio_id, '.self::ENVIOS.' as total_envios')
            ->first() ?? abort(404);

        return Inertia::render('clientes/ClientesEdit', [
            'cliente' => [
                'id_cliente' => $c->id_cliente,
                'nombre' => $c->nombre,
                'tipo_cliente' => $c->tipo_cliente,
                'contacto' => $c->contacto,
                'territorio_id' => $c->territorio_id,
            ],
            'total_envios' => (int) $c->total_envios,
            'territorios' => $this->territorios(),
        ]);
    }

    public function update(UpdateClienteRequest $request, int $cliente): RedirectResponse
    {
        $data = $request->validated();

        $afectadas = DB::table('envios.clientes')
            ->where('id_cliente', $cliente)
            ->update([
                'nombre' => $data['nombre'],
                'tipo_cliente' => $data['tipo_cliente'],
                'contacto' => $data['contacto'] ?? null,
                'territorio_id' => $data['territorio_id'],
                'updated_at' => now(),
            ]);

        abort_if($afectadas === 0, 404);

        return to_route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    /* ===================== ELIMINAR ===================== */

    public function destroy(int $cliente): RedirectResponse
    {
        abort_unless(DB::table('envios.clientes')->where('id_cliente', $cliente)->exists(), 404);

        // Un cliente con envíos no se elimina: dejaría huérfano el historial de esos envíos.
        $envios = DB::table('envios.envios')
            ->where('cliente_remitente_id', $cliente)
            ->orWhere('cliente_destinatario_id', $cliente)
            ->count();

        if ($envios > 0) {
            return back()->withErrors([
                'eliminar' => "No se puede eliminar: el cliente tiene {$envios} envío(s) asociado(s).",
            ]);
        }

        try {
            DB::table('envios.clientes')->where('id_cliente', $cliente)->delete();
        } catch (QueryException $e) {
            // 23503 = violación de llave foránea (alguien registró un envío justo ahora)
            if ($e->getCode() === '23503') {
                return back()->withErrors(['eliminar' => 'No se puede eliminar: el cliente ya tiene envíos asociados.']);
            }

            throw $e;
        }

        return to_route('clientes.index')->with('success', 'Cliente eliminado correctamente.');
    }

    /* ===================== CONSULTAS DE APOYO ===================== */

    private function base(): Builder
    {
        return DB::table('envios.clientes as c')
            ->join('territorio.territorios as t', 't.id_territorio', '=', 'c.territorio_id');
    }

    /**
     * Filtros recibidos por URL (validados) con valores por defecto y tipos normalizados.
     *
     * @return array<string, mixed>
     */
    private function filtros(Request $request): array
    {
        $validos = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'in:'.implode(',', self::TIPOS)],
            'territorio' => ['nullable', 'integer'],
            'inactivos' => ['nullable', 'boolean'],
        ]);

        $f = array_merge(['q' => null, 'tipo' => null, 'territorio' => null, 'inactivos' => false], $validos);
        $f['territorio'] = $f['territorio'] !== null ? (int) $f['territorio'] : null;
        $f['inactivos'] = filter_var($f['inactivos'], FILTER_VALIDATE_BOOLEAN);

        return $f;
    }

    /**
     * Aplica los filtros en SQL. `$omit` permite que las fichas de tipo ignoren su propio filtro
     * y así sigan mostrando el conteo de las demás opciones.
     *
     * @param  array<string, mixed>  $f
     */
    private function aplicar(Builder $q, array $f, ?string $omit = null): Builder
    {
        if ($f['q']) {
            $like = '%'.addcslashes($f['q'], '%_\\').'%';
            $q->where(fn ($w) => $w
                ->where('c.nombre', 'ilike', $like)
                ->orWhere('c.contacto', 'ilike', $like));
        }
        if ($f['tipo'] && $omit !== 'tipo') {
            $q->where('c.tipo_cliente', $f['tipo']);
        }
        if ($f['territorio']) {
            $q->where('c.territorio_id', $f['territorio']);
        }
        if ($f['inactivos']) {
            // Con envíos, pero el último fue hace más de DIAS_INACTIVO días (sin envíos no entra).
            $q->whereRaw(self::ULTIMO_ENVIO.' < ?', [now()->subDays(self::DIAS_INACTIVO)]);
        }

        return $q;
    }

    /**
     * Indicadores calculados en la base sobre TODO el conjunto filtrado (no solo la página).
     *
     * @param  array<string, mixed>  $f
     * @return array<string, mixed>
     */
    private function resumen(array $f): array
    {
        $hace30 = now()->subDays(30);
        $hace60 = now()->subDays(60);
        $limite = now()->subDays(self::DIAS_INACTIVO);

        $k = $this->aplicar($this->base(), $f)->selectRaw('
            count(*) as total,
            count(*) FILTER (WHERE c.tipo_cliente = \'EMPRESA\') as empresas,
            count(*) FILTER (WHERE c.tipo_cliente = \'PERSONA\') as personas,
            count(*) FILTER (WHERE '.self::ENVIOS.' > 0) as con_envios,
            count(*) FILTER (WHERE c.created_at >= ?) as nuevos,
            count(*) FILTER (WHERE c.created_at >= ? AND c.created_at < ?) as nuevos_previos,
            count(*) FILTER (WHERE '.self::ULTIMO_ENVIO.' < ?) as inactivos
        ', [$hace30, $hace60, $hace30, $limite])->first();

        $tipos = $this->aplicar($this->base(), $f, 'tipo')
            ->selectRaw('c.tipo_cliente as tipo, count(*) as total')
            ->groupBy('c.tipo_cliente')
            ->pluck('total', 'tipo');

        return [
            'kpis' => [
                'total' => (int) $k->total,
                'empresas' => (int) $k->empresas,
                'personas' => (int) $k->personas,
                'con_envios' => (int) $k->con_envios,
                'nuevos' => (int) $k->nuevos,
                'nuevos_previos' => (int) $k->nuevos_previos,
                'inactivos' => (int) $k->inactivos,
            ],
            'tipos' => collect(self::TIPOS)
                ->map(fn (string $t) => ['tipo' => $t, 'total' => (int) ($tipos[$t] ?? 0)])
                ->values(),
        ];
    }

    /**
     * Concentración de envíos: top 10 de clientes del conjunto filtrado.
     * `envios_totales` suma los envíos de TODOS esos clientes con el mismo criterio que la columna
     * "Envíos" (remitente o destinatario), de modo que los porcentajes del top sean coherentes.
     *
     * @param  array<string, mixed>  $f
     * @return array{top: array<int, array{id_cliente: mixed, nombre: mixed, total_envios: int}>, envios_totales: int}
     */
    private function analisis(array $f): array
    {
        $top = $this->aplicar($this->base(), $f)
            ->select(['c.id_cliente', 'c.nombre'])
            ->selectRaw(self::ENVIOS.' as total_envios')
            ->orderByDesc('total_envios')
            ->orderBy('c.nombre')
            ->limit(10)
            ->get()
            ->filter(fn ($c) => (int) $c->total_envios > 0)
            ->map(fn ($c) => [
                'id_cliente' => $c->id_cliente,
                'nombre' => $c->nombre,
                'total_envios' => (int) $c->total_envios,
            ])
            ->values()
            ->all();

        $total = (int) DB::query()
            ->fromSub($this->aplicar($this->base(), $f)->selectRaw(self::ENVIOS.' as n'), 't')
            ->sum('n');

        return ['top' => $top, 'envios_totales' => $total];
    }

    /** @return Collection<int, \stdClass> */
    private function territorios(): Collection
    {
        return DB::table('territorio.territorios')
            ->orderBy('nombre_territorio')
            ->get(['id_territorio as id', 'nombre_territorio as nombre']);
    }

    /** @return array<string, bool> */
    private function permisos(): array
    {
        return [
            'registrar' => Gate::allows('clientes.registrar'),
            'editar' => Gate::allows('clientes.editar'),
            'eliminar' => Gate::allows('clientes.eliminar'),
        ];
    }
}
