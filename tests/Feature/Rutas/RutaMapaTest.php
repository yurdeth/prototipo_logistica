<?php

namespace Tests\Feature\Rutas;

use App\Models\Empleado;
use App\Models\Envio;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RutaMapaTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_mapa_calcula_indicadores_y_filtra_por_estado(): void
    {
        Envio::factory()->hasta('ENTREGADO')->create();
        Envio::factory()->hasta('EN_RUTA')->create();

        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->getJson('/rutas/mapa/datos?dias=0')
            ->assertOk()
            ->assertJsonPath('kpis.total', 2)
            ->assertJsonPath('kpis.pendientes', 0)
            ->assertJsonPath('kpis.en_ruta', 1)
            ->assertJsonPath('kpis.entregados', 1)
            ->assertJsonPath('kpis.tasa_entrega', 50)
            ->assertJsonPath('estados.4.estado', 'EN_RUTA');

        $this->actingAs($usuario)
            ->getJson('/rutas/mapa/datos?dias=0&estado=ENTREGADO')
            ->assertOk()
            ->assertJsonPath('kpis.total', 1)
            ->assertJsonPath('kpis.entregados', 1)
            ->assertJsonPath('estados.4.total', 1);
    }

    public function test_el_indice_muestra_los_niveles_territoriales_disponibles(): void
    {
        // Los territorios ya no viajan como prop: el mapa los trae de /rutas/mapa/datos.
        $this->actingAs(User::factory()->create())
            ->get('/rutas/mapa')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->component('rutas/RutasMapa'),
            );
    }

    public function test_el_api_del_mapa_ya_no_expone_catalogos_de_zonas(): void
    {
        Envio::factory()->hasta('EN_RUTA')->create();
        $this->actingAs(User::factory()->create())
            ->getJson('/rutas/mapa/datos?dias=0')
            ->assertOk()
            ->assertJsonMissingPath('catalogos.zonas')
            ->assertJsonMissingPath('filtros.zona');
    }

    public function test_el_buscador_del_mapa_encuentra_por_guia(): void
    {
        $objetivo = Envio::factory()->hasta('EN_RUTA')->create();
        Envio::factory()->hasta('EN_RUTA')->create();

        $this->actingAs(User::factory()->create())
            ->getJson("/rutas/mapa/datos?dias=0&q={$objetivo->codigo_guia}")
            ->assertOk()
            ->assertJsonPath('kpis.total', 1)
            ->assertJsonPath('envios.0.codigo_guia', $objetivo->codigo_guia);
    }

    public function test_el_buscador_del_mapa_encuentra_rutas_por_codigo(): void
    {
        foreach (['PLANIFICADO', 'EN_CURSO', 'COMPLETADO'] as $estado) {
            DB::table('rutas.estados_despachos')->insertOrIgnore(['nombre_estado' => $estado]);
        }

        $objetivo = Envio::factory()->hasta('EN_RUTA')->create(['fecha_registro' => now()->subDays(30)]);
        $otro = Envio::factory()->hasta('EN_RUTA')->create();

        $sucursal = DB::table('territorio.sucursales')->inRandomOrder()->first();
        $despachoId = (int) DB::table('rutas.despachos')->insertGetId([
            'sucursal_origen_id' => $sucursal->id_sucursal,
            'conductor_empleado_id' => Empleado::factory()->create()->id_empleado,
            'vehiculo_id' => Vehiculo::factory()->create()->id_vehiculo,
            'fecha' => now()->toDateString(),
            'estado_despacho_id' => DB::table('rutas.estados_despachos')->where('nombre_estado', 'EN_CURSO')->value('id_estado'),
        ], 'id_despacho');
        DB::table('rutas.despacho_paradas')->insert(['despacho_id' => $despachoId, 'envio_id' => $objetivo->id_envio, 'orden' => 1]);

        // Por código DSP: la ruta aparece aunque su envío se registró hace semanas
        $codigo = 'DSP-'.str_pad((string) $despachoId, 6, '0', STR_PAD_LEFT);
        $this->actingAs(User::factory()->create())
            ->getJson("/rutas/mapa/datos?dias=7&q={$codigo}")
            ->assertOk()
            ->assertJsonPath('kpis.total', 1)
            ->assertJsonPath('rutas.data.0.id', $despachoId)
            ->assertJsonMissing(['id' => $otro->id_envio]);
    }
}
