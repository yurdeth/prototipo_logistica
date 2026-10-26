<?php

namespace Tests\Feature\Rutas;

use App\Models\Despacho;
use App\Models\Empleado;
use App\Models\Envio;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\TipoVehiculo;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\EstadoVehiculoSeeder;
use Database\Seeders\MarcaVehiculoSeeder;
use Database\Seeders\ModeloVehiculoSeeder;
use Database\Seeders\RedLogisticaSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TipoVehiculoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        // Camión pesado (18 t): la capacidad nunca es el motivo de rechazo en los tests base
        $this->vehiculoId = Vehiculo::factory()
            ->create([
                'tipo_vehiculo_id' => DB::table('flota.tipos_vehiculos')->where('nombre', 'Camion Pesado')->value('id_tipo_vehiculo'),
                'sucursal_id' => $this->sucursal->id_sucursal,
            ])
            ->id_vehiculo;
    }

    private function usuarioConRol(string $rol): User
    {
        $usuario = User::factory()->create();
        $usuario->roles()->attach(Role::query()->where('name', $rol)->value('id'));

        return $usuario;
    }

    /** Envío de prueba registrado en la sucursal de origen del despacho. */
    private function envioDeLaSucursal(): Envio
    {
        return Envio::factory()->hasta('EN_CENTRO')->create(['sucursal_origen_id' => $this->sucursal->id_sucursal]);
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
                ['envio_id' => $this->envioDeLaSucursal()->id_envio, 'orden' => 1, 'hora_estimada' => now()->setTime(9, 0)->toISOString()],
                ['envio_id' => $this->envioDeLaSucursal()->id_envio, 'orden' => 2, 'hora_estimada' => now()->setTime(10, 0)->toISOString()],
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

    public function test_el_seguimiento_conserva_todas_las_paradas_aunque_se_filtre_por_estado(): void
    {
        $envios = [
            $this->envioDeLaSucursal(),
            $this->envioDeLaSucursal(),
        ];
        $paradas = array_map(
            fn (Envio $envio, int $indice): array => [
                'envio_id' => $envio->id_envio,
                'orden' => $indice + 1,
                'hora_estimada' => now()->setTime(9 + $indice, 0)->toISOString(),
            ],
            $envios,
            array_keys($envios),
        );

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload(['paradas' => $paradas]))
            ->assertRedirect(route('rutas.index'));

        $despacho = Despacho::query()->sole();
        DB::table('envios.envios')
            ->where('id_envio', $envios[1]->id_envio)
            ->update([
                'estado_envio_id' => DB::table('envios.estados_envios')
                    ->where('nombre_estado', 'ENTREGADO')
                    ->value('id_estado'),
            ]);

        $this->actingAs($this->operador)
            ->getJson("/rutas/mapa/datos?dias=0&estado=ENTREGADO&ruta={$despacho->id_despacho}")
            ->assertOk()
            ->assertJsonCount(2, 'rutas.0.paradas')
            ->assertJsonPath('rutas.0.paradas.0.envio_id', $envios[0]->id_envio)
            ->assertJsonPath('rutas.0.paradas.1.envio_id', $envios[1]->id_envio)
            ->assertJsonPath('rutas.0.paradas.0.estado_envio', 'EN_CENTRO')
            ->assertJsonPath('rutas.0.paradas.1.estado_envio', 'ENTREGADO');
    }

    public function test_el_seguimiento_usa_ubicaciones_de_sucursales_si_no_hay_ubicacion_reportada(): void
    {
        $territorios = DB::table('territorio.territorios')
            ->whereIn('nombre_territorio', ['San Salvador', 'San Miguel'])
            ->pluck('id_territorio', 'nombre_territorio');

        $envios = [
            $this->envioDeLaSucursal(),
            $this->envioDeLaSucursal(),
        ];
        $territoriosDestino = [
            $territorios['San Salvador'],
            $territorios['San Miguel'],
        ];

        foreach ($territoriosDestino as $indice => $territorioId) {
            DB::table('envios.envios')
                ->where('id_envio', $envios[$indice]->id_envio)
                ->update(['territorio_destino_id' => $territorioId]);
        }

        $coordenadasEsperadas = collect($territoriosDestino)
            ->map(fn (int $territorioId) => DB::table('territorio.sucursales')
                ->where('territorio_id', $territorioId)
                ->whereNotNull('latitud')
                ->whereNotNull('longitud')
                ->selectRaw('AVG(latitud)::double precision as lat, AVG(longitud)::double precision as lng')
                ->first())
            ->values();

        $paradas = array_map(
            fn (Envio $envio, int $indice): array => [
                'envio_id' => $envio->id_envio,
                'orden' => $indice + 1,
                'hora_estimada' => now()->setTime(9 + $indice, 0)->toISOString(),
            ],
            $envios,
            array_keys($envios),
        );

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload(['paradas' => $paradas]))
            ->assertRedirect(route('rutas.index'));

        $despacho = Despacho::query()->sole();
        $respuesta = $this->actingAs($this->operador)
            ->getJson("/rutas/mapa/datos?dias=0&ruta={$despacho->id_despacho}")
            ->assertOk()
            ->assertJsonPath('rutas.0.paradas.0.ubicacion_aproximada', true)
            ->assertJsonPath('rutas.0.paradas.1.ubicacion_aproximada', true);

        $this->assertNotNull(data_get($respuesta->json(), 'rutas.0.paradas.0.lat'));
        $this->assertNotNull(data_get($respuesta->json(), 'rutas.0.paradas.0.lng'));
        $this->assertNotNull(data_get($respuesta->json(), 'rutas.0.paradas.1.lat'));
        $this->assertNotNull(data_get($respuesta->json(), 'rutas.0.paradas.1.lng'));
        foreach ($coordenadasEsperadas as $indice => $coordenadas) {
            $this->assertNotNull($coordenadas);
            $this->assertEquals(
                (float) $coordenadas->lat,
                data_get($respuesta->json(), "rutas.0.paradas.{$indice}.lat"),
            );
            $this->assertEquals(
                (float) $coordenadas->lng,
                data_get($respuesta->json(), "rutas.0.paradas.{$indice}.lng"),
            );
        }
    }

    public function test_rechaza_un_envio_ya_despachado(): void
    {
        // El primer envío del payload se despacha dos veces
        $envio = $this->envioDeLaSucursal();
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

    public function test_rechaza_peso_que_supera_la_capacidad_del_vehiculo(): void
    {
        $tipoChico = TipoVehiculo::query()->create([
            'nombre' => 'Cuadro Moto',
            'marca' => 'X',
            'capacidad_kg' => 100.0,
            'capacidad_m3' => null,
        ]);
        $vehiculoChico = Vehiculo::factory()->create([
            'tipo_vehiculo_id' => $tipoChico->id_tipo_vehiculo,
            'sucursal_id' => $this->sucursal->id_sucursal,
        ]);
        $envioPesado = Envio::factory()->hasta('EN_CENTRO')->create([
            'sucursal_origen_id' => $this->sucursal->id_sucursal,
            'peso_kg' => 150,
        ]);

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload([
                'vehiculo_id' => $vehiculoChico->id_vehiculo,
                'paradas' => [['envio_id' => $envioPesado->id_envio, 'orden' => 1, 'hora_estimada' => null]],
            ]))
            ->assertSessionHasErrors('paradas');

        expect(session('errors')->get('paradas')[0])
            ->toBe('El peso total de los envíos (150.00 kg) supera la capacidad del vehículo (100.00 kg).');
    }

    public function test_rechaza_envios_de_otra_sucursal_de_origen(): void
    {
        $otraSucursal = Sucursal::query()->where('id_sucursal', '!=', $this->sucursal->id_sucursal)->firstOrFail();
        $envioAjeno = Envio::factory()->hasta('EN_CENTRO')->create(['sucursal_origen_id' => $otraSucursal->id_sucursal]);

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload([
                'paradas' => [['envio_id' => $envioAjeno->id_envio, 'orden' => 1, 'hora_estimada' => null]],
            ]))
            ->assertSessionHasErrors('paradas');

        expect(session('errors')->get('paradas')[0])
            ->toContain($envioAjeno->codigo_guia)
            ->and(Despacho::count())->toBe(0);
    }
}
