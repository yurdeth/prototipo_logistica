<?php

namespace Database\Seeders;

use App\Models\MarcaVehiculo;
use Illuminate\Database\Seeder;

class MarcaVehiculoSeeder extends Seeder
{
    /**
     * Catálogo fijo de marcas: vehículos de carga/panel y motocicletas.
     *
     * @var list<string>
     */
    private const MARCAS = [
        'Toyota',
        'Nissan',
        'Mitsubishi',
        'Kia',
        'Chevrolet',
        'Honda',
        'Suzuki',
        'Yamaha',
    ];

    public function run(): void
    {
        foreach (self::MARCAS as $nombre) {
            MarcaVehiculo::updateOrCreate(['nombre' => $nombre]);
        }
    }
}
