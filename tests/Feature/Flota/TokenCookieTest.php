<?php

namespace Tests\Feature\Flota;

use App\Models\EstadoVehiculo;
use App\Models\MarcaVehiculo;
use App\Models\ModeloVehiculo;
use App\Models\Sucursal;
use App\Models\Territorio;
use App\Models\TipoVehiculo;
use App\Models\User;
use Database\Seeders\EstadoVehiculoSeeder;
use Database\Seeders\MarcaVehiculoSeeder;
use Database\Seeders\ModeloVehiculoSeeder;
use Database\Seeders\TipoVehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Tests\TestCase;

class TokenCookieTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase deja la BD sin el cliente personal de Passport,
     * necesario para que el middleware pueda emitir tokens.
     */
    private function crearClientePersonal(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient(
            'SPA',
            config('auth.guards.api.provider'),
        );
    }

    public function test_usuario_con_sesion_recibe_cookie_con_token_jwt(): void
    {
        $this->crearClientePersonal();
        $usuario = User::factory()->create();

        $respuesta = $this->actingAs($usuario)->get('/vehiculos');

        $respuesta->assertOk();
        expect($respuesta->getCookie('api_token', false))->not->toBeNull();

        $token = $respuesta->getCookie('api_token', false)->getValue();
        expect($token)->toBeString()->not->toBe('');
    }

    public function test_el_token_de_la_cookie_autentica_en_la_api(): void
    {
        $this->crearClientePersonal();
        $this->seed(TipoVehiculoSeeder::class);
        $this->seed(EstadoVehiculoSeeder::class);
        $this->seed(MarcaVehiculoSeeder::class);
        $this->seed(ModeloVehiculoSeeder::class);

        $usuario = User::factory()->create();
        $respuesta = $this->actingAs($usuario)->get('/vehiculos');
        $token = $respuesta->getCookie('api_token', false)->getValue();

        // Sin token la API rechaza; con el Bearer de la cookie responde
        $this->getJson('/api/vehiculos')->assertUnauthorized();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/vehiculos')
            ->assertOk()
            ->assertJsonStructure(['vehiculos']);
    }

    public function test_invitado_no_recibe_token_y_la_cookie_se_expira(): void
    {
        $respuesta = $this->get('/vehiculos');

        $cookie = $respuesta->getCookie('api_token', false);
        expect($cookie?->getMaxAge() ?? 0)->toBe(0);
    }

    public function test_registro_de_vehiculo_con_token_de_cookie(): void
    {
        $this->crearClientePersonal();
        $this->seed(TipoVehiculoSeeder::class);
        $this->seed(EstadoVehiculoSeeder::class);
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
        ]);

        $usuario = User::factory()->create();
        $token = $this->actingAs($usuario)
            ->get('/vehiculos')
            ->getCookie('api_token', false)
            ->getValue();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/vehiculos', [
                'placa' => 'P123456',
                'tipo_vehiculo_id' => TipoVehiculo::where('nombre', 'Van')->first()->id_tipo_vehiculo,
                'estado_vehiculo_id' => EstadoVehiculo::where('nombre_estado', 'DISPONIBLE')->first()->id_estado,
                'sucursal_id' => $sucursal->id_sucursal,
                'marca_vehiculo_id' => MarcaVehiculo::where('nombre', 'Toyota')->first()->id_marca,
                'modelo_vehiculo_id' => ModeloVehiculo::where('nombre', 'Hiace')->first()->id_modelo,
                'anio' => 2024,
            ])
            ->assertCreated();
    }
}
