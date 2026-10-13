<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Envio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnvioFactoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Diez envíos generados de un tirón, cada uno con su cliente, sus eventos
     * en orden creciente y el estado final idéntico al del último evento:
     * sin esa consistencia, los indicadores de tiempos mienten.
     */
    public function test_genera_envios_con_rastro_coherente(): void
    {
        Envio::factory()->count(10)->create();

        expect(Envio::count())->toBe(10)
            ->and(Cliente::count())->toBeGreaterThan(0);

        Envio::query()->get()->each(function (Envio $envio): void {
            $eventos = DB::table('envios.envio_eventos')
                ->where('envio_id', $envio->id_envio)
                ->orderBy('id_evento')
                ->get();

            // El rastro arranca en REGISTRADO y su último estado es el actual del envío
            $nombres = $eventos->pluck('estado_envio_id')
                ->map(fn ($id) => DB::table('envios.estados_envios')->where('id_estado', $id)->value('nombre_estado'));

            $estadoActual = DB::table('envios.estados_envios')
                ->where('id_estado', $envio->estado_envio_id)
                ->value('nombre_estado');

            expect($nombres->first())->toBe('REGISTRADO')
                ->and($nombres->last())->toBe($estadoActual)
                ->and($eventos->count())->toBeGreaterThanOrEqual(1);

            // Los tiempos del rastro crecen sin saltos hacia atrás
            $tiempos = $eventos->pluck('ocurrido_en')->map(fn ($t) => strtotime($t));

            expect($tiempos->sort()->values()->all())->toBe($tiempos->values()->all());
        });
    }

    /** Las entregas fuera de plazo deben existir como datos: sin ellas no hay incidencias que analizar. */
    public function test_hay_entregas_a_tiempo_y_tarde(): void
    {
        Envio::factory()->entregadoATiempo()->count(5)->create();
        Envio::factory()->entregadoTarde()->count(5)->create();

        $fueraDePlazo = DB::selectOne(
            "SELECT count(*) AS total
             FROM envios.envios e
             JOIN envios.envio_eventos ev ON ev.envio_id = e.id_envio
             JOIN envios.estados_envios s ON s.id_estado = ev.estado_envio_id
             WHERE s.nombre_estado = 'ENTREGADO' AND ev.ocurrido_en > e.fecha_comprometida"
        )->total;

        $aTiempo = DB::selectOne(
            "SELECT count(*) AS total
             FROM envios.envios e
             JOIN envios.envio_eventos ev ON ev.envio_id = e.id_envio
             JOIN envios.estados_envios s ON s.id_estado = ev.estado_envio_id
             WHERE s.nombre_estado = 'ENTREGADO' AND ev.ocurrido_en <= e.fecha_comprometida"
        )->total;

        expect($fueraDePlazo)->toBe(5)
            ->and($aTiempo)->toBe(5);
    }
}
