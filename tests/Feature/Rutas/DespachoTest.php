<?php

namespace Tests\Feature\Rutas;

use App\Models\Despacho;
use App\Models\Empleado;
use App\Models\Envio;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\EstadoVehiculoSeeder;
use Database\Seeders\MarcaVehiculoSeeder;
use Database\Seeders\ModeloVehiculoSeeder;
use Database\Seeders\RedLogisticaSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoVehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DespachoTest extends TestCase
{
    use RefreshDatabase;

    private User $operador;

    private Sucursal $sucursal;

    private Empleado $conductor;

    private int $vehiculoId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RedLogisticaSeeder::class);

        // Catálogos mínimos para la flota del test
        $this->seed(TipoVehiculoSeeder::class);
        $this->seed(EstadoVehiculoSeeder::class);
        $this->seed(MarcaVehiculoSeeder::class);
        $this->seed(ModeloVehiculoSeeder::class);

        $this->operador = $this->usuarioConRol('operador');

        $this->sucursal = Sucursal::query()->inRandomOrder()->first();
        $this->conductor = Empleado::factory()->conductor()->create(['sucursal_id' => $this->sucursal->id_sucursal]);
        $this->vehiculoId = Vehiculo::factory()->create(['sucursal_id' => $this->sucursal->id_sucursal])->id_vehiculo;
    }

    private function usuarioConRol(string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Role::query()->where('name', $rol)->value('id'));

        return $usuario;
    }

    /** Carga útil mínima válida para programar un despacho hoy. */
    private function payload(array $sobrescribir = []): array
    {
        return [
            'sucursal_origen_id' => $this->sucursal->id_sucursal,
            'conductor_empleado_id' => $this->conductor->id_empleado,
            'vehiculo_id' => $this->vehiculoId,
            'fecha' => now()->toDateString(),
            'km_planificados' => 120,
            'costo_estimado' => 150,
            'paradas' => [
                ['envio_id' => Envio::factory()->hasta('EN_CENTRO')->create()->id_envio, 'orden' => 1, 'hora_estimada' => now()->setTime(9, 0)->toISOString()],
                ['envio_id' => Envio::factory()->hasta('EN_CENTRO')->create()->id_envio, 'orden' => 2, 'hora_estimada' => now()->setTime(10, 0)->toISOString()],
            ],
            ...$sobrescribir,
        ];
    }

    public function test_el_indice_carga_despachos_y_red(): void
    {
        $this->actingAs($this->operador)
            ->get('/rutas')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('rutas/RutasIndex')
                    ->has('despachos')
                    ->has('conexiones', 11),
            );
    }

    public function test_el_formulario_carga_catalogos_reales(): void
    {
        $this->actingAs($this->operador)
            ->get('/rutas/crear')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('rutas/RutasCreate')
                    ->where('conductores', fn ($c) => $c->pluck('nombre')->contains(fn ($n) => str_contains($n, $this->conductor->nombres)))
                    ->where('vehiculos', fn ($v) => $v->count() === 1),
            );
    }

    public function test_registra_un_despacho_con_paradas(): void
    {
        $respuesta = $this->actingAs($this->operador)
            ->post('/rutas', $this->payload());

        $respuesta->assertRedirect(route('rutas.index'))->assertSessionHas('success');

        $despacho = Despacho::query()->sole();

        expect($despacho->paradas)->toHaveCount(2)
            ->and($despacho->estado->nombre_estado)->toBe('PLANIFICADO')
            ->and((float) $despacho->km_planificados)->toBe(120.0);
    }

    public function test_rechaza_un_envio_ya_despachado(): void
    {
        // El primer envío del payload se despacha dos veces
        $envio = Envio::factory()->hasta('EN_CENTRO')->create();
        $parada = fn (int $orden) => ['envio_id' => $envio->id_envio, 'orden' => $orden, 'hora_estimada' => null];

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload(['paradas' => [$parada(1)]]))
            ->assertRedirect();

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload(['paradas' => [$parada(1)]]))
            ->assertSessionHasErrors('paradas');
    }

    public function test_rechaza_doble_reserva_de_vehiculo_el_mismo_dia(): void
    {
        $this->actingAs($this->operador)->post('/rutas', $this->payload());

        $otroConductor = Empleado::factory()->conductor()->create();

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload(['conductor_empleado_id' => $otroConductor->id_empleado]))
            ->assertSessionHasErrors('fecha');
    }

    public function test_rechaza_conductor_sin_licencia(): void
    {
        $ayudante = Empleado::factory()->create(); // sin numero_licencia

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload(['conductor_empleado_id' => $ayudante->id_empleado]))
            ->assertSessionHasErrors('conductor_empleado_id');
    }

    /** Sin el permiso, un rol de solo lectura no llega al formulario ni registra. */
    public function test_sin_permiso_no_puede_programar_despachos(): void
    {
        $analista = $this->usuarioConRol('analista');

        $this->actingAs($analista)
            ->get('/rutas/crear')
            ->assertForbidden();

        $this->actingAs($analista)
            ->post('/rutas', $this->payload())
            ->assertForbidden();

        expect(Despacho::count())->toBe(0);
    }
}
