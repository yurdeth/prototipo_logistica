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
use Database\Seeders\ParametroSeeder;
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
                    ->has('conexiones', 11)
                    ->where('permisos.gestionar', true),
            );
    }

    /** El analista consulta el índice pero no ve la acción de crear despachos. */
    public function test_el_analista_no_tiene_permiso_de_gestionar_despachos_en_el_indice(): void
    {
        $analista = $this->usuarioConRol('analista');

        $this->actingAs($analista)
            ->get('/rutas')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('rutas/RutasIndex')
                    ->where('permisos.gestionar', false),
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

    /** El formulario sigue ofreciendo al ocupado (la reserva es por fecha; el front deshabilita según el día elegido). */
    public function test_muestra_conductor_y_vehiculo_aunque_tengan_despacho_sin_cerrar(): void
    {
        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload())
            ->assertRedirect(route('rutas.index'));

        $this->actingAs($this->operador)
            ->get('/rutas/crear')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('conductores', fn ($c) => $c->count() === 1)
                    ->where('vehiculos', fn ($v) => $v->count() === 1)
                    ->where('ocupados', fn ($o) => $o->count() === 1),
            );
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

    public function test_la_busqueda_encuentra_envios_por_guia_sin_importar_mayusculas(): void
    {
        $envio = $this->envioDeLaSucursal();

        $respuesta = $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?q='.urlencode(substr($envio->codigo_guia, 3)))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        expect($respuesta->json('data.0.id_envio'))->toBe($envio->id_envio)
            ->and($respuesta->json('data.0.codigo_guia'))->toBe($envio->codigo_guia)
            ->and(array_keys($respuesta->json('data.0')))->toBe([
                'id_envio', 'codigo_guia', 'destino', 'peso_kg', 'prioridad', 'fecha_comprometida', 'sucursal_origen_id',
            ]);
    }

    /** El paginador acepta la sucursal del despacho y solo devuelve su origen. */
    public function test_la_busqueda_filtra_por_sucursal_de_origen(): void
    {
        $envioPropio = $this->envioDeLaSucursal();
        $otraSucursal = Sucursal::query()->where('id_sucursal', '!=', $this->sucursal->id_sucursal)->firstOrFail();
        $envioAjeno = Envio::factory()->hasta('EN_CENTRO')->create(['sucursal_origen_id' => $otraSucursal->id_sucursal]);

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?sucursal_id='.$this->sucursal->id_sucursal)
            ->assertOk()
            ->assertJsonFragment(['id_envio' => $envioPropio->id_envio])
            ->assertJsonMissing(['id_envio' => $envioAjeno->id_envio]);

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?sucursal_id='.$otraSucursal->id_sucursal)
            ->assertOk()
            ->assertJsonFragment(['id_envio' => $envioAjeno->id_envio])
            ->assertJsonMissing(['id_envio' => $envioPropio->id_envio]);
    }

    public function test_la_busqueda_encuentra_envios_por_territorio_de_destino(): void
    {
        $envio = $this->envioDeLaSucursal();
        $sanMiguel = DB::table('territorio.territorios')->where('nombre_territorio', 'San Miguel')->value('id_territorio');
        DB::table('envios.envios')->where('id_envio', $envio->id_envio)->update(['territorio_destino_id' => $sanMiguel]);

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?q=san%20miguel')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_envio', $envio->id_envio)
            ->assertJsonPath('data.0.destino', 'San Miguel');
    }

    public function test_la_busqueda_excluye_envios_entregados_y_despachados(): void
    {
        $envio = $this->envioDeLaSucursal();

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload(['paradas' => [
                ['envio_id' => $envio->id_envio, 'orden' => 1, 'hora_estimada' => null],
            ]]))
            ->assertRedirect(route('rutas.index'));

        $entregado = $this->envioDeLaSucursal();
        DB::table('envios.envios')
            ->where('id_envio', $entregado->id_envio)
            ->update(['estado_envio_id' => DB::table('envios.estados_envios')->where('nombre_estado', 'ENTREGADO')->value('id_estado')]);

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar')
            ->assertOk()
            ->assertJsonMissing(['id_envio' => $envio->id_envio])
            ->assertJsonMissing(['id_envio' => $entregado->id_envio]);
    }

    public function test_sin_permiso_no_puede_buscar_envios(): void
    {
        $analista = $this->usuarioConRol('analista');

        $this->actingAs($analista)
            ->getJson('/rutas/envios/buscar?q=PK')
            ->assertForbidden();
    }

    public function test_el_listado_de_disponibles_se_pagina_en_el_servidor(): void
    {
        Envio::factory()->hasta('EN_CENTRO')->count(21)->create(['sucursal_origen_id' => $this->sucursal->id_sucursal]);

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?page=1')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('hay_mas', true);

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('hay_mas', false);
    }

    /** Un envío con parada registrada en cualquier despacho deja de ofrecerse. */
    public function test_el_listado_de_disponibles_excluye_envios_ya_despachados(): void
    {
        $asignado = $this->envioDeLaSucursal();
        $libre = $this->envioDeLaSucursal();

        $this->actingAs($this->operador)
            ->post('/rutas', $this->payload([
                'paradas' => [['envio_id' => $asignado->id_envio, 'orden' => 1, 'hora_estimada' => null]],
            ]))
            ->assertRedirect(route('rutas.index'));

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?q='.rawurlencode($libre->codigo_guia))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_envio', $libre->id_envio);

        $this->actingAs($this->operador)
            ->getJson('/rutas/envios/buscar?q='.rawurlencode($asignado->codigo_guia))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /* Ejecución del despacho: avance de estado e incidencias. */

    private function estadoDespachoId(string $nombre): int
    {
        return (int) DB::table('rutas.estados_despachos')->where('nombre_estado', $nombre)->value('id_estado');
    }

    /** El despacho recién programado por el operador. */
    private function despachoReciente(): Despacho
    {
        $this->actingAs($this->operador)->post('/rutas', $this->payload())->assertRedirect();

        return Despacho::query()->latest('id_despacho')->first();
    }

    /** Fila vigente del despacho (los asserts con esquema.tabla van por DB::table). */
    private function filaDespacho(Despacho $despacho): object
    {
        return DB::table('rutas.despachos')->where('id_despacho', $despacho->id_despacho)->first();
    }

    public function test_el_despacho_avanza_de_planificado_a_completado_con_su_ejecucion(): void
    {
        $despacho = $this->despachoReciente();
        $parada = $despacho->paradas()->first();

        $this->post("/rutas/{$despacho->id_despacho}/iniciar")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($this->estadoDespachoId('EN_CURSO'), (int) $this->filaDespacho($despacho)->estado_despacho_id);

        $this->post("/rutas/{$despacho->id_despacho}/finalizar", [
            'km_recorridos' => 128.4,
            'paradas' => [
                ['id_parada' => $parada->id_parada, 'hora_real' => now()->setTime(9, 25)->toISOString()],
            ],
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fila = $this->filaDespacho($despacho);
        $this->assertSame($this->estadoDespachoId('COMPLETADO'), (int) $fila->estado_despacho_id);
        $this->assertSame(128.4, (float) $fila->km_recorridos);

        $this->assertNotNull(
            DB::table('rutas.despacho_paradas')->where('id_parada', $parada->id_parada)->value('hora_real')
        );
    }

    public function test_no_se_puede_finalizar_un_despacho_que_no_esta_en_curso(): void
    {
        $despacho = $this->despachoReciente();

        $this->post("/rutas/{$despacho->id_despacho}/finalizar", ['km_recorridos' => 10])
            ->assertSessionHasErrors('estado');

        $this->assertSame($this->estadoDespachoId('PLANIFICADO'), (int) $this->filaDespacho($despacho)->estado_despacho_id);
    }

    public function test_la_hora_real_de_una_parada_no_puede_ser_anterior_a_la_estimada(): void
    {
        $despacho = $this->despachoReciente();
        $parada = $despacho->paradas()->first();

        $this->post("/rutas/{$despacho->id_despacho}/iniciar")->assertSessionHas('success');

        $this->post("/rutas/{$despacho->id_despacho}/finalizar", [
            'paradas' => [
                // la parada se estimó a las 09:00 en payload()
                ['id_parada' => $parada->id_parada, 'hora_real' => now()->setTime(8, 0)->toISOString()],
            ],
        ])->assertSessionHasErrors();

        $this->assertSame($this->estadoDespachoId('EN_CURSO'), (int) $this->filaDespacho($despacho)->estado_despacho_id);
    }

    public function test_registra_una_incidencia_asociada_al_despacho(): void
    {
        $despacho = $this->despachoReciente();
        $envioId = $despacho->paradas()->first()->envio_id;
        $tipoId = (int) DB::table('rutas.tipos_incidencias')->where('nombre_tipo', 'TRANSITO')->value('id_tipo');

        $this->post("/rutas/{$despacho->id_despacho}/incidencias", [
            'tipo_incidencia_id' => $tipoId,
            'minutos_retraso' => 25,
            'envio_id' => $envioId,
            'descripcion' => 'Tránsito denso a la salida',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $incidencia = DB::table('rutas.incidencias')
            ->where('despacho_id', $despacho->id_despacho)
            ->first();

        $this->assertNotNull($incidencia);
        $this->assertSame($envioId, $incidencia->envio_id);
        $this->assertSame($tipoId, $incidencia->tipo_incidencia_id);
        $this->assertSame(25, $incidencia->minutos_retraso);
    }

    public function test_rechaza_una_incidencia_sobre_un_envio_ajeno_al_despacho(): void
    {
        $despacho = $this->despachoReciente();
        $ajeno = $this->envioDeLaSucursal();
        $tipoId = (int) DB::table('rutas.tipos_incidencias')->where('nombre_tipo', 'CLIMA')->value('id_tipo');

        $this->post("/rutas/{$despacho->id_despacho}/incidencias", [
            'tipo_incidencia_id' => $tipoId,
            'minutos_retraso' => 10,
            'envio_id' => $ajeno->id_envio,
        ])->assertSessionHasErrors('envio_id');

        $this->assertNull(
            DB::table('rutas.incidencias')
                ->where('despacho_id', $despacho->id_despacho)
                ->where('envio_id', $ajeno->id_envio)
                ->first()
        );
    }

    public function test_el_analista_no_puede_registrar_incidencias_ni_avanzar_despachos(): void
    {
        $analista = $this->usuarioConRol('analista');
        $despacho = $this->despachoReciente();

        $this->actingAs($analista)
            ->post("/rutas/{$despacho->id_despacho}/incidencias", [
                'tipo_incidencia_id' => 1,
                'minutos_retraso' => 5,
            ])
            ->assertForbidden();

        $this->actingAs($analista)
            ->post("/rutas/{$despacho->id_despacho}/iniciar")
            ->assertForbidden();

        $this->assertSame($this->estadoDespachoId('PLANIFICADO'), (int) $this->filaDespacho($despacho)->estado_despacho_id);
    }

    public function test_el_formulario_toma_el_costo_por_km_del_parametro(): void
    {
        $this->seed(ParametroSeeder::class);

        $this->actingAs($this->operador)
            ->get('/rutas/crear')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->where('vehiculos.0.costo_km', 1.25)
            );
    }
}
