<?php

namespace Database\Seeders;

use App\Models\TipoIncidencia;
use Illuminate\Database\Seeder;

class TipoIncidenciaSeeder extends Seeder
{
    /**
     * Catálogo fijo de tipos de incidencia; idéntico al que siembra la
     * migración con insertOrIgnore.
     *
     * @var list<string>
     */
    private const TIPOS = [
        'FALLA_MECANICA',
        'TRANSITO',
        'CLIMA',
        'CLIENTE_AUSENTE',
        'DIRECCION_ERRONEA',
        'OTRO',
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $nombre) {
            TipoIncidencia::updateOrCreate(['nombre_tipo' => $nombre]);
        }
    }
}
