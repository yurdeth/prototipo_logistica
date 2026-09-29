<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\ClientRepository;

beforeEach(function () {
    (new ClientRepository)->createPersonalAccessGrantClient('test');
});

test('api login returns a bearer token with user roles', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'roles']]);

    expect($response->json('token_type'))->toBe('Bearer')
        ->and($response->json('user.email'))->toBe($user->email)
        ->and($response->json('user.roles'))->toBeArray();
});

test('api login rejects a wrong password', function () {
    $user = User::factory()->create();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

test('api login requires email and password', function () {
    $this->postJson('/api/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('api logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->accessToken;

    $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$token}"])
        ->assertOk();

    // TokenGuard caches the resolved user; force a fresh guard for the next request.
    Auth::forgetGuards();

    $this->postJson('/api/logout', [], ['Authorization' => "Bearer {$token}"])
        ->assertStatus(401);
});
