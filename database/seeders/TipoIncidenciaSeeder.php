<?php

namespace Database\Seeders;

use App\Enums\TipoIncidenciaEnum;
use App\Models\TipoIncidencia;
use Illuminate\Database\Seeder;

class TipoIncidenciaSeeder extends Seeder
{
    /**
     * Catálogo fijo de tipos de incidencia; idéntico al que siembra la
     * migración con insertOrIgnore y al enum TipoIncidenciaEnum.
     */
    public function run(): void
    {
        foreach (TipoIncidenciaEnum::cases() as $tipo) {
            TipoIncidencia::updateOrCreate(['nombre_tipo' => $tipo->value]);
        }
    }
}
