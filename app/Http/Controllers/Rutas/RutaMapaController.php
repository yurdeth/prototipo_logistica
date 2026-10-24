<?php

namespace App\Http\Controllers\Rutas;

use App\Http\Controllers\Controller;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mapa de rutas: lee los despachos reales (rutas.*) sin modificar el listado actual.
 *
 * Las paradas priorizan coordenadas reportadas; si faltan, usan una referencia
 * aproximada derivada de las ubicaciones de sucursales del territorio.
 */
class RutaMapaController extends Controller
{
    private const ESTADOS_ENVIO = [
        'REGISTRADO',
        'RECOLECTADO',
        'EN_TRANSITO',
        'EN_CENTRO',
        'EN_RUTA',
        'ENTREGADO',
        'DEVUELTO',
        'FALLIDO',
    ];

    /* ===================== PÁGINA ===================== */

    public function index(): Response
    {
        return Inertia::render('rutas/RutasMapa');
    }

    /* ===================== DATOS (JSON) ===================== */

    public function datos(Request $request): JsonResponse
    {
        $validos = $request->validate([
            'dias' => ['nullable', 'integer', 'between:0,365'],
            'estado' => ['nullable', 'in:'.implode(',', self::ESTADOS_ENVIO)],
            'ruta' => ['nullable', 'integer', 'min:1'],
            'territorio' => ['nullable', 'integer', 'min:1'],
        ]);
        $filtros = [
            'dias' => (int) ($validos['dias'] ?? 7),
            'estado' => $validos['estado'] ?? null,
            'ruta' => isset($validos['ruta']) ? (int) $validos['ruta'] : null,
            'territorio' => isset($validos['territorio']) ? (int) $validos['territorio'] : null,
        ];
        $filtros['territorio_ids'] = $filtros['territorio'] !== null
            ? $this->territoriosDescendientes($filtros['territorio'])
            : null;

        $desde = $filtros['dias'] > 0
            ? now()->startOfDay()->subDays($filtros['dias'] - 1)
            : null;
        $consulta = $this->aplicarFiltros(
            $this->enviosBase($filtros['ruta']),
            $filtros,
            $desde,
        );

        $kpis = (clone $consulta)
            ->selectRaw("
                count(*) as total,
                count(*) FILTER (WHERE s.nombre_estado = 'REGISTRADO') as pendientes,
                count(*) FILTER (WHERE s.nombre_estado IN ('RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA')) as en_ruta,
                count(*) FILTER (WHERE s.nombre_estado = 'ENTREGADO') as entregados,
                count(*) FILTER (WHERE s.nombre_estado IN ('DEVUELTO', 'FALLIDO')) as fallidos,
                count(*) FILTER (WHERE COALESCE(ie.total, 0) > 0) as envios_con_incidencias,
                COALESCE(
                    round(100.0 * count(*) FILTER (WHERE s.nombre_estado = 'ENTREGADO')
                        / NULLIF(count(*), 0), 1),
                    0
                ) as tasa_entrega
            ")
            ->first();

        $consultaEstados = $this->aplicarFiltros(
            $this->enviosBase($filtros['ruta']),
            [...$filtros, 'estado' => null],
            $desde,
        );
        $porEstado = $consultaEstados
            ->selectRaw('s.nombre_estado as estado, count(*) as total')
            ->groupBy('s.nombre_estado')
            ->pluck('total', 'estado');

        $territorios = (clone $consulta)
            ->selectRaw("
                t.id_territorio as id,
                t.nombre_territorio as nombre,
                t.tipo_territorio as tipo,
                t.territorio_padre_id as padre_id,
                padre.nombre_territorio as padre,
                count(*) as total,
                count(*) FILTER (WHERE s.nombre_estado = 'ENTREGADO') as entregados,
                count(*) FILTER (WHERE s.nombre_estado = 'REGISTRADO') as pendientes,
                count(*) FILTER (WHERE s.nombre_estado IN ('RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA')) as en_ruta,
                count(*) FILTER (WHERE COALESCE(ie.total, 0) > 0) as envios_con_incidencias,
                COALESCE(
                    round(100.0 * count(*) FILTER (WHERE s.nombre_estado = 'ENTREGADO')
                        / NULLIF(count(*), 0), 1),
                    0
                ) as tasa_entrega
            ")
            ->groupBy(
                't.id_territorio',
                't.nombre_territorio',
                't.tipo_territorio',
                't.territorio_padre_id',
                'padre.nombre_territorio'
            )
            ->orderByDesc('total')
            ->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'nombre' => $t->nombre,
                'tipo' => $t->tipo,
                'padre_id' => $t->padre_id !== null ? (int) $t->padre_id : null,
                'padre' => $t->padre,
                'total' => (int) $t->total,
                'entregados' => (int) $t->entregados,
                'pendientes' => (int) $t->pendientes,
                'en_ruta' => (int) $t->en_ruta,
                'envios_con_incidencias' => (int) $t->envios_con_incidencias,
                'tasa_entrega' => (float) $t->tasa_entrega,
            ])
            ->values();

        $consultaEnvios = (clone $consulta)->select([
            'e.id_envio',
            'e.codigo_guia',
            'e.direccion_destino',
            'e.fecha_registro',
            's.nombre_estado as estado',
            't.id_territorio as territorio_id',
            't.nombre_territorio as territorio',
            't.tipo_territorio as tipo_territorio',
            'padre.nombre_territorio as territorio_padre',
            'pos.lat as lat_reportada',
            'pos.lng as lng_reportada',
            'ie.total as incidencias',
            'ruta.id_despacho as ruta_id',
            'ruta.fecha as ruta_fecha',
            'ruta.estado as ruta_estado',
            'ruta.sucursal as ruta_sucursal',
            'ruta.orden',
            'ruta.hora_estimada',
            'ruta.hora_real',
        ]);
        $envios = $consultaEnvios
            ->orderByDesc('e.fecha_registro')
            ->get()
            ->map(fn ($e) => [
                'id' => (int) $e->id_envio,
                'codigo_guia' => $e->codigo_guia,
                'direccion' => $e->direccion_destino,
                'fecha_registro' => $this->iso($e->fecha_registro),
                'estado' => $e->estado,
                'territorio_id' => (int) $e->territorio_id,
                'territorio' => $e->territorio,
                'tipo_territorio' => $e->tipo_territorio,
                'territorio_padre' => $e->territorio_padre,
                'ruta_id' => $e->ruta_id !== null ? (int) $e->ruta_id : null,
                'ruta_codigo' => $e->ruta_id !== null
                    ? 'DSP-'.str_pad((string) $e->ruta_id, 6, '0', STR_PAD_LEFT)
                    : null,
                'ruta_fecha' => $e->ruta_fecha,
                'ruta_estado' => $e->ruta_estado,
                'ruta_sucursal' => $e->ruta_sucursal,
                'orden' => $e->orden !== null ? (int) $e->orden : null,
                'hora_estimada' => $this->iso($e->hora_estimada),
                'hora_real' => $this->iso($e->hora_real),
                'lat' => $this->numero($e->lat_reportada),
                'lng' => $this->numero($e->lng_reportada),
                'incidencias' => (int) ($e->incidencias ?? 0),
            ])
            ->values();

        $idsRuta = array_values($envios->pluck('ruta_id')->filter()->unique()->map(fn ($id) => (int) $id)->all());
        if ($filtros['ruta'] !== null) {
            $idsRuta[] = $filtros['ruta'];
            $idsRuta = array_values(array_unique($idsRuta));
        }
        $rutas = $this->rutas($idsRuta);
        $idsEnvio = array_values($envios->pluck('id')->map(fn ($id) => (int) $id)->all());
        $incidencias = $this->incidenciasFiltradas($idsEnvio, $idsRuta);

        return response()->json([
            'generado' => now()->toIso8601String(),
            'filtros' => $filtros,
            'kpis' => [
                'total' => (int) $kpis->total,
                'pendientes' => (int) $kpis->pendientes,
                'en_ruta' => (int) $kpis->en_ruta,
                'entregados' => (int) $kpis->entregados,
                'fallidos' => (int) $kpis->fallidos,
                'envios_con_incidencias' => (int) $kpis->envios_con_incidencias,
                'incidencias' => $incidencias,
                'tasa_entrega' => (float) $kpis->tasa_entrega,
            ],
            'estados' => collect(self::ESTADOS_ENVIO)->map(fn (string $estado) => [
                'estado' => $estado,
                'total' => (int) ($porEstado[$estado] ?? 0),
            ])->values(),
            'territorios' => $territorios,
            'catalogos' => [
                'estados' => self::ESTADOS_ENVIO,
                'rutas' => $this->opcionesRuta($desde),
                'territorios' => $this->territorios(),
            ],
            'envios' => $envios,
            'rutas' => $rutas,
            'sucursales' => $this->sucursales(),
        ]);
    }

