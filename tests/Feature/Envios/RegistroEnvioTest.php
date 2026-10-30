<?php

namespace Tests\Feature\Envios;

use App\Models\Cliente;
use App\Models\Envio;
use App\Models\Role;
use App\Models\Sucursal;
use App\Models\User;
use Database\Seeders\RedLogisticaSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistroEnvioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(RedLogisticaSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::query()->where('name', 'admin')->value('id'));
    }

    /** Carga útil válida: origen en San Miguel, destino en otro departamento. */
    private function payload(array $sobrescribir = []): array
    {
        $origen = Sucursal::query()->where('codigo', 'SUC-SMG')->firstOrFail();
        $otroTerritorio = Sucursal::query()
            ->where('id_sucursal', '!=', $origen->id_sucursal)
            ->where('activa', true)
            ->firstOrFail()
            ->territorio_id;
        $remitente = Cliente::factory()->create();
        $destinatario = Cliente::factory()->create();

        return [
            'cliente_remitente_id' => $remitente->id_cliente,
            'cliente_destinatario_id' => $destinatario->id_cliente,
            'sucursal_origen_id' => $origen->id_sucursal,
            'territorio_destino_id' => $otroTerritorio,
            'direccion_destino' => 'Colonia Escalón, Calle El Sauce #123',
            'peso_kg' => 120,
            'volumen_m3' => 1.5,
            'prioridad' => 'NORMAL',
            'fecha_comprometida' => now()->addDay()->format('Y-m-d\TH:i'),
            ...$sobrescribir,
        ];
    }

    public function test_registra_un_envio_valido(): void
    {
        $this->actingAs($this->admin)
            ->post('/envios', $this->payload())
            ->assertRedirect();

        expect(Envio::count())->toBe(1);
    }

    /** Un envío puede entregarse dentro del mismo territorio de su sucursal de origen. */
    public function test_acepta_destino_en_el_territorio_de_la_sucursal_de_origen(): void
    {
        $origen = Sucursal::query()->where('codigo', 'SUC-SMG')->firstOrFail();

        $this->actingAs($this->admin)
            ->post('/envios', $this->payload(['territorio_destino_id' => $origen->territorio_id]))
            ->assertRedirect();

        expect(Envio::count())->toBe(1);
    }

    /** Tope operativo: capacidad del camión pesado más grande de la flota (18 000 kg, Tabla 3.2). */
    public function test_rechaza_peso_que_excede_el_tope_operativo(): void
    {
        $this->actingAs($this->admin)
            ->post('/envios', $this->payload(['peso_kg' => 18000.01]))
            ->assertSessionHasErrors('peso_kg');

        expect(Envio::count())->toBe(0);
    }

    public function test_acepta_peso_exactamente_en_el_tope(): void
    {
        $this->actingAs($this->admin)
            ->post('/envios', $this->payload(['peso_kg' => 18000, 'volumen_m3' => null]))
            ->assertRedirect();

        expect((float) Envio::sole()->peso_kg)->toBe(18000.0);
    }

    /** Tope del volumen declarado por el negocio. */
    public function test_rechaza_volumen_que_excede_el_tope(): void
    {
        $this->actingAs($this->admin)
            ->post('/envios', $this->payload(['volumen_m3' => 100.01]))
            ->assertSessionHasErrors('volumen_m3');

        expect(Envio::count())->toBe(0);
    }

    public function test_acepta_volumen_exactamente_en_el_tope(): void
    {
        $this->actingAs($this->admin)
            ->post('/envios', $this->payload(['volumen_m3' => 100]))
            ->assertRedirect();

        expect((float) Envio::sole()->volumen_m3)->toBe(100.0);
    }
}
