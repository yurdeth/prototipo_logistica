<?php

namespace Tests\Feature\Rutas;

use App\Models\Envio;
use App\Models\User;
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
        $this->actingAs(User::factory()->create())
            ->get('/rutas/mapa')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('rutas/RutasMapa')
                    ->has('territorios'),
            );
    }

    public function test_el_mapa_no_usa_el_poligono_territorial_para_estimar_ubicaciones(): void
    {
        $envio = Envio::factory()->hasta('EN_RUTA')->create();

        DB::update(
            "UPDATE territorio.territorios
             SET zona = ST_SetSRID(ST_GeomFromText('POLYGON((-89.3 13.6, -89.2 13.6, -89.2 13.7, -89.3 13.7, -89.3 13.6))'), 4326)::geography
             WHERE id_territorio = ?",
            [$envio->territorio_destino_id],
        );

        $this->actingAs(User::factory()->create())
            ->getJson('/rutas/mapa/datos?dias=0')
            ->assertOk()
            ->assertJsonPath('envios.0.id', $envio->id_envio)
            ->assertJsonPath('envios.0.lat', null)
            ->assertJsonPath('envios.0.lng', null);
    }
}