    /* ===================== CONSULTAS DE APOYO ===================== */

    /** @return list<int> */
    private function territoriosDescendientes(int $territorio): array
    {
        return array_values(collect(DB::select(
            'WITH RECURSIVE jerarquia AS (
                SELECT id_territorio
                FROM territorio.territorios
                WHERE id_territorio = ?
                UNION
                SELECT hijo.id_territorio
                FROM territorio.territorios as hijo
                INNER JOIN jerarquia as padre ON padre.id_territorio = hijo.territorio_padre_id
            )
            SELECT id_territorio FROM jerarquia',
            [$territorio]
        ))
            ->pluck('id_territorio')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    /** @return Collection<int, array{id: int, nombre: string, tipo: string, padre_id: int|null, padre: string|null}> */
    private function territorios(): Collection
    {
        return DB::table('territorio.territorios as t')
            ->leftJoin('territorio.territorios as padre', 'padre.id_territorio', '=', 't.territorio_padre_id')
            ->selectRaw('
                t.id_territorio as id,
                t.nombre_territorio as nombre,
                t.tipo_territorio as tipo,
                t.territorio_padre_id as padre_id,
                padre.nombre_territorio as padre
            ')
            ->orderBy('t.tipo_territorio')
            ->orderBy('t.nombre_territorio')
            ->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'nombre' => (string) $t->nombre,
                'tipo' => (string) $t->tipo,
                'padre_id' => $t->padre_id !== null ? (int) $t->padre_id : null,
                'padre' => $t->padre !== null ? (string) $t->padre : null,
            ])
            ->values();
    }

