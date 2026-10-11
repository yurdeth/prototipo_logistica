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
            'consumo_km_l' => 12.5,
        ];
    }

    public function test_usuario_autenticado_registra_un_vehiculo(): void
    {
        $respuesta = $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', $this->datosBase);

        $respuesta->assertCreated()
            ->assertJsonPath('vehiculo.placa', 'P123456')
            ->assertJsonPath('vehiculo.marca', 'Toyota')
            ->assertJsonPath('vehiculo.modelo', 'Hiace');

        $vehiculo = Vehiculo::where('placa', 'P123456')->first();
        expect($vehiculo)->not->toBeNull()
            ->and(
                DB::table('flota.vehiculo_marca_modelo')
                    ->where('vehiculo_id', $vehiculo->id_vehiculo)
                    ->where('marca_vehiculo_id', $this->datosBase['marca_vehiculo_id'])
                    ->where('modelo_vehiculo_id', $this->datosBase['modelo_vehiculo_id'])
                    ->exists()
            )->toBeTrue();
    }

    public function test_rechaza_modelo_de_otra_marca(): void
    {
        $cb125 = ModeloVehiculo::where('nombre', 'CB125')->first();
        $kia = MarcaVehiculo::where('nombre', 'Kia')->first();

        // CB125 es de Honda, no puede registrarse como Kia
        $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', [
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
        $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', [
                ...$this->datosBase,
                'marca_vehiculo_id' => $cb125->marca_vehiculo_id,
                'modelo_vehiculo_id' => $cb125->id_modelo,
                'tipo_vehiculo_id' => $van->id_tipo_vehiculo,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tipo_vehiculo_id');

        // Con su tipo correcto (Motocicleta) sí se registra
        $moto = TipoVehiculo::where('nombre', 'Motocicleta')->first();
        $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', [
                ...$this->datosBase,
                'placa' => 'M654321',
                'marca_vehiculo_id' => $cb125->marca_vehiculo_id,
                'modelo_vehiculo_id' => $cb125->id_modelo,
                'tipo_vehiculo_id' => $moto->id_tipo_vehiculo,
            ])
            ->assertCreated();
    }

    public function test_normaliza_la_placa_a_mayusculas_sin_separadores(): void
    {
        $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', [...$this->datosBase, 'placa' => ' c 345-678'])
            ->assertCreated()
            ->assertJsonPath('vehiculo.placa', 'C345678');
    }

    public function test_rechaza_placas_fuera_del_formato_nacional(): void
    {
        $casosInvalidos = ['XYZ12345', 'P12345', 'Q123456', '123456P', 'P12-45A'];

        foreach ($casosInvalidos as $placa) {
            $this->actingAs($this->usuario, 'api')
                ->postJson('/api/vehiculos', [...$this->datosBase, 'placa' => $placa])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('placa');
        }

        expect(Vehiculo::count())->toBe(0);
    }

    public function test_no_se_permite_duplicar_la_placa(): void
    {
        Vehiculo::create($this->datosBase);

        $respuesta = $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', $this->datosBase);

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors('placa');

        expect(Vehiculo::where('placa', 'P123456')->count())->toBe(1);
    }

    public function test_valida_campos_obligatorios_y_rangos(): void
    {
        $respuesta = $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', [
                'placa' => '',
                'tipo_vehiculo_id' => 99999,
                'anio' => 1900,
                'consumo_km_l' => 0,
            ]);

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['placa', 'tipo_vehiculo_id', 'estado_vehiculo_id', 'sucursal_id', 'marca_vehiculo_id', 'anio', 'consumo_km_l']);
    }

    public function test_rechaza_anios_futuros(): void
    {
        $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', [...$this->datosBase, 'anio' => (int) date('Y') + 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('anio');

        $this->actingAs($this->usuario, 'api')
            ->postJson('/api/vehiculos', [...$this->datosBase, 'anio' => (int) date('Y')])
            ->assertCreated();
    }

    public function test_invitado_recibe_401(): void
    {
        $this->postJson('/api/vehiculos', $this->datosBase)
            ->assertUnauthorized();
    }
}
