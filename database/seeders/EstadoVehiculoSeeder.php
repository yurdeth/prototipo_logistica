<?php

namespace Database\Seeders;

use App\Models\EstadoVehiculo;
use Illuminate\Database\Seeder;

class EstadoVehiculoSeeder extends Seeder
{
    /**
     * Estados operativos de la flota según el documento de análisis.
     * INACTIVO se usa para dar de baja un vehículo sin borrar su historial.
     *
     * @var list<string>
     */
    private const ESTADOS = ['DISPONIBLE', 'EN RUTA', 'MANTENIMIENTO', 'INACTIVO'];

    public function run(): void
    {
        foreach (self::ESTADOS as $nombre) {
            EstadoVehiculo::updateOrCreate(['nombre_estado' => $nombre]);
        }
    }
}
