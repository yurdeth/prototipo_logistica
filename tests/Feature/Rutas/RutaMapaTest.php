<?php

namespace Tests\Feature\Rutas;

use App\Models\Envio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->actingAs(User::factory()->create())
            ->get('/rutas/mapa')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('rutas/RutasMapa')
                    ->has('territorios'),
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
}
