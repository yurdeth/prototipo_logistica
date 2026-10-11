<?php

namespace Database\Seeders;

use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;

class TipoVehiculoSeeder extends Seeder
{
    /**
     * Tipos de vehículo de la flota: nombre, marca de referencia, capacidad en kg y volumen en m³.
     *
     * @var list<array{nombre: string, marca: string, capacidad_kg: float, capacidad_m3: float|null}>
     */
    private const TIPOS = [
        ['nombre' => 'Motocicleta', 'marca' => 'Honda', 'capacidad_kg' => 50.0, 'capacidad_m3' => null],
        ['nombre' => 'Van', 'marca' => 'Toyota', 'capacidad_kg' => 800.0, 'capacidad_m3' => 6.0],
        ['nombre' => 'Camion Ligero', 'marca' => 'Isuzu', 'capacidad_kg' => 3500.0, 'capacidad_m3' => 12.0],
        ['nombre' => 'Camion Pesado', 'marca' => 'Volvo', 'capacidad_kg' => 18000.0, 'capacidad_m3' => 45.0],
    ];

    public function run(): void
    {
        foreach (self::TIPOS as $tipo) {
            TipoVehiculo::updateOrCreate(
                ['nombre' => $tipo['nombre'], 'marca' => $tipo['marca']],
                ['capacidad_kg' => $tipo['capacidad_kg'], 'capacidad_m3' => $tipo['capacidad_m3']],
            );
        }
    }
}
