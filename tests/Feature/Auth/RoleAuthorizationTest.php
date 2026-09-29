<?php

use App\Models\Role;
use App\Models\User;
use Laravel\Passport\ClientRepository;

beforeEach(function () {
    (new ClientRepository)->createPersonalAccessGrantClient('test');
});

function apiUserWithRole(string $role): array
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::firstOrCreate(['name' => $role]));

    return [$user, $user->createToken('api')->accessToken];
}

test('admin can access admin only route', function () {
    [, $token] = apiUserWithRole('admin');

    $this->getJson('/api/admin/only', ['Authorization' => "Bearer {$token}"])
        ->assertOk();
});

test('analista cannot access admin only route', function () {
    [, $token] = apiUserWithRole('analista');

    $this->getJson('/api/admin/only', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(403);
});

test('unauthenticated request to protected route returns 401', function () {
    $this->getJson('/api/admin/only')
        ->assertStatus(401);
});
