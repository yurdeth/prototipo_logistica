<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden garantizado: primero los catálogos (listas fijas que todo lo
     * demás referencia), después los datos. Todos los seeders de catálogo
     * son idempotentes (updateOrCreate/firstOrCreate): se puede re-ejecutar
     * `php artisan db:seed` sin duplicar filas.
     *
     * Sin WithoutModelEvents: la historia simulada depende de los hooks del
     * modelo Envio para generar el rastro de eventos.
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

        /* ── 2. Red logística e historia operativa ── */
        $this->call(AdminUserSeeder::class);
        $this->call(RedLogisticaSeeder::class);
        $this->call(HistoriaSeeder::class);

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
