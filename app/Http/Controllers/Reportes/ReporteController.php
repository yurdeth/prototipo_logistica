<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\TipoIncidencia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes operativos: envíos por vencer, despachos según el compromiso de
 * sus envíos e incidencias con su retraso. Todo se calcula sobre la operación
 * vigente; la clasificación de plazo compara la fecha comprometida contra ahora.
 *
 * @phpstan-type Filtros array{plazo: 'vencido'|'vence_pronto'|'en_plazo'|null, tipo: int|null, dias: int, tab: 'envios'|'despachos'|'incidencias'|null}
 */
class ReporteController extends Controller
{
    /** Tamaño de página de los listados paginados (envíos e incidencias). */
    private const TAM_PAGINA = 20;

    /** Un envío que vence dentro de este margen se reporta como "vence pronto". */
    private const HORAS_VENCE_PRONTO = 48;

    /** Cuántos despachos activos muestra el reporte, del más urgente al resto. */
    private const LIMITE_DESPACHOS = 50;

    public function index(Request $request): Response
    {
        $filtros = self::filtros($request);

        return Inertia::render('reportes/ReportesIndex', [
            'filtros' => $filtros,
            'kpis' => self::kpis($filtros),
            'envios' => self::enviosPorVencer($filtros),
            'despachos' => self::despachosPorPlazo($filtros),
            'incidencias' => self::incidencias($filtros, $request),
            'tipos' => TipoIncidencia::query()
                ->orderBy('id_tipo')
                ->get(['id_tipo as id', 'nombre_tipo as nombre']),
        ]);
    }

    /** Filtros compartidos por el índice y el exporte. `tab` solo lo usan los exportes: indica la sección pedida (null = todas).
     * @return Filtros
     */
    private function filtros(Request $request): array
    {
        $validos = $request->validate([
            'plazo' => ['nullable', Rule::in(['vencido', 'vence_pronto', 'en_plazo'])],
            'tipo' => ['nullable', 'integer', 'min:1'],
            'dias' => ['nullable', 'integer', 'in:0,7,30'],
            'tab' => ['nullable', Rule::in(['envios', 'despachos', 'incidencias'])],
        ]);

        return [
            'plazo' => $validos['plazo'] ?? null,
            'tipo' => isset($validos['tipo']) ? (int) $validos['tipo'] : null,
            'dias' => (int) ($validos['dias'] ?? 0),
            'tab' => $validos['tab'] ?? null,
        ];
    }

    /** Código de presentación DSP-0001 (la tabla no guarda código). */
    private static function codigoDespacho(int|string|null $id): string
    {
        return $id !== null && $id !== '' ? 'DSP-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT) : '';
    }

    /** Marca de tiempo en la hora local de presentación. */
    private static function iso(mixed $valor): string
    {
        return $valor !== null ? Carbon::parse($valor)->setTimezone('America/El_Salvador')->format('Y-m-d H:i') : '';
    }

    /*
     * Condiciones de plazo sobre la fecha comprometida; compartidas por los
     * envíos, los despachos y los KPI para que los tres reportes midan igual.
     */
    private const SQL_VENCIDO = 'e.fecha_comprometida < now()';

    private const SQL_VENCE_PRONTO =
        "e.fecha_comprometida >= now() AND e.fecha_comprometida <= now() + interval '".self::HORAS_VENCE_PRONTO." hours'";

    private const SQL_EN_PLAZO = "e.fecha_comprometida > now() + interval '".self::HORAS_VENCE_PRONTO." hours'";

    private const SQL_ETIQUETA_PLAZO =
        "CASE
            WHEN e.fecha_comprometida < now() THEN 'vencido'
            WHEN e.fecha_comprometida <= now() + interval '".self::HORAS_VENCE_PRONTO." hours' THEN 'vence_pronto'
            ELSE 'en_plazo'
        END";

    /** Envíos abiertos: la base que comparten los KPI y el listado por vencer. */
    private static function enviosAbiertos(): Builder
    {
        return DB::table('envios.envios as e')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->join('territorio.territorios as t', 't.id_territorio', '=', 'e.territorio_destino_id')
            ->whereRaw("s.nombre_estado NOT IN ('ENTREGADO', 'DEVUELTO', 'FALLIDO')");
    }

    /** @param Filtros $filtros
     * @return array{vencidos: int, vence_pronto: int, en_plazo: int, incidencias: int, minutos_retraso: int}
     */
    private static function kpis(array $filtros): array
    {
        $incidencias = self::consultaIncidencias($filtros['dias']);

        return [
            'vencidos' => (int) (clone self::enviosAbiertos())->whereRaw(self::SQL_VENCIDO)->count(),
            'vence_pronto' => (int) (clone self::enviosAbiertos())->whereRaw(self::SQL_VENCE_PRONTO)->count(),
            'en_plazo' => (int) (clone self::enviosAbiertos())->whereRaw(self::SQL_EN_PLAZO)->count(),
            'incidencias' => (int) (clone $incidencias)->count(),
            'minutos_retraso' => (int) (clone $incidencias)->sum('i.minutos_retraso'),
        ];
    }

