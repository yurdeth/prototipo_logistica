<?php

namespace Database\Seeders;

use App\Models\EstadoDespacho;
use Illuminate\Database\Seeder;

class EstadoDespachoSeeder extends Seeder
{
    /**
     * Catálogo fijo de estados de un despacho; idéntico al que siembra la
     * migración con insertOrIgnore.
     *
     * @var list<string>
     */
    private const ESTADOS = ['PLANIFICADO', 'EN_CURSO', 'COMPLETADO'];

    public function run(): void
    {
        foreach (self::ESTADOS as $nombre) {
            EstadoDespacho::updateOrCreate(['nombre_estado' => $nombre]);
        }
    }
}
