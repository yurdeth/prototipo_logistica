<?php

namespace Tests\Feature;

use App\Models\Parametro;
use Database\Seeders\RedLogisticaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RedLogisticaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los pesos son distancias geodésicas reales: lo guardado en distancia_km
     * debe cuadrar con lo que mide PostGIS sobre las sucursales, no números al azar.
     */
    public function test_los_pesos_cuadran_con_las_distancias_de_postgis(): void
    {
        $this->seed(RedLogisticaSeeder::class);

        $desviaciones = DB::select(
            'SELECT c.id_conexion, c.distancia_km,
                    ST_Distance(a.ubicacion, b.ubicacion) / 1000 AS geodesica
             FROM rutas.conexiones c
             JOIN territorio.sucursales a ON a.id_sucursal = c.origen_sucursal_id
             JOIN territorio.sucursales b ON b.id_sucursal = c.destino_sucursal_id'
        );

        expect($desviaciones)->not->toBeEmpty();

        foreach ($desviaciones as $tramo) {
            expect(abs((float) $tramo->distancia_km - (float) $tramo->geodesica))
                ->toBeLessThan(0.05);
        }
    }

    /**
     * Entre San Salvador y San Miguel hay camino directo y otro por San Vicente:
     * la comparación de rutas alternativas necesita al menos un ciclo así.
     */
    public function test_el_grafo_tiene_alternativa_entre_dos_sucursales(): void
    {
        $this->seed(RedLogisticaSeeder::class);

        $directa = DB::selectOne(
            'SELECT count(*) AS hay
             FROM rutas.conexiones c
             JOIN territorio.sucursales o ON o.id_sucursal = c.origen_sucursal_id
             JOIN territorio.sucursales d ON d.id_sucursal = c.destino_sucursal_id
             WHERE (o.nombre_sucursal = \'Sucursal San Salvador\' AND d.nombre_sucursal = \'Sucursal San Miguel\')
                OR (o.nombre_sucursal = \'Sucursal San Miguel\' AND d.nombre_sucursal = \'Sucursal San Salvador\')'
        )->hay;

        $porSanVicente = DB::selectOne(
            'SELECT count(*) AS hay
             FROM rutas.conexiones c1
             JOIN rutas.conexiones c2 ON c2.origen_sucursal_id = c1.destino_sucursal_id
             WHERE c1.origen_sucursal_id = (SELECT id_sucursal FROM territorio.sucursales WHERE nombre_sucursal = \'Sucursal San Salvador\')
               AND c2.destino_sucursal_id = (SELECT id_sucursal FROM territorio.sucursales WHERE nombre_sucursal = \'Sucursal San Miguel\')'
        )->hay;

        expect($directa)->toBe(1)
            ->and($porSanVicente)->toBeGreaterThan(0);
    }

    /**
     * El tramo candidato a congestión quedó marcado como parámetro versionado
     * y conserva una alternativa indirecta (por Sonsonate) para el día de demo.
     */
    public function test_el_tramo_de_congestion_esta_identificado_con_alternativa(): void
    {
        $this->seed(RedLogisticaSeeder::class);

        $idCongestionado = (int) Parametro::query()->vigente(RedLogisticaSeeder::CLAVE_CONGESTION)->value('valor');

        $tramo = DB::selectOne(
            'SELECT o.nombre_sucursal AS origen, d.nombre_sucursal AS destino
             FROM rutas.conexiones c
             JOIN territorio.sucursales o ON o.id_sucursal = c.origen_sucursal_id
             JOIN territorio.sucursales d ON d.id_sucursal = c.destino_sucursal_id
             WHERE c.id_conexion = ?',
            [$idCongestionado],
        );

        expect($tramo->origen)->toBe('Sucursal San Salvador')
            ->and($tramo->destino)->toBe('Sucursal Santa Ana');

        $alternativa = DB::selectOne(
            'SELECT count(*) AS hay
             FROM rutas.conexiones c1
             JOIN rutas.conexiones c2 ON c2.origen_sucursal_id = c1.destino_sucursal_id
             WHERE c1.origen_sucursal_id = (SELECT id_sucursal FROM territorio.sucursales WHERE nombre_sucursal = \'Sucursal San Salvador\')
               AND c2.destino_sucursal_id = (SELECT id_sucursal FROM territorio.sucursales WHERE nombre_sucursal = \'Sucursal Santa Ana\')'
        )->hay;

        expect($alternativa)->toBeGreaterThan(0);
    }
}
