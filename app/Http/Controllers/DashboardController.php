<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Envios\EnvioController;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel principal: indicadores calculados en la base con los mismos criterios
 * (SLA, incidencias, estados) que la página de Envíos.
 */
class DashboardController extends Controller
{
    /** Envíos fallidos o devueltos en los últimos 7 días (siguen visibles como incidencia). */
    private const FALLOS_RECIENTES = "s.nombre_estado IN ('FALLIDO','DEVUELTO') AND e.updated_at >= now() - interval '7 days'";

    public function __invoke(): Response
    {
        $ahora = now();
        $hace30 = $ahora->copy()->subDays(30);
        $hace60 = $ahora->copy()->subDays(60);

        // Envíos por estado actual (todo el historial)
        $porEstado = $this->base()
            ->selectRaw('s.nombre_estado as estado, count(*) as total')
            ->groupBy('s.nombre_estado')
            ->pluck('total', 'estado');

        // Envíos abiertos que ya vencieron o vencen en las próximas 24 horas
        $alertas = $this->base()->selectRaw(
            'count(*) FILTER (WHERE '.EnvioController::ABIERTO.' AND e.fecha_comprometida < now()) as vencidos,
            count(*) FILTER (WHERE '.EnvioController::ABIERTO." AND e.fecha_comprometida >= now()
                             AND e.fecha_comprometida < now() + interval '24 hours') as en_riesgo,
            count(*) FILTER (WHERE ".self::FALLOS_RECIENTES.') as fallos'
        )->first();

        // Detalle de lo que requiere atención, de más a menos grave: vencidos (el más atrasado
        // primero), fallos recientes y por vencer en 24 h (el más próximo primero).
        $incidencias = $this->base()
            ->whereRaw('(('.EnvioController::ABIERTO." AND e.fecha_comprometida < now() + interval '24 hours') OR (".self::FALLOS_RECIENTES.'))')
            ->selectRaw("e.id_envio, e.codigo_guia, r.nombre as cliente, t.nombre_territorio as destino,
                s.nombre_estado as estado, e.fecha_comprometida,
                CASE WHEN s.nombre_estado IN ('FALLIDO','DEVUELTO') THEN 'FALLO'
                     WHEN e.fecha_comprometida < now() THEN 'VENCIDO'
                     ELSE 'EN_RIESGO' END as tipo,
                extract(epoch FROM (now() - e.fecha_comprometida)) / 3600 as horas")
            ->orderByRaw("CASE WHEN s.nombre_estado IN ('FALLIDO','DEVUELTO') THEN 2
                              WHEN e.fecha_comprometida < now() THEN 1
                              ELSE 3 END")
            ->orderBy('e.fecha_comprometida')
            ->limit(6)
            ->get()
            ->map(fn ($e) => [
                'id_envio' => $e->id_envio,
                'codigo_guia' => $e->codigo_guia,
                'cliente' => $e->cliente,
                'destino' => $e->destino,
                'estado' => $e->estado,
                'fecha_comprometida' => Carbon::parse($e->fecha_comprometida)->toIso8601String(),
                'tipo' => $e->tipo,
                'horas' => round(abs((float) $e->horas), 1),
            ])
            ->values();

        // Registros de los últimos 6 meses (el mes en curso incluido)
        $inicio = $ahora->copy()->startOfMonth()->subMonthsNoOverflow(5);
        $porMes = $this->base()
            ->where('e.fecha_registro', '>=', $inicio)
            ->selectRaw("to_char(e.fecha_registro, 'YYYY-MM') as mes, count(*) as total")
            ->groupByRaw("to_char(e.fecha_registro, 'YYYY-MM')")
            ->pluck('total', 'mes');

        $mensual = collect(range(0, 5))->map(function (int $i) use ($inicio, $porMes): array {
            $mes = $inicio->copy()->addMonthsNoOverflow($i);

            return [
                'label' => ucfirst($mes->locale('es')->translatedFormat('M')),
                'total' => (int) ($porMes[$mes->format('Y-m')] ?? 0),
            ];
        })->values();

        // Cumplimiento de entrega por sucursal de origen (últimos 30 días)
        $porSucursal = $this->base()
            ->join('territorio.sucursales as su', 'su.id_sucursal', '=', 'e.sucursal_origen_id')
            ->where('e.fecha_registro', '>=', $hace30)
            ->selectRaw("su.nombre_sucursal as sucursal, count(*) as total,
                count(*) FILTER (WHERE s.nombre_estado IN ('ENTREGADO','DEVUELTO','FALLIDO')) as cerrados,
                count(*) FILTER (WHERE s.nombre_estado = 'ENTREGADO' AND fe.fecha <= e.fecha_comprometida) as a_tiempo")
            ->groupBy('su.id_sucursal', 'su.nombre_sucursal')
            ->orderBy('su.nombre_sucursal')
            ->get()
            ->map(fn ($f) => [
                'sucursal' => $f->sucursal,
                'total' => (int) $f->total,
                'cerrados' => (int) $f->cerrados,
                'a_tiempo' => (int) $f->a_tiempo,
            ])
            ->values();

        $recientes = $this->base()
            ->select([
                'e.id_envio',
                'e.codigo_guia',
                't.nombre_territorio as destino',
                'r.nombre as cliente',
                's.nombre_estado as estado',
                'e.fecha_comprometida',
            ])
            ->orderByDesc('e.fecha_registro')
            ->orderByDesc('e.id_envio')
            ->limit(6)
            ->get()
            ->map(fn ($e) => [
                'id_envio' => $e->id_envio,
                'codigo_guia' => $e->codigo_guia,
                'destino' => $e->destino,
                'cliente' => $e->cliente,
                'estado' => $e->estado,
                'fecha_comprometida' => Carbon::parse($e->fecha_comprometida)->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Dashboard', [
            // Últimos 30 días frente a los 30 anteriores (misma duración: comparación justa)
            'kpis' => [
                'actual' => $this->ventana($hace30),
                'previo' => $this->ventana($hace60, $hace30),
            ],
            'alertas' => [
                'vencidos' => (int) $alertas->vencidos,
                'en_riesgo' => (int) $alertas->en_riesgo,
                'fallos' => (int) $alertas->fallos,
            ],
            'incidencias' => $incidencias,
            'mensual' => $mensual,
            'sucursales' => $porSucursal,
            'estados' => collect(EnvioController::ESTADOS)
                ->map(fn (string $estado) => ['estado' => $estado, 'total' => (int) ($porEstado[$estado] ?? 0)])
                ->values(),
            'recientes' => $recientes,
            'permisos' => ['registrar' => Gate::allows('envios.registrar')],
        ]);
    }

    /**
     * Métricas de los envíos registrados en una ventana de tiempo.
     *
     * @return array{total: int, cerrados: int, a_tiempo: int, horas: float|null, incidencias: int}
     */
    private function ventana(CarbonInterface $desde, ?CarbonInterface $hasta = null): array
    {
        $consulta = $this->base()->where('e.fecha_registro', '>=', $desde);

        if ($hasta !== null) {
            $consulta->where('e.fecha_registro', '<', $hasta);
        }

        $k = $consulta->selectRaw("
            count(*) as total,
            count(*) FILTER (WHERE s.nombre_estado IN ('ENTREGADO','DEVUELTO','FALLIDO')) as cerrados,
            count(*) FILTER (WHERE s.nombre_estado = 'ENTREGADO' AND fe.fecha <= e.fecha_comprometida) as a_tiempo,
            avg(extract(epoch FROM (fe.fecha - e.fecha_registro)) / 3600)
                FILTER (WHERE s.nombre_estado = 'ENTREGADO' AND fe.fecha IS NOT NULL) as horas,
            count(*) FILTER (WHERE ".EnvioController::INCIDENCIA.') as incidencias
        ')->first();

        return [
            'total' => (int) $k->total,
            'cerrados' => (int) $k->cerrados,
            'a_tiempo' => (int) $k->a_tiempo,
            'horas' => $k->horas !== null ? (float) $k->horas : null,
            'incidencias' => (int) $k->incidencias,
        ];
    }

    /** Misma base que la página de Envíos; `fe.fecha` es la fecha real de entrega (evento ENTREGADO). */
    private function base(): Builder
    {
        return DB::table('envios.envios as e')
            ->join('envios.clientes as r', 'r.id_cliente', '=', 'e.cliente_remitente_id')
            ->join('envios.estados_envios as s', 's.id_estado', '=', 'e.estado_envio_id')
            ->join('territorio.territorios as t', 't.id_territorio', '=', 'e.territorio_destino_id')
            ->leftJoinLateral(
                DB::table('envios.envio_eventos as ev')
                    ->join('envios.estados_envios as se', 'se.id_estado', '=', 'ev.estado_envio_id')
                    ->whereColumn('ev.envio_id', 'e.id_envio')
                    ->where('se.nombre_estado', 'ENTREGADO')
                    ->selectRaw('max(ev.ocurrido_en) as fecha'),
                'fe'
            );
    }
}
