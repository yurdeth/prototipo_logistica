<?php

namespace Database\Factories;

use App\Models\EstadoVehiculo;
use App\Models\ModeloVehiculo;
use App\Models\Sucursal;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    /**
     * Consumo esperado por tipo (km/gal): motocicletas rinden mucho más que
     * un camión pesado; el rango alimenta el indicador de eficiencia. Solo se
     * usa cuando el catálogo marca/modelo está vacío (tests sin seeders).
     *
     * @var array<string, array{marca: string, modelo: string, consumo: array{float, float}}>
     */
    private const PERFIL_TIPO = [
        'Motocicleta' => ['marca' => 'Honda', 'modelo' => 'CB125', 'consumo' => [35.0, 45.0]],
        'Van' => ['marca' => 'Toyota', 'modelo' => 'Hiace', 'consumo' => [15.0, 20.0]],
        'Camion Ligero' => ['marca' => 'Mitsubishi', 'modelo' => 'Canter', 'consumo' => [6.0, 10.0]],
        'Camion Pesado' => ['marca' => 'Mitsubishi', 'modelo' => 'Canter', 'consumo' => [2.5, 4.0]],
    ];

    public function definition(): array
    {
        $tipo = TipoVehiculo::query()->inRandomOrder()->first()
            ?? TipoVehiculo::query()->create(['nombre' => 'Van', 'marca' => 'Toyota', 'capacidad_kg' => 800.0, 'capacidad_m3' => 6.0]);

        $modeloCatalogo = ModeloVehiculo::query()
            ->where('tipo_vehiculo_id', $tipo->id_tipo_vehiculo)
            ->with('marca')
            ->inRandomOrder()
            ->first();

        $perfil = self::PERFIL_TIPO[$tipo->nombre] ?? self::PERFIL_TIPO['Van'];

        $marcaNombre = $modeloCatalogo?->marca?->nombre;
        $modeloNombre = $modeloCatalogo?->nombre;

        return [
            // C de carga (o M de motocicleta) + dígitos: secuencia propia que
            // nunca choca con las placas escritas a mano en los tests.
            'placa' => $this->placaSecuencial($tipo->nombre === 'Motocicleta'),
            'tipo_vehiculo_id' => $tipo->id_tipo_vehiculo,
            'estado_vehiculo_id' => EstadoVehiculo::query()->firstOrCreate(['nombre_estado' => 'DISPONIBLE'])->id_estado,
            // Reparte la flota entre las sucursales ya existentes (si las hay).
            'sucursal_id' => (Sucursal::query()->inRandomOrder()->first() ?? Sucursal::factory()->create())->id_sucursal,
            'marca' => $marcaNombre !== null ? $marcaNombre : $perfil['marca'],
            'modelo' => $modeloNombre !== null ? $modeloNombre : $perfil['modelo'],
            'anio' => (int) fake()->numberBetween(2015, 2026),
            'consumo_km_gal' => fake()->randomFloat(2, $perfil['consumo'][0], $perfil['consumo'][1]),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Vehiculo $vehiculo): void {
            // El CRUD hidrata marca/modelo desde la pivote flota.vehiculo_marca_modelo,
            // no desde las columnas de texto: sin fila ahí la vista de edición
            // muestra el vehículo "Sin asignar". Se resuelve por nombre del
            // catálogo; si no existe (tests sin seeders), queda sin pivote.
            $modelo = ModeloVehiculo::query()
                ->where('nombre', $vehiculo->modelo)
                ->whereHas('marca', fn ($q) => $q->where('nombre', $vehiculo->marca))
                ->first();

            if ($modelo !== null) {
                $vehiculo->modelos()->attach($modelo->id_modelo, ['marca_vehiculo_id' => $modelo->marca_vehiculo_id]);
            }
        });
    }

    /** Placa única incremental; $contador persiste entre tests del mismo proceso. */
    private function placaSecuencial(bool $motocicleta): string
    {
        static $contador = 0;

        return ($motocicleta ? 'M' : 'C').str_pad((string) (++$contador + 800000), 6, '0', STR_PAD_LEFT);
    }
}
