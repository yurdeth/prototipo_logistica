<?php

namespace App\Http\Controllers\Rutas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rutas\GuardarZonaRequest;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mapa de rutas: lee los despachos reales (rutas.*) sin modificar el listado actual.
 *
 * Las paradas solo se ubican cuando un evento del envío tiene coordenadas reportadas.
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

    /** Área permitida para una zona dibujada (m²): evita trazos accidentales o enormes. */
    private const AREA_MIN = 1_000;

    private const AREA_MAX = 25_000_000_000;

    /* ===================== PÁGINA ===================== */

    public function index(): Response
    {
        return Inertia::render('rutas/RutasMapa', [
            'territorios' => $this->territorios($this->tieneColumnaZona()),
        ]);
    }

    /* ===================== DATOS (JSON) ===================== */

    public function datos(Request $request): JsonResponse
    {
        $validos = $request->validate([
            'dias' => ['nullable', 'integer', 'between:0,365'],
            'estado' => ['nullable', 'in:'.implode(',', self::ESTADOS_ENVIO)],
            'ruta' => ['nullable', 'integer', 'min:1'],
            'territorio' => ['nullable', 'integer', 'min:1'],
            'zona' => ['nullable', 'integer', 'min:1'],
        ]);
        $filtros = [
            'dias' => (int) ($validos['dias'] ?? 7),
            'estado' => $validos['estado'] ?? null,
            'ruta' => isset($validos['ruta']) ? (int) $validos['ruta'] : null,
            'territorio' => isset($validos['territorio']) ? (int) $validos['territorio'] : null,
            'zona' => isset($validos['zona']) ? (int) $validos['zona'] : null,
        ];
        $tieneZona = $this->tieneColumnaZona();

        if ($filtros['zona'] !== null) {
            abort_unless($tieneZona, 422, 'La base de datos no tiene zonas geográficas configuradas.');
            abort_unless(
                DB::table('territorio.territorios')
                    ->where('id_territorio', $filtros['zona'])
                    ->whereNotNull('zona')
                    ->exists(),
                422,
                'La zona seleccionada no existe.'
            );
        }
        $filtros['territorio_ids'] = $filtros['territorio'] !== null
            ? $this->territoriosDescendientes($filtros['territorio'])
            : null;
        $filtros['zona_ids'] = $filtros['zona'] !== null
            ? $this->territoriosDescendientes($filtros['zona'])
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

        $idsRuta = $envios->pluck('ruta_id')->filter()->unique()->values()->all();
        $rutas = $this->rutas($idsRuta, $envios);
        $idsEnvio = $envios->pluck('id')->all();
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
            'zonas_soportadas' => $tieneZona,
            'catalogos' => [
                'estados' => self::ESTADOS_ENVIO,
                'rutas' => $this->opcionesRuta($desde),
                'territorios' => $this->territorios($tieneZona),
                'zonas' => $this->zonas($tieneZona),
            ],
            'envios' => $envios,
            'rutas' => $rutas,
            'sucursales' => $this->sucursales(),
        ]);
    }

    /* ===================== ZONAS ===================== */

    public function guardarZona(GuardarZonaRequest $request, int $territorio): RedirectResponse
    {
        abort_unless($this->tieneColumnaZona(), 404, 'La base de datos no tiene soporte para zonas geográficas.');
        abort_unless(DB::table('territorio.territorios')->where('id_territorio', $territorio)->exists(), 404);

        $geojson = json_encode($request->geometriaLimpia(), JSON_THROW_ON_ERROR);

        $fila = DB::selectOne(
            'SELECT (CASE WHEN ST_IsValid(g) THEN 1 ELSE 0 END) AS valida, ST_Area(g::geography) AS area
             FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g) q',
            [$geojson]
        );

        if (! $fila || (int) $fila->valida !== 1) {
            return back()->withErrors(['zona' => 'La zona no es válida: el contorno se cruza consigo mismo.']);
        }
        if ((float) $fila->area < self::AREA_MIN) {
            return back()->withErrors(['zona' => 'La zona es demasiado pequeña. Dibújala más grande.']);
        }
        if ((float) $fila->area > self::AREA_MAX) {
            return back()->withErrors(['zona' => 'La zona es demasiado grande para un territorio.']);
        }

        DB::update(
            'UPDATE territorio.territorios
             SET zona = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography
             WHERE id_territorio = ?',
            [$geojson, $territorio]
        );

        return back()->with('success', 'Zona guardada correctamente.');
    }

    /* ===================== CONSULTAS DE APOYO ===================== */

    private function tieneColumnaZona(): bool
    {
        return Schema::hasColumn('territorio.territorios', 'zona');
    }

    /** @return list<int> */
    private function territoriosDescendientes(int $territorio): array
    {
        return collect(DB::select(
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
            ->all();
    }

    /** @return Collection<int, array{id: int, nombre: string, tipo: string, padre_id: int|null, padre: string|null, tiene_zona: bool}> */
    private function territorios(bool $tieneZona): Collection
    {
        $tieneZonaSelect = $tieneZona
            ? 'CASE WHEN t.zona IS NULL THEN false ELSE true END as tiene_zona'
            : 'false as tiene_zona';

        return DB::table('territorio.territorios as t')
            ->leftJoin('territorio.territorios as padre', 'padre.id_territorio', '=', 't.territorio_padre_id')
            ->selectRaw("
                t.id_territorio as id,
                t.nombre_territorio as nombre,
                t.tipo_territorio as tipo,
                t.territorio_padre_id as padre_id,
                padre.nombre_territorio as padre,
                {$tieneZonaSelect}
            ")
            ->orderBy('t.tipo_territorio')
            ->orderBy('t.nombre_territorio')
            ->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'nombre' => $t->nombre,
                'tipo' => $t->tipo,
                'padre_id' => $t->padre_id !== null ? (int) $t->padre_id : null,
                'padre' => $t->padre,
                'tiene_zona' => (bool) $t->tiene_zona,
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

    /** @param array{dias: int, estado: string|null, ruta: int|null, territorio: int|null, zona: int|null, territorio_ids: list<int>|null, zona_ids: list<int>|null} $filtros */
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
        if ($filtros['zona_ids'] !== null) {
            $consulta->whereIn('t.id_territorio', $filtros['zona_ids']);
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
     * @param  Collection<int, array<string, mixed>>  $envios
     * @return Collection<int, array<string, mixed>>
     */
    private function rutas(array $ids, Collection $envios): Collection
    {
        if ($ids === []) {
            return collect();
        }

        $incidencias = DB::table('rutas.incidencias')
            ->whereNotNull('despacho_id')
            ->selectRaw('despacho_id, count(*) as total, COALESCE(sum(minutos_retraso), 0) as minutos_retraso')
            ->groupBy('despacho_id');
        $enviosPorRuta = $envios
            ->filter(fn (array $envio) => $envio['ruta_id'] !== null)
            ->groupBy('ruta_id');

        return DB::table('rutas.despachos as d')
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
            ->map(function ($d) use ($enviosPorRuta): array {
                $paradas = $enviosPorRuta->get((int) $d->id_despacho, collect())
                    ->sortBy('orden')
                    ->map(fn (array $envio) => [
                        'id' => $envio['id'],
                        'orden' => $envio['orden'],
                        'envio_id' => $envio['id'],
                        'codigo_guia' => $envio['codigo_guia'],
                        'destino' => $envio['territorio'],
                        'direccion' => $envio['direccion'],
                        'estado_envio' => $envio['estado'],
                        'hora_estimada' => $envio['hora_estimada'],
                        'hora_real' => $envio['hora_real'],
                        'lat' => $envio['lat'],
                        'lng' => $envio['lng'],
                    ])
                    ->values();

                return [
                    'id' => (int) $d->id_despacho,
                    'codigo' => 'DSP-'.str_pad((string) $d->id_despacho, 6, '0', STR_PAD_LEFT),
                    'fecha' => $d->fecha,
                    'estado' => $d->estado,
                    'sucursal' => $d->sucursal,
                    'origen' => $this->punto($d->latitud, $d->longitud),
                    'km_planificados' => $d->km_planificados !== null ? (float) $d->km_planificados : null,
                    'km_recorridos' => $d->km_recorridos !== null ? (float) $d->km_recorridos : null,
                    'incidencias' => (int) ($d->incidencias ?? 0),
                    'minutos_retraso' => (int) ($d->minutos_retraso ?? 0),
                    'paradas' => $paradas,
                ];
            })
            ->values();
    }

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
                'fecha' => $d->fecha,
                'estado' => $d->estado,
            ])
            ->values();
    }

    /** @param list<int> $envioIds @param list<int> $rutaIds */
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
        return DB::table('territorio.sucursales')
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
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function zonas(bool $tieneZona): array
    {
        if (! $tieneZona) {
            return [];
        }

        return DB::table('territorio.territorios')
            ->whereNotNull('zona')
            ->orderBy('nombre_territorio')
            ->selectRaw('id_territorio as id, nombre_territorio as nombre, tipo_territorio as tipo,
                ST_AsGeoJSON(zona::geometry, 6) as geojson')
            ->get()
            ->map(fn ($z) => [
                'id' => (int) $z->id,
                'nombre' => $z->nombre,
                'tipo' => $z->tipo,
                'geometry' => json_decode((string) $z->geojson, true),
            ])
            ->values()
            ->all();
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
