<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Orden garantizado: primero los catálogos (listas fijas que todo lo
     * demás referencia), después los datos. Todos los seeders de catálogo
     * son idempotentes (updateOrCreate/firstOrCreate): se puede re-ejecutar
     * `php artisan db:seed` sin duplicar filas.
     */
    public function run(): void
    {
        /* ── 1. Catálogos ── */
        $this->call(EstadoVehiculoSeeder::class);
        $this->call(TipoVehiculoSeeder::class);
        $this->call(MarcaVehiculoSeeder::class);
        $this->call(ModeloVehiculoSeeder::class);
        $this->call(EstadoEnvioSeeder::class);
        $this->call(EstadoDespachoSeeder::class);
        $this->call(TipoIncidenciaSeeder::class);
        $this->call(RoleSeeder::class);
        $this->call(ParametroSeeder::class);

        /* ── 2. Datos ── */
        $this->call(AdminUserSeeder::class);
        $this->call(EnviosSeeder::class);

        // User::factory(10)->create();

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
            ],
        );
    }
}
