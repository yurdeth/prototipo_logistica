<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogosSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Re-ejecutar el seeder completo no duplica catálogos: el conteo de
     * cada tabla de catálogo debe quedar idéntico tras la segunda pasada.
     */
    public function test_ejecutar_el_seeder_dos_veces_no_duplica_los_catalogos(): void
    {
        $this->seed(DatabaseSeeder::class);
        $conteoInicial = $this->conteoCatalogos();

        $this->seed(DatabaseSeeder::class);

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
