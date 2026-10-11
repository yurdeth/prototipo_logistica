<?php

namespace Database\Seeders;

use App\Models\MarcaVehiculo;
use App\Models\ModeloVehiculo;
use App\Models\TipoVehiculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModeloVehiculoSeeder extends Seeder
{
    /**
     * Catálogo fijo de modelos por marca. Solo vehículos panel/carga (van, camiones;
     * sin pickups ni sedanes) y motocicletas. Cada modelo indica su tipo de vehículo.
     *
     * @var array<string, array<string, string>>
     */
    private const MODELOS = [
        'Toyota' => ['Hiace' => 'Van'],
        'Nissan' => ['Urvan' => 'Van'],
        'Mitsubishi' => ['Canter' => 'Camion Ligero'],
        'Kia' => ['K2500' => 'Van'],
        'Chevrolet' => ['NHR' => 'Camion Ligero', 'NPR' => 'Camion Ligero'],
        'Honda' => ['CB125' => 'Motocicleta', 'Boxer CT100' => 'Motocicleta'],
        'Suzuki' => ['AX100' => 'Motocicleta'],
        'Yamaha' => ['Crypton' => 'Motocicleta'],
    ];

    public function run(): void
    {
        $idsPermanecentes = [];

        foreach (self::MODELOS as $marca => $modelos) {
            $marcaRegistrada = MarcaVehiculo::where('nombre', $marca)->firstOrFail();

            foreach ($modelos as $nombre => $tipo) {
                $tipoRegistrado = TipoVehiculo::where('nombre', $tipo)->firstOrFail();

                $modelo = ModeloVehiculo::updateOrCreate(
                    ['marca_vehiculo_id' => $marcaRegistrada->id_marca, 'nombre' => $nombre],
                    ['tipo_vehiculo_id' => $tipoRegistrado->id_tipo_vehiculo],
                );
                $idsPermanecentes[] = $modelo->id_modelo;
            }
        }

        // El catálogo es fijo: elimina modelos fuera de él que ninguna pivote referencie
        $referenciados = DB::table('flota.vehiculo_marca_modelo')->pluck('modelo_vehiculo_id');
        ModeloVehiculo::whereNotIn('id_modelo', $idsPermanecentes)
            ->whereNotIn('id_modelo', $referenciados)
            ->delete();
    }
}
