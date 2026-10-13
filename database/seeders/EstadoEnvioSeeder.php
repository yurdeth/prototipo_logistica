<?php

namespace Database\Seeders;

use App\Enums\EstadoEnvioEnum;
use App\Models\EstadoEnvio;
use Illuminate\Database\Seeder;

class EstadoEnvioSeeder extends Seeder
{
    /**
     * Catálogo fijo de estados del ciclo de vida de un envío; idéntico al
     * que siembra la migración con insertOrIgnore y al enum EstadoEnvioEnum.
     */
    public function run(): void
    {
        foreach (EstadoEnvioEnum::cases() as $estado) {
            EstadoEnvio::updateOrCreate(['nombre_estado' => $estado->value]);
        }
    }
}
