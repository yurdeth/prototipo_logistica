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
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        $this->call(EstadoVehiculoSeeder::class);
        $this->call(TipoVehiculoSeeder::class);
        $this->call(MarcaVehiculoSeeder::class);
        $this->call(ModeloVehiculoSeeder::class);
        $this->call(EnviosSeeder::class);

        // los estados y tipos de incidencia ya se siembran en sus migraciones
        // con insertOrIgnore; re-ejecutar db:seed no duplica nada
        $this->call(ParametroSeeder::class);

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
