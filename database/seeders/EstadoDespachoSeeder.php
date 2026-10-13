<?php

namespace Database\Seeders;

use App\Enums\EstadoDespachoEnum;
use App\Models\EstadoDespacho;
use Illuminate\Database\Seeder;

class EstadoDespachoSeeder extends Seeder
{
    /**
     * Catálogo fijo de estados de un despacho; idéntico al que siembra la
     * migración con insertOrIgnore y al enum EstadoDespachoEnum.
     */
    public function run(): void
    {
        foreach (EstadoDespachoEnum::cases() as $estado) {
            EstadoDespacho::updateOrCreate(['nombre_estado' => $estado->value]);
        }
    }
}
