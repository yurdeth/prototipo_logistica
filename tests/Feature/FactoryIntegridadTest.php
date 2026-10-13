<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Models\Puesto;
use App\Models\Sucursal;
use App\Models\Vehiculo;
use Database\Factories\SucursalFactory;
use Database\Seeders\TipoVehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryIntegridadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Las factories de flota, personal y territorio corren sin violar llaves
     * foráneas y con datos de donde salgan indicadores con sentido.
     */
    public function test_genera_lotes_completos_sin_violar_integridad(): void
    {
        $this->seed(TipoVehiculoSeeder::class);

        $sucursales = Sucursal::factory()->count(6)->create();
        $vehiculos = Vehiculo::factory()->count(12)->create();
        $conductores = Empleado::factory()->conductor()->count(4)->create();
        Empleado::factory()->count(8)->create();

        $sucursales->each(fn ($s) => expect($s->territorio->id_territorio)->toBeInt());
        $vehiculos->each(fn ($v) => expect($v->sucursal->id_sucursal)->toBeInt());
        $conductores->each(fn ($e) => expect($e->numero_licencia)->not->toBeNull());

        expect(Empleado::count())->toBe(12)
            ->and(Vehiculo::count())->toBe(12);
    }

    /**
     * Las coordenadas de las sucursales caen dentro del bounding box nacional;
     * coordenadas al azar romperían las distancias PostGIS de la red.
     */
    public function test_las_sucursales_caidas_en_el_pais(): void
    {
        Sucursal::factory()->count(10)->create();

        Sucursal::query()->get()->each(function (Sucursal $sucursal): void {
            expect((float) $sucursal->latitud)
                ->toBeGreaterThanOrEqual(SucursalFactory::LATITUD_MIN)
                ->toBeLessThanOrEqual(SucursalFactory::LATITUD_MAX)
                ->and((float) $sucursal->longitud)
                ->toBeGreaterThanOrEqual(SucursalFactory::LONGITUD_MIN)
                ->toBeLessThanOrEqual(SucursalFactory::LONGITUD_MAX);
        });
    }

    /** Vehículos de tipos variados: la utilización por tipo necesita contraste. */
    public function test_la_flota_varia_en_tipo_y_capacidad(): void
    {
        $this->seed(TipoVehiculoSeeder::class);

        Vehiculo::factory()->count(16)->create();

        $tipos = Vehiculo::query()->with('tipo')->get()->pluck('tipo.nombre')->unique();

        expect($tipos->count())->toBeGreaterThan(1);

        $capacidades = Vehiculo::query()->with('tipo')->get()->pluck('tipo.capacidad_kg')->map(fn ($c) => (float) $c);

        expect($capacidades->max())->toBeGreaterThan($capacidades->min() * 10);
    }

    /** Puestos dentro de departamentos reales y varios conductores entre el personal. */
    public function test_el_personal_conductores_con_licencia(): void
    {
        Empleado::factory()->conductor()->count(5)->create();

        $conductores = Empleado::query()->whereNotNull('numero_licencia')->get();

        expect($conductores)->toHaveCount(5)
            ->and($conductores->every(
                fn ($e) => $e->puesto->nombre_puesto === 'Conductor'
                    && Puesto::query()->whereKey($e->puesto_id)->where('activo', true)->exists()
            ))->toBeTrue();
    }
}
