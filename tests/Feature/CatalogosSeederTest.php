<?php

namespace Tests\Feature;

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\EstadoDespachoSeeder;
use Database\Seeders\EstadoEnvioSeeder;
use Database\Seeders\EstadoVehiculoSeeder;
use Database\Seeders\MarcaVehiculoSeeder;
use Database\Seeders\ModeloVehiculoSeeder;
use Database\Seeders\ParametroSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoIncidenciaSeeder;
use Database\Seeders\TipoVehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogosSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Re-ejecutar los seeders de catálogo no duplica filas: el conteo de
     * cada tabla de catálogo debe quedar idéntico tras la segunda pasada.
     * (Se siembran los catálogos directo: DatabaseSeeder incluye además la
     * red logística y la historia simulada, que no es idempotente.)
     */
    public function test_ejecutar_los_seeders_de_catalogo_dos_veces_no_duplica(): void
    {
        $this->seedCatalogos();
        $conteoInicial = $this->conteoCatalogos();

        $this->seedCatalogos();

        expect($this->conteoCatalogos())->toBe($conteoInicial);

        // Catálogos mínimos que el sistema necesita para operar
        expect($conteoInicial['envios.estados_envios'])->toBe(8)
            ->and($conteoInicial['flota.estados_vehiculos'])->toBe(4)
            ->and($conteoInicial['flota.tipos_vehiculos'])->toBe(4)
            ->and($conteoInicial['rutas.estados_despachos'])->toBe(3)
            ->and($conteoInicial['rutas.tipos_incidencias'])->toBe(6)
            ->and($conteoInicial['rutas.parametros'])->toBeGreaterThan(0)
            ->and($conteoInicial['roles'])->toBe(3);
    }

    private function seedCatalogos(): void
    {
        $this->seed(EstadoVehiculoSeeder::class);
        $this->seed(TipoVehiculoSeeder::class);
        $this->seed(MarcaVehiculoSeeder::class);
        $this->seed(ModeloVehiculoSeeder::class);
        $this->seed(EstadoEnvioSeeder::class);
        $this->seed(EstadoDespachoSeeder::class);
        $this->seed(TipoIncidenciaSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ParametroSeeder::class);
        $this->seed(AdminUserSeeder::class);
    }

    /**
     * @return array<string, int>
     */
    private function conteoCatalogos(): array
    {
        $tablas = [
            'envios.estados_envios',
            'flota.estados_vehiculos',
            'flota.tipos_vehiculos',
            'flota.marca_vehiculo',
            'flota.modelo_vehiculo',
            'rutas.estados_despachos',
            'rutas.tipos_incidencias',
            'rutas.parametros',
            'roles',
        ];

        return array_combine(
            $tablas,
            array_map(fn (string $tabla): int => (int) DB::table($tabla)->count(), $tablas),
        );
    }
}
