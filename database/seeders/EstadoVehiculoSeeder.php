<?php

namespace Database\Seeders;

use App\Models\EstadoVehiculo;
use Illuminate\Database\Seeder;

class EstadoVehiculoSeeder extends Seeder
{
    /**
     * Estados operativos de la flota según el documento de análisis.
     *
     * @var list<string>
     */
    private const ESTADOS = ['DISPONIBLE', 'EN RUTA', 'MANTENIMIENTO'];

    public function run(): void
    {
        foreach (self::ESTADOS as $nombre) {
            EstadoVehiculo::updateOrCreate(['nombre_estado' => $nombre]);
        }
    }
}
