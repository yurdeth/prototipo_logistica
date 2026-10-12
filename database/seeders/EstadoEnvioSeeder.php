<?php

namespace Database\Seeders;

use App\Models\EstadoEnvio;
use Illuminate\Database\Seeder;

class EstadoEnvioSeeder extends Seeder
{
    /**
     * Catálogo fijo de estados del ciclo de vida de un envío; idéntico al
     * que siembra la migración con insertOrIgnore.
     *
     * @var list<string>
     */
    private const ESTADOS = [
        'REGISTRADO',
        'RECOLECTADO',
        'EN_TRANSITO',
        'EN_CENTRO',
        'EN_RUTA',
        'ENTREGADO',
        'DEVUELTO',
        'FALLIDO',
    ];

    public function run(): void
    {
        foreach (self::ESTADOS as $nombre) {
            EstadoEnvio::updateOrCreate(['nombre_estado' => $nombre]);
        }
    }
}
