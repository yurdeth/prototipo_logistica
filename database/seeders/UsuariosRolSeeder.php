<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Un usuario por rol, para probar la matriz de permisos en el navegador sin
 * andar cambiando roles a mano en la base. Idempotente y con la misma
 * contraseña para los tres: es prototipo, no producción.
 */
class UsuariosRolSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        foreach (['operador', 'analista'] as $rol) {
            $usuario = User::firstOrCreate(
                ['email' => "{$rol}@demo.test"],
                [
                    'name' => ucfirst($rol).' Demo',
                    'password' => Hash::make('password'),
                ],
            );

            $usuario->roles()->syncWithoutDetaching(
                Role::query()->where('name', $rol)->firstOrFail(),
            );
        }
    }
}