    /** Consulta del listado de envíos abiertos, del compromiso más apretado al más holgado.
     * @param  Filtros  $filtros
     */
    private static function consultaEnviosPorVencer(array $filtros): Builder
    {
        return self::enviosAbiertos()
            ->when($filtros['plazo'], function (Builder $consulta) use ($filtros): void {
                match ($filtros['plazo']) {
                    'vencido' => $consulta->whereRaw(self::SQL_VENCIDO),
                    'vence_pronto' => $consulta->whereRaw(self::SQL_VENCE_PRONTO),
                    default => $consulta->whereRaw(self::SQL_EN_PLAZO),
                };
            })
            ->orderBy('e.fecha_comprometida')
            ->selectRaw('
                e.id_envio,
                e.codigo_guia,
                t.nombre_territorio as destino,
                s.nombre_estado as estado,
                e.prioridad,
                e.fecha_comprometida,
                '.self::SQL_ETIQUETA_PLAZO.' as plazo
            ');
    }

    /** Listado paginado de envíos abiertos, del compromiso más apretado al más holgado.
     * @param  Filtros  $filtros
     * @return array{data: list<array<string, mixed>>, pagina: int, ultima: int, total: int}
     */
    private static function enviosPorVencer(array $filtros): array
    {
        $paginado = self::consultaEnviosPorVencer($filtros)->paginate(self::TAM_PAGINA);

        return [
            'data' => array_values($paginado->through(fn ($e): array => [
                'id_envio' => (int) $e->id_envio,
                'codigo_guia' => $e->codigo_guia,
                'destino' => $e->destino,
                'estado' => $e->estado,
                'prioridad' => $e->prioridad,
                'fecha_comprometida' => Carbon::parse($e->fecha_comprometida)->toISOString(),
                'plazo' => $e->plazo,
            ])->all()),
            'pagina' => $paginado->currentPage(),
            'ultima' => $paginado->lastPage(),
            'total' => $paginado->total(),
        ];
    }

    /**
     * Despachos sin cerrar, con el compromiso de sus envíos agregado: cuántas
     * paradas van vencidas, por vencer o en plazo. Del más urgente al resto.
     *
     * @param  Filtros  $filtros
     * @return list<array<string, mixed>>
     */
    private static function despachosPorPlazo(array $filtros): array
    {
        $medidas = DB::table('rutas.despacho_paradas as p')
            ->join('envios.envios as e', 'e.id_envio', '=', 'p.envio_id')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->whereRaw("s.nombre_estado NOT IN ('ENTREGADO', 'DEVUELTO', 'FALLIDO')")
            ->groupBy('p.despacho_id')
            ->selectRaw('
                p.despacho_id,
                count(*) as total,
                count(*) FILTER (WHERE '.self::SQL_VENCIDO.') as vencidos,
                count(*) FILTER (WHERE '.self::SQL_VENCE_PRONTO.') as vence_pronto,
                count(*) FILTER (WHERE '.self::SQL_EN_PLAZO.') as en_plazo
            ');

        $consulta = DB::table('rutas.despachos as d')
            ->join('rutas.estados_despachos as ed', 'ed.id_estado', '=', 'd.estado_despacho_id')
            ->join('territorio.sucursales as su', 'su.id_sucursal', '=', 'd.sucursal_origen_id')
            ->leftJoinSub($medidas, 'm', 'm.despacho_id', '=', 'd.id_despacho')
            ->whereIn('ed.nombre_estado', ['PLANIFICADO', 'EN_CURSO'])
            ->when($filtros['plazo'], function (Builder $consulta) use ($filtros): void {
                // El despacho entra en la situación si alguna de sus paradas la tiene;
                // "vence pronto" y "en plazo" exigen que no haya una situación peor.
                match ($filtros['plazo']) {
                    'vencido' => $consulta->where('m.vencidos', '>', 0),
                    'vence_pronto' => $consulta
                        ->where('m.vence_pronto', '>', 0)
                        ->whereRaw('COALESCE(m.vencidos, 0) = 0'),
                    default => $consulta
                        ->where('m.en_plazo', '>', 0)
                        ->whereRaw('COALESCE(m.vencidos, 0) = 0')
                        ->whereRaw('COALESCE(m.vence_pronto, 0) = 0'),
                };
            })
            ->orderByDesc('m.vencidos')
            ->orderByDesc('m.vence_pronto')
            ->orderByDesc('d.fecha')
            ->orderByDesc('d.id_despacho')
            ->limit(self::LIMITE_DESPACHOS)
            ->get([
                'd.id_despacho',
                'd.fecha',
                'ed.nombre_estado as estado',
                'su.nombre_sucursal as sucursal',
                'm.total',
                'm.vencidos',
                'm.vence_pronto',
                'm.en_plazo',
            ]);

        return array_values($consulta->map(fn ($d): array => [
            'id_despacho' => (int) $d->id_despacho,
            'codigo' => 'DSP-'.str_pad((string) $d->id_despacho, 6, '0', STR_PAD_LEFT),
            'fecha' => (string) $d->fecha,
            'estado' => (string) $d->estado,
            'sucursal' => (string) $d->sucursal,
            'total' => (int) ($d->total ?? 0),
            'vencidos' => (int) ($d->vencidos ?? 0),
            'vence_pronto' => (int) ($d->vence_pronto ?? 0),
            'en_plazo' => (int) ($d->en_plazo ?? 0),
        ])->all());
    }

    /** Incidencias del período; con tipo, filtra al detalle y al cuadre por tipo. */
    private static function consultaIncidencias(int $dias): Builder
    {
        $desde = $dias > 0 ? now()->startOfDay()->subDays($dias - 1) : null;

        return DB::table('rutas.incidencias as i')
            ->join('rutas.tipos_incidencias as ti', 'ti.id_tipo', '=', 'i.tipo_incidencia_id')
            ->leftJoin('rutas.despachos as d', 'd.id_despacho', '=', 'i.despacho_id')
            ->leftJoin('envios.envios as e', 'e.id_envio', '=', 'i.envio_id')
            ->when($desde, fn (Builder $consulta) => $consulta->where('i.ocurrio_en', '>=', $desde));
    }

    /** Detalle de incidencias con su contexto, del más reciente al más viejo.
     * @param  Filtros  $filtros
     */
    private static function consultaDetalleIncidencias(array $filtros): Builder
    {
        return self::consultaIncidencias($filtros['dias'])
            ->when($filtros['tipo'], fn (Builder $consulta) => $consulta->where('i.tipo_incidencia_id', $filtros['tipo']))
            ->orderByDesc('i.ocurrio_en')
            ->selectRaw('
                i.id_incidencia,
                ti.nombre_tipo as tipo,
                d.id_despacho as despacho_id,
                e.codigo_guia as guia,
                i.minutos_retraso,
                i.descripcion,
                i.ocurrio_en
            ');
    }

    /** Cuadre de incidencias por tipo para el período; ignora el filtro de tipo.
     * @return list<array{id: int, tipo: string, total: int, minutos: int}>
     */
    private static function cuadrePorTipo(int $dias): array
    {
        return array_values(self::consultaIncidencias($dias)
            ->groupBy('ti.id_tipo', 'ti.nombre_tipo')
            ->orderByDesc('total')
            ->get([
                'ti.id_tipo as id',
                'ti.nombre_tipo as tipo',
                DB::raw('count(*) as total'),
                DB::raw('COALESCE(sum(i.minutos_retraso), 0) as minutos'),
            ])
            ->map(fn ($t): array => [
                'id' => (int) $t->id,
                'tipo' => $t->tipo,
                'total' => (int) $t->total,
                'minutos' => (int) $t->minutos,
            ])
            ->all());
    }

    /** Detalle paginado de incidencias y su cuadre por tipo para el mismo período.
     * @param  Filtros  $filtros
     * @return array{data: list<array<string, mixed>>, pagina: int, ultima: int, total: int, por_tipo: list<array{id: int, tipo: string, total: int, minutos: int}>}
     */
    private static function incidencias(array $filtros, Request $request): array
    {
        $detalle = self::consultaDetalleIncidencias($filtros)->paginate(self::TAM_PAGINA);

        $porTipo = self::cuadrePorTipo($filtros['dias']);

        return [
            'data' => array_values($detalle->through(fn ($i): array => [
                'id' => (int) $i->id_incidencia,
                'tipo' => $i->tipo,
                'despacho' => $i->despacho_id !== null
                    ? 'DSP-'.str_pad((string) $i->despacho_id, 6, '0', STR_PAD_LEFT)
                    : null,
                'guia' => $i->guia,
                'minutos_retraso' => (int) $i->minutos_retraso,
                'descripcion' => $i->descripcion,
                'ocurrio_en' => Carbon::parse($i->ocurrio_en)->toISOString(),
            ])->all()),
            'pagina' => $detalle->currentPage(),
            'ultima' => $detalle->lastPage(),
            'total' => $detalle->total(),
            'por_tipo' => $porTipo,
        ];
    }

    /** CSV del reporte; con `tab`, exporta únicamente esa sección. */
    public function exportar(Request $request): StreamedResponse
    {
        $filtros = self::filtros($request);
        $secciones = match ($filtros['tab']) {
            'envios' => ['envios'],
            'despachos' => ['despachos'],
            'incidencias' => ['incidencias'],
            default => ['envios', 'despachos', 'incidencias'],
        };

        $envios = in_array('envios', $secciones, true)
            ? self::consultaEnviosPorVencer($filtros)->get()
            : collect();
        $despachos = in_array('despachos', $secciones, true)
            ? self::despachosPorPlazo($filtros)
            : [];
        $incidencias = in_array('incidencias', $secciones, true)
            ? self::consultaDetalleIncidencias($filtros)->get()
            : collect();

        // Evita que Excel interprete como fórmula un texto que empieza con = + - @
        $seguro = fn ($v) => is_string($v) && $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;

        return response()->streamDownload(function () use ($envios, $despachos, $incidencias, $seguro, $secciones): void {
            $salida = fopen('php://output', 'w');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien los acentos

            if (in_array('envios', $secciones, true)) {
                fputcsv($salida, ['ENVÍOS POR VENCER']);
                fputcsv($salida, ['Guía', 'Destino', 'Estado', 'Prioridad', 'Compromiso', 'Situación']);
                foreach ($envios as $e) {
                    fputcsv($salida, [
                        $e->codigo_guia,
                        $e->destino,
                        $e->estado,
                        $e->prioridad,
                        self::iso($e->fecha_comprometida),
                        $e->plazo,
                    ]);
                }
                fputcsv($salida, []);
            }

            if (in_array('despachos', $secciones, true)) {
                fputcsv($salida, ['DESPACHOS POR PLAZO (SIN CERRAR)']);
                fputcsv($salida, ['Despacho', 'Fecha', 'Sucursal', 'Estado', 'Paradas', 'Vencidas', 'Por vencer', 'En plazo']);
                foreach ($despachos as $d) {
                    fputcsv($salida, [
                        $d['codigo'],
                        $d['fecha'],
                        $d['sucursal'],
                        $d['estado'],
                        $d['total'],
                        $d['vencidos'],
                        $d['vence_pronto'],
                        $d['en_plazo'],
                    ]);
                }
                fputcsv($salida, []);
            }

            if (in_array('incidencias', $secciones, true)) {
                fputcsv($salida, ['INCIDENCIAS']);
                fputcsv($salida, ['Tipo', 'Despacho', 'Envío', 'Retraso (min)', 'Fecha', 'Descripción']);
                foreach ($incidencias as $i) {
                    fputcsv($salida, [
                        $seguro(strtolower(str_replace('_', ' ', (string) $i->tipo))),
                        self::codigoDespacho($i->despacho_id),
                        $i->guia ?? '',
                        (int) $i->minutos_retraso,
                        self::iso($i->ocurrio_en),
                        $seguro($i->descripcion ?? ''),
                    ]);
                }
            }

            fclose($salida);
        }, 'reportes-'.($filtros['tab'] !== null ? $filtros['tab'].'-' : '').now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** PDF horizontal del reporte; con `tab`, incluye únicamente esa sección. */
    public function exportarPdf(Request $request): \Illuminate\Http\Response
    {
        $filtros = self::filtros($request);
        $secciones = match ($filtros['tab']) {
            'envios' => ['envios'],
            'despachos' => ['despachos'],
            'incidencias' => ['incidencias'],
            default => ['envios', 'despachos', 'incidencias'],
        };

        $pdf = Pdf::loadView('reportes.pdf', [
            'secciones' => $secciones,
            'generado' => now(),
            'etiquetaPlazo' => match ($filtros['plazo']) {
                'vencido' => 'Vencidos',
                'vence_pronto' => 'Vencen pronto',
                'en_plazo' => 'En plazo',
                default => 'Todos',
            },
            'etiquetaTipo' => $filtros['tipo'] !== null
                ? strtolower(str_replace('_', ' ', (string) TipoIncidencia::query()->whereKey($filtros['tipo'])->value('nombre_tipo')))
                : 'Todos',
            'etiquetaDias' => match ($filtros['dias']) {
                7 => 'Últimos 7 días',
                30 => 'Últimos 30 días',
                default => 'Todo el historial',
            },
            'envios' => in_array('envios', $secciones, true)
                ? self::consultaEnviosPorVencer($filtros)->get()
                : collect(),
            'despachos' => in_array('despachos', $secciones, true)
                ? self::despachosPorPlazo($filtros)
                : [],
            'incidencias' => in_array('incidencias', $secciones, true)
                ? self::consultaDetalleIncidencias($filtros)->get()
                : collect(),
            'porTipo' => self::cuadrePorTipo($filtros['dias']),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('reportes-'.($filtros['tab'] !== null ? $filtros['tab'].'-' : '').now()->format('Ymd-His').'.pdf');
    }
}
