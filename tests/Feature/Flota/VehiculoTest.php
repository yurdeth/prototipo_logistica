<?php

namespace Tests\Feature\Flota;

use App\Models\EstadoVehiculo;
use App\Models\MarcaVehiculo;
use App\Models\ModeloVehiculo;
use App\Models\Sucursal;
use App\Models\Territorio;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\EstadoVehiculoSeeder;
use Database\Seeders\MarcaVehiculoSeeder;
use Database\Seeders\ModeloVehiculoSeeder;
use Database\Seeders\TipoVehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VehiculoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private array $datosBase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EstadoVehiculoSeeder::class);
        $this->seed(TipoVehiculoSeeder::class);
        $this->seed(MarcaVehiculoSeeder::class);
        $this->seed(ModeloVehiculoSeeder::class);

        $territorio = Territorio::create([
            'nombre_territorio' => 'Santa Cruz',
            'tipo_territorio' => 'DEPARTAMENTO',
        ]);
        $sucursal = Sucursal::create([
            'codigo' => 'SCZ-01',
            'nombre_sucursal' => 'Bodega Base',
            'territorio_id' => $territorio->id_territorio,
            'direccion' => 'Base operativa',
            'latitud' => -17.783,
            'longitud' => -63.182,
        ]);

        $toyota = MarcaVehiculo::where('nombre', 'Toyota')->first();
        $hiace = ModeloVehiculo::where('nombre', 'Hiace')->first();
        $van = TipoVehiculo::where('nombre', 'Van')->first();

        $this->usuario = User::factory()->create();
        $this->datosBase = [
            'placa' => 'P123456',
            'tipo_vehiculo_id' => $van->id_tipo_vehiculo,
            'estado_vehiculo_id' => EstadoVehiculo::where('nombre_estado', 'DISPONIBLE')->first()->id_estado,
            'sucursal_id' => $sucursal->id_sucursal,
            'marca_vehiculo_id' => $toyota->id_marca,
            'modelo_vehiculo_id' => $hiace->id_modelo,
            'anio' => 2024,
            'consumo_km_gal' => 12.5,
        ];
    }

    public function test_usuario_autenticado_registra_un_vehiculo(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->postJson('/vehiculos', $this->datosBase);

        $respuesta->assertRedirect(route('vehiculos.index'))
            ->assertSessionHas('success');

        $vehiculo = Vehiculo::where('placa', 'P123456')->first();
        expect($vehiculo->marca)->toBe('Toyota')
            ->and($vehiculo->modelo)->toBe('Hiace');
        expect($vehiculo)->not->toBeNull()
            ->and(
                DB::table('flota.vehiculo_marca_modelo')
                    ->where('vehiculo_id', $vehiculo->id_vehiculo)
                    ->where('marca_vehiculo_id', $this->datosBase['marca_vehiculo_id'])
                    ->where('modelo_vehiculo_id', $this->datosBase['modelo_vehiculo_id'])
                    ->exists()
            )->toBeTrue();
    }

    public function test_los_formularios_cargan_catalogos_desde_la_base(): void
    {
        // Los catálogos deben llegar sembrados en la base, no escritos a mano en la vista
        $this->actingAs($this->usuario)
            ->get('/vehiculos/crear')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('flota/VehiculosCreate')
                    ->where('tipos', fn ($tipos) => $tipos->pluck('nombre')->contains('Motocicleta'))
                    ->where('estados', fn ($estados) => $estados->pluck('nombre_estado')->contains('INACTIVO'))
                    ->where('marcas', fn ($marcas) => $marcas->pluck('nombre')->contains('Toyota'))
                    ->where('modelos', fn ($modelos) => $modelos->pluck('nombre')->contains('Hiace')),
            );

        $this->actingAs($this->usuario)
            ->get('/vehiculos')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->component('flota/VehiculosIndex'),
            );
    }

    public function test_rechaza_modelo_de_otra_marca(): void
    {
        $cb125 = ModeloVehiculo::where('nombre', 'CB125')->first();
        $kia = MarcaVehiculo::where('nombre', 'Kia')->first();

        // CB125 es de Honda, no puede registrarse como Kia
        $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [
                ...$this->datosBase,
                'marca_vehiculo_id' => $kia->id_marca,
                'modelo_vehiculo_id' => $cb125->id_modelo,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('modelo_vehiculo_id');

        expect(Vehiculo::count())->toBe(0);
    }

    public function test_rechaza_tipo_incoherente_con_el_modelo(): void
    {
        $cb125 = ModeloVehiculo::where('nombre', 'CB125')->first();
        $van = TipoVehiculo::where('nombre', 'Van')->first();

        // Un modelo de moto no admite el tipo Van
        $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [
                ...$this->datosBase,
                'marca_vehiculo_id' => $cb125->marca_vehiculo_id,
                'modelo_vehiculo_id' => $cb125->id_modelo,
                'tipo_vehiculo_id' => $van->id_tipo_vehiculo,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo_vehiculo_id');

        // Con su tipo correcto (Motocicleta) sí se registra
        $moto = TipoVehiculo::where('nombre', 'Motocicleta')->first();
        $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [
                ...$this->datosBase,
                'placa' => 'M654321',
                'marca_vehiculo_id' => $cb125->marca_vehiculo_id,
                'modelo_vehiculo_id' => $cb125->id_modelo,
                'tipo_vehiculo_id' => $moto->id_tipo_vehiculo,
            ])
            ->assertRedirect(route('vehiculos.index'));
    }

    public function test_normaliza_la_placa_a_mayusculas_sin_separadores(): void
    {
        $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [...$this->datosBase, 'placa' => ' c 345-678'])
            ->assertRedirect(route('vehiculos.index'));

        expect(Vehiculo::where('placa', 'C345678')->exists())->toBeTrue();
    }

    public function test_acepta_placas_cortas_mixtas_y_categorias_mb_ab(): void
    {
        foreach (['P1234', 'M12345', 'AB12345', 'MB123', 'RE123456', 'CD123456', 'P457A', 'P12'] as $placa) {
            $this->actingAs($this->usuario)
                ->postJson('/vehiculos', [
                    ...$this->datosBase,
                    'placa' => $placa,
                    'anio' => 2020,
                ])
                ->assertRedirect(route('vehiculos.index'));

            expect(Vehiculo::where('placa', $placa)->exists())->toBeTrue();
        }
    }

    public function test_normaliza_formatos_con_espacios_y_guiones_internos(): void
    {
        $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [...$this->datosBase, 'placa' => 'P 4 57A'])
            ->assertRedirect(route('vehiculos.index'));

        expect(Vehiculo::where('placa', 'P457A')->exists())->toBeTrue();
    }

    public function test_rechaza_placas_fuera_del_formato_nacional(): void
    {
        $casosInvalidos = ['XYZ12345', 'P1234567', 'Q123456', '123456P', 'P#123'];

        foreach ($casosInvalidos as $placa) {
            $this->actingAs($this->usuario, 'api')
                ->postJson('/vehiculos', [...$this->datosBase, 'placa' => $placa])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('placa');
        }

        expect(Vehiculo::count())->toBe(0);
    }

    public function test_no_se_permite_duplicar_la_placa(): void
    {
        Vehiculo::create($this->datosBase);

        $respuesta = $this->actingAs($this->usuario)
            ->postJson('/vehiculos', $this->datosBase);

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors('placa');

        expect(Vehiculo::where('placa', 'P123456')->count())->toBe(1);
    }

    public function test_valida_campos_obligatorios_y_rangos(): void
    {
        $respuesta = $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [
                'placa' => '',
                'tipo_vehiculo_id' => 99999,
                'anio' => 1900,
                'consumo_km_gal' => 0,
            ]);

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['placa', 'tipo_vehiculo_id', 'estado_vehiculo_id', 'sucursal_id', 'marca_vehiculo_id', 'anio', 'consumo_km_gal']);
    }

    public function test_rechaza_anios_futuros(): void
    {
        $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [...$this->datosBase, 'anio' => (int) date('Y') + 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anio');

        $this->actingAs($this->usuario)
            ->postJson('/vehiculos', [...$this->datosBase, 'anio' => (int) date('Y')])
            ->assertRedirect(route('vehiculos.index'));
    }

    public function test_actualiza_un_vehiculo_sin_chocar_con_su_propia_placa(): void
    {
        $vehiculo = Vehiculo::create($this->datosBase);

        // La misma placa debe ser válida al editar el propio vehículo
        $this->actingAs($this->usuario)
            ->putJson("/vehiculos/{$vehiculo->id_vehiculo}", [...$this->datosBase, 'anio' => 2022])
            ->assertRedirect(route('vehiculos.index'));

        expect($vehiculo->refresh()->anio)->toBe(2022);

        expect(Vehiculo::where('placa', 'P123456')->count())->toBe(1);
    }

    public function test_al_editar_se_reemplaza_la_pivote_marca_modelo(): void
    {
        $vehiculo = Vehiculo::create($this->datosBase);
        $canter = ModeloVehiculo::where('nombre', 'Canter')->first();
        $mitsubishi = MarcaVehiculo::where('nombre', 'Mitsubishi')->first();

        $this->actingAs($this->usuario)
            ->putJson("/vehiculos/{$vehiculo->id_vehiculo}", [
                ...$this->datosBase,
                'marca_vehiculo_id' => $mitsubishi->id_marca,
                'modelo_vehiculo_id' => $canter->id_modelo,
                'tipo_vehiculo_id' => $canter->tipo_vehiculo_id,
            ])
            ->assertRedirect(route('vehiculos.index'));

        $vehiculo->refresh();
        expect($vehiculo->marca)->toBe('Mitsubishi')
            ->and($vehiculo->modelo)->toBe('Canter');

        expect(
            DB::table('flota.vehiculo_marca_modelo')
                ->where('vehiculo_id', $vehiculo->id_vehiculo)
                ->count(),
        )->toBe(1)
            ->and(
                DB::table('flota.vehiculo_marca_modelo')
                    ->where('vehiculo_id', $vehiculo->id_vehiculo)
                    ->where('modelo_vehiculo_id', $canter->id_modelo)
                    ->exists(),
            )->toBeTrue();
    }

    public function test_desactiva_el_vehiculo_sin_eliminarlo(): void
    {
        $vehiculo = Vehiculo::create($this->datosBase);

        $this->actingAs($this->usuario)
            ->postJson("/vehiculos/{$vehiculo->id_vehiculo}/desactivar")
            ->assertRedirect(route('vehiculos.edit', ['vehiculo' => $vehiculo->id_vehiculo]));

        $vehiculo->refresh();
        expect($vehiculo->estado->nombre_estado)->toBe('INACTIVO')
            ->and(Vehiculo::count())->toBe(1);
    }

    public function test_reactiva_un_vehiculo_inactivo(): void
    {
        $vehiculo = Vehiculo::create($this->datosBase);
        $inactivo = EstadoVehiculo::where('nombre_estado', 'INACTIVO')->first();
        $vehiculo->update(['estado_vehiculo_id' => $inactivo->id_estado]);

        $this->actingAs($this->usuario)
            ->postJson("/vehiculos/{$vehiculo->id_vehiculo}/reactivar")
            ->assertRedirect(route('vehiculos.edit', ['vehiculo' => $vehiculo->id_vehiculo]));

        expect($vehiculo->refresh()->estado->nombre_estado)->toBe('DISPONIBLE');
    }
}
