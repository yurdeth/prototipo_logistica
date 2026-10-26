<?php

use App\Models\Envio;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RedLogisticaSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RedLogisticaSeeder::class);

    $admin = User::factory()->create();
    $admin->roles()->attach(Role::query()->where('name', 'admin')->value('id'));
    $this->admin = $admin;
});

/** Cambia el estado del envío como lo haría el detalle en la UI. */
function cambiarEstado(TestCase $test, Envio $envio, string $estado): mixed
{
    return $test->actingAs($test->admin)
        ->patchJson("/envios/{$envio->id_envio}/estado", ['estado' => $estado]);
}

test('saltar de REGISTRADO a ENTREGADO falla con 422', function () {
    $envio = Envio::factory()->hasta('REGISTRADO')->create();

    cambiarEstado($this, $envio, 'ENTREGADO')
        ->assertStatus(422)
        ->assertJsonValidationErrors('estado');

    expect($envio->refresh()->estado->nombre_estado)->toBe('REGISTRADO');
});

test('la cadena feliz completa el ciclo paso a paso', function () {
    $envio = Envio::factory()->hasta('REGISTRADO')->create();

    foreach (['RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA', 'ENTREGADO'] as $estado) {
        cambiarEstado($this, $envio, $estado)->assertRedirect();
    }

    expect($envio->refresh()->estado->nombre_estado)->toBe('ENTREGADO');
});

test('un envio entregado esta cerrado y no admite mas transiciones', function () {
    $envio = Envio::factory()->hasta('ENTREGADO')->create();

    cambiarEstado($this, $envio, 'EN_RUTA')
        ->assertStatus(422)
        ->assertJsonValidationErrors('estado');
});

test('un envio fallido admite reintento a EN_RUTA', function () {
    $envio = Envio::factory()->hasta('FALLIDO')->create();

    cambiarEstado($this, $envio, 'EN_RUTA')->assertRedirect();

    expect($envio->refresh()->estado->nombre_estado)->toBe('EN_RUTA');
});
