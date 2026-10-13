<?php

namespace Tests\Feature;

use App\Enums\EstadoDespachoEnum;
use App\Enums\EstadoEnvioEnum;
use App\Enums\TipoIncidenciaEnum;
use App\Http\Controllers\Envios\EnvioController;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EstadoCatalogoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los enums deben coincidir uno a uno con las filas de sus catálogos:
     * mismos valores, sin sobrantes en ningún lado.
     */
    public function test_los_enums_espejan_los_catalogos_de_la_base(): void
    {
        $this->seed(DatabaseSeeder::class);

        expect(
            $this->nombres('envios.estados_envios', 'nombre_estado', 'id_estado')
        )->toBe(EstadoEnvioEnum::valores())
            ->and($this->nombres('rutas.estados_despachos', 'nombre_estado', 'id_estado'))
            ->toBe(EstadoDespachoEnum::valores())
            ->and($this->nombres('rutas.tipos_incidencias', 'nombre_tipo', 'id_tipo'))
            ->toBe(TipoIncidenciaEnum::valores())
            // La constante del controlador sigue siendo la lista para validación "in:"
            ->and(EnvioController::ESTADOS)
            ->toBe(EstadoEnvioEnum::VALUES);
    }

    /** @return list<string> */
    private function nombres(string $tabla, string $columna, string $pk): array
    {
        // Por PK: conserva el orden del ciclo de vida con que la migración sembró.
        return DB::table($tabla)->orderBy($pk)->pluck($columna)->all();
    }
}
