<?php

namespace Tests\Feature\Flota;

use App\Models\User;
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

    public function test_invitado_no_recibe_token_y_la_cookie_se_expira(): void
    {
        $respuesta = $this->get('/vehiculos');

        $cookie = $respuesta->getCookie('api_token', false);
        expect($cookie?->getMaxAge() ?? 0)->toBe(0);
    }
}