    private function enviosBase(?int $rutaSeleccionada): Builder
    {
        $incidenciasPorEnvio = DB::table('rutas.incidencias')
            ->whereNotNull('envio_id')
            ->selectRaw('envio_id, count(*) as total')
            ->groupBy('envio_id');

        $posicionReportada = DB::table('envios.envio_eventos as ev')
            ->join('envios.estados_envios as se', 'se.id_estado', '=', 'ev.estado_envio_id')
            ->whereColumn('ev.envio_id', 'e.id_envio')
            ->whereNotNull('ev.ubicacion')
            ->where('se.nombre_estado', '<>', 'REGISTRADO')
            ->orderByDesc('ev.ocurrido_en')
            ->orderByDesc('ev.id_evento')
            ->limit(1)
            ->selectRaw('ST_Y(ev.ubicacion::geometry) as lat, ST_X(ev.ubicacion::geometry) as lng');

        $rutaActual = DB::table('rutas.despacho_paradas as p')
            ->join('rutas.despachos as d', 'd.id_despacho', '=', 'p.despacho_id')
            ->join('rutas.estados_despachos as ed', 'ed.id_estado', '=', 'd.estado_despacho_id')
            ->join('territorio.sucursales as su', 'su.id_sucursal', '=', 'd.sucursal_origen_id')
            ->whereColumn('p.envio_id', 'e.id_envio')
            ->when($rutaSeleccionada, fn (Builder $consulta) => $consulta->where('d.id_despacho', $rutaSeleccionada))
            ->orderByDesc('d.fecha')
            ->orderByDesc('d.id_despacho')
            ->limit(1)
            ->selectRaw('
                d.id_despacho,
                d.fecha,
                ed.nombre_estado as estado,
                su.nombre_sucursal as sucursal,
                su.latitud,
                su.longitud,
                p.orden,
                p.hora_estimada,
                p.hora_real
            ');

        return DB::table('envios.envios as e')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->join('territorio.territorios as t', 't.id_territorio', '=', 'e.territorio_destino_id')
            ->leftJoin('territorio.territorios as padre', 'padre.id_territorio', '=', 't.territorio_padre_id')
            ->leftJoinLateral($posicionReportada, 'pos')
            ->leftJoinLateral($rutaActual, 'ruta')
            ->leftJoinSub($incidenciasPorEnvio, 'ie', 'ie.envio_id', '=', 'e.id_envio');
    }

    /** @param array{dias: int, estado: string|null, ruta: int|null, territorio: int|null, territorio_ids: list<int>|null} $filtros */
    private function aplicarFiltros(Builder $consulta, array $filtros, ?CarbonInterface $desde): Builder
    {
        if ($desde !== null) {
            $consulta->where('e.fecha_registro', '>=', $desde);
        }
        if ($filtros['estado'] !== null) {
            $consulta->where('s.nombre_estado', $filtros['estado']);
        }
        if ($filtros['territorio_ids'] !== null) {
            $consulta->whereIn('t.id_territorio', $filtros['territorio_ids']);
        }
        if ($filtros['ruta'] !== null) {
            $consulta->whereExists(fn (Builder $subconsulta) => $subconsulta
                ->selectRaw('1')
                ->from('rutas.despacho_paradas as filtro_parada')
                ->whereColumn('filtro_parada.envio_id', 'e.id_envio')
                ->where('filtro_parada.despacho_id', $filtros['ruta']));
        }

        return $consulta;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, codigo: string, fecha: string|null, estado: string|null, sucursal: string|null, origen: array{lat: float, lng: float}|null, km_planificados: float|null, km_recorridos: float|null, incidencias: int, minutos_retraso: int, paradas: list<array<string, mixed>>}>
     */
    private function rutas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $incidencias = DB::table('rutas.incidencias')
            ->whereNotNull('despacho_id')
            ->selectRaw('despacho_id, count(*) as total, COALESCE(sum(minutos_retraso), 0) as minutos_retraso')
            ->groupBy('despacho_id');
        $incidenciasPorEnvio = DB::table('rutas.incidencias')
            ->whereNotNull('envio_id')
            ->selectRaw('envio_id, count(*) as total')
            ->groupBy('envio_id');
        $posicionReportada = DB::table('envios.envio_eventos as ev')
            ->join('envios.estados_envios as se', 'se.id_estado', '=', 'ev.estado_envio_id')
            ->whereColumn('ev.envio_id', 'e.id_envio')
            ->whereNotNull('ev.ubicacion')
            ->where('se.nombre_estado', '<>', 'REGISTRADO')
            ->orderByDesc('ev.ocurrido_en')
            ->orderByDesc('ev.id_evento')
            ->limit(1)
            ->selectRaw('ST_Y(ev.ubicacion::geometry) as lat, ST_X(ev.ubicacion::geometry) as lng');
        $centroSucursales = DB::table('territorio.sucursales as su')
            ->whereColumn('su.territorio_id', 't.id_territorio')
            ->whereNotNull('su.latitud')
            ->whereNotNull('su.longitud')
            ->selectRaw('AVG(su.latitud)::double precision as lat, AVG(su.longitud)::double precision as lng');
        $centroSucursalesPadre = DB::table('territorio.sucursales as su')
            ->whereColumn('su.territorio_id', 'padre.id_territorio')
            ->whereNotNull('su.latitud')
            ->whereNotNull('su.longitud')
            ->selectRaw('AVG(su.latitud)::double precision as lat, AVG(su.longitud)::double precision as lng');

        $paradasPorRuta = DB::table('rutas.despacho_paradas as p')
            ->join('envios.envios as e', 'e.id_envio', '=', 'p.envio_id')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->join('territorio.territorios as t', 't.id_territorio', '=', 'e.territorio_destino_id')
            ->leftJoin('territorio.territorios as padre', 'padre.id_territorio', '=', 't.territorio_padre_id')
            ->leftJoinLateral($posicionReportada, 'pos')
            ->leftJoinLateral($centroSucursales, 'centro')
            ->leftJoinLateral($centroSucursalesPadre, 'centro_padre')
            ->leftJoinSub($incidenciasPorEnvio, 'ie', 'ie.envio_id', '=', 'e.id_envio')
            ->whereIn('p.despacho_id', $ids)
            ->orderBy('p.despacho_id')
            ->orderBy('p.orden')
            ->addSelect([
                'p.despacho_id',
                'e.id_envio',
                'p.orden',
                'e.codigo_guia',
                't.nombre_territorio as destino',
                'e.direccion_destino as direccion',
                's.nombre_estado as estado_envio',
                'p.hora_estimada',
                'p.hora_real',
                'ie.total as incidencias',
            ])
            ->selectRaw('
                COALESCE(pos.lat, centro.lat, centro_padre.lat) as lat,
                COALESCE(pos.lng, centro.lng, centro_padre.lng) as lng,
                (
                    pos.lat IS NULL
                    AND (centro.lat IS NOT NULL OR centro_padre.lat IS NOT NULL)
                ) as ubicacion_aproximada
            ')
            ->get()
            ->groupBy('despacho_id');

        return array_values(DB::table('rutas.despachos as d')
            ->join('rutas.estados_despachos as ed', 'ed.id_estado', '=', 'd.estado_despacho_id')
            ->join('territorio.sucursales as su', 'su.id_sucursal', '=', 'd.sucursal_origen_id')
            ->leftJoinSub($incidencias, 'inc', 'inc.despacho_id', '=', 'd.id_despacho')
            ->whereIn('d.id_despacho', $ids)
            ->orderByDesc('d.fecha')
            ->orderByDesc('d.id_despacho')
            ->get([
                'd.id_despacho',
                'd.fecha',
                'd.km_planificados',
                'd.km_recorridos',
                'ed.nombre_estado as estado',
                'su.nombre_sucursal as sucursal',
                'su.latitud',
                'su.longitud',
                'inc.total as incidencias',
                'inc.minutos_retraso',
            ])
            ->map(function ($d) use ($paradasPorRuta): array {
                $paradas = array_values($paradasPorRuta->get((int) $d->id_despacho, collect())
                    ->map(fn ($parada) => [
                        'id' => (int) $parada->id_envio,
                        'orden' => (int) $parada->orden,
                        'envio_id' => (int) $parada->id_envio,
                        'codigo_guia' => $parada->codigo_guia,
                        'destino' => $parada->destino,
                        'direccion' => $parada->direccion,
                        'estado_envio' => $parada->estado_envio,
                        'hora_estimada' => $this->iso($parada->hora_estimada),
                        'hora_real' => $this->iso($parada->hora_real),
                        'lat' => $this->numero($parada->lat),
                        'lng' => $this->numero($parada->lng),
                        'ubicacion_aproximada' => (bool) $parada->ubicacion_aproximada,
                        'incidencias' => (int) ($parada->incidencias ?? 0),
                    ])
                    ->all());

                return [
                    'id' => (int) $d->id_despacho,
                    'codigo' => 'DSP-'.str_pad((string) $d->id_despacho, 6, '0', STR_PAD_LEFT),
                    'fecha' => $d->fecha !== null ? (string) $d->fecha : null,
                    'estado' => $d->estado !== null ? (string) $d->estado : null,
                    'sucursal' => $d->sucursal !== null ? (string) $d->sucursal : null,
                    'origen' => $this->punto($d->latitud, $d->longitud),
                    'km_planificados' => $d->km_planificados !== null ? (float) $d->km_planificados : null,
                    'km_recorridos' => $d->km_recorridos !== null ? (float) $d->km_recorridos : null,
                    'incidencias' => (int) ($d->incidencias ?? 0),
                    'minutos_retraso' => (int) ($d->minutos_retraso ?? 0),
                    'paradas' => $paradas,
                ];
            })
            ->all());
    }

    /** @return Collection<int, array{id: int, codigo: string, fecha: string|null, estado: string|null}> */
    private function opcionesRuta(?CarbonInterface $desde): Collection
    {
        return DB::table('rutas.despachos as d')
            ->join('rutas.estados_despachos as ed', 'ed.id_estado', '=', 'd.estado_despacho_id')
            ->when($desde, fn (Builder $consulta) => $consulta->whereExists(
                fn (Builder $envios) => $envios
                    ->selectRaw('1')
                    ->from('rutas.despacho_paradas as p')
                    ->join('envios.envios as e', 'e.id_envio', '=', 'p.envio_id')
                    ->whereColumn('p.despacho_id', 'd.id_despacho')
                    ->where('e.fecha_registro', '>=', $desde)
            ))
            ->orderByDesc('d.fecha')
            ->orderByDesc('d.id_despacho')
            ->get([
                'd.id_despacho as id',
                'd.fecha',
                'ed.nombre_estado as estado',
            ])
            ->map(fn ($d) => [
                'id' => (int) $d->id,
                'codigo' => 'DSP-'.str_pad((string) $d->id, 6, '0', STR_PAD_LEFT),
                'fecha' => $d->fecha !== null ? (string) $d->fecha : null,
                'estado' => $d->estado !== null ? (string) $d->estado : null,
            ])
            ->values();
    }

    /**
     * @param  list<int>  $envioIds
     * @param  list<int>  $rutaIds
     */
    private function incidenciasFiltradas(array $envioIds, array $rutaIds): int
    {
        if ($envioIds === [] && $rutaIds === []) {
            return 0;
        }

        return (int) DB::table('rutas.incidencias')
            ->where(function (Builder $consulta) use ($envioIds, $rutaIds): void {
                if ($envioIds !== []) {
                    $consulta->whereIn('envio_id', $envioIds);
                }
                if ($rutaIds !== []) {
                    $metodo = $envioIds !== [] ? 'orWhereIn' : 'whereIn';
                    $consulta->{$metodo}('despacho_id', $rutaIds);
                }
            })
            ->count();
    }

    /** @return list<array<string, mixed>> */
    private function sucursales(): array
    {
        return array_values(DB::table('territorio.sucursales')
            ->orderBy('nombre_sucursal')
            ->get(['id_sucursal', 'codigo', 'nombre_sucursal', 'direccion', 'latitud', 'longitud', 'activa'])
            ->map(fn ($s) => [
                'id' => (int) $s->id_sucursal,
                'codigo' => $s->codigo,
                'nombre' => $s->nombre_sucursal,
                'direccion' => $s->direccion,
                'activa' => (bool) $s->activa,
                'punto' => $this->punto($s->latitud, $s->longitud),
            ])
            ->all());
    }

    /** @return array{lat: float, lng: float}|null */
    private function punto(mixed $lat, mixed $lng): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    private function numero(mixed $valor): ?float
    {
        return $valor !== null ? (float) $valor : null;
    }

    private function iso(?string $valor): ?string
    {
        return $valor ? Carbon::parse($valor)->toIso8601String() : null;
    }
}
