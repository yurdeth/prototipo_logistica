<?php

namespace Tests\Feature;

use App\Models\Parametro;
use Database\Seeders\HistoriaSeeder;
use Database\Seeders\RedLogisticaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoriaSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Con la historia sembrada (volumen corto de prueba) se pueden responder
     * las tres familias de indicadores del documento con consultas simples:
     * tiempos de entrega, kilómetros de despacho y asignación de recursos.
     */
    public function test_la_historia_responde_los_indicadores(): void
    {
        $this->seed(RedLogisticaSeeder::class);
        (new HistoriaSeeder(dias: 6, envioMin: 4, envioMax: 8))->run();

        $dias = (int) DB::selectOne(
            'SELECT count(DISTINCT date(fecha_registro)) AS dias FROM envios.envios'
        )->dias;

        // Familia de tiempos: horas promedio entre registro y entrega real
        $tiempoPromedio = (float) DB::selectOne(
            "SELECT avg(extract(epoch FROM (ev.ocurrido_en - e.fecha_registro)) / 3600) AS horas
             FROM envios.envios e
             JOIN envios.envio_eventos ev ON ev.envio_id = e.id_envio
             JOIN envios.estados_envios s ON s.id_estado = ev.estado_envio_id
             WHERE s.nombre_estado = 'ENTREGADO'"
        )->horas;

        // Familia de kilómetros: los despachos completados reportan km recorridos
        $conKilometros = (int) DB::selectOne(
            'SELECT count(*) AS total FROM rutas.despachos WHERE km_recorridos IS NOT NULL'
        )->total;

        // Familia de asignación: cada despacho tiene conductor con licencia y vehículo
        $despachosConRecursos = (int) DB::selectOne(
            'SELECT count(*) AS total
             FROM rutas.despachos d
             JOIN personal.empleados c ON c.id_empleado = d.conductor_empleado_id
             JOIN flota.vehiculos v ON v.id_vehiculo = d.vehiculo_id
             WHERE c.numero_licencia IS NOT NULL'
        )->total;

        $conParadas = (int) DB::selectOne(
            'SELECT count(DISTINCT d.id_despacho) AS total
             FROM rutas.despachos d
             JOIN rutas.despacho_paradas p ON p.despacho_id = d.id_despacho'
        )->total;

        expect($dias)->toBeGreaterThanOrEqual(5)
            ->and($tiempoPromedio)->toBeGreaterThan(0)
            ->and($conKilometros)->toBeGreaterThan(0)
            ->and($despachosConRecursos)->toBeGreaterThan(0)
            ->and($conParadas)->toBe($despachosConRecursos);
    }

    /** El día congestionado existe y es visible: su tramo queda versionado y sus incidencias registradas. */
    public function test_existe_el_dia_congestionado(): void
    {
        $this->seed(RedLogisticaSeeder::class);
        (new HistoriaSeeder(dias: 6, envioMin: 4, envioMax: 8))->run();

        $congestion = Parametro::query()
            ->where('clave', RedLogisticaSeeder::CLAVE_CONGESTION)
            ->orderByDesc('vigente_desde')
            ->first();

        expect($congestion)->not->toBeNull()
            ->and((int) $congestion->valor)->toBeGreaterThan(0);

        $hayIncidenciaCongestion = (int) DB::selectOne(
            'SELECT count(*) AS total
             FROM rutas.incidencias i
             JOIN rutas.tipos_incidencias t ON t.id_tipo = i.tipo_incidencia_id
             WHERE t.nombre_tipo = \'TRANSITO\' AND i.minutos_retraso >= 40'
        )->total;

        expect($hayIncidenciaCongestion)->toBeGreaterThan(0);
    }
}
