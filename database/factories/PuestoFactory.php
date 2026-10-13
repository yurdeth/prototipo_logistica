<?php

namespace Database\Factories;

use App\Models\Puesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Puesto>
 */
class PuestoFactory extends Factory
{
    /** @var list<string> */
    private const NOMBRES = ['Conductor', 'Auxiliar de Reparto', 'Despachador', 'Supervisor de Sucursal', 'Mecánico'];

    public function definition(): array
    {
        static $indice = 0;

        return [
            'departamento_id' => DepartamentoFactory::llamado('Operaciones')->id_departamento,
            'nombre_puesto' => self::NOMBRES[$indice++ % count(self::NOMBRES)],
            'activo' => true,
        ];
    }

    /** El puesto de Conductor es el que exige licencia y asigna despachos. */
    public static function conductor(): Puesto
    {
        return Puesto::query()->firstOrCreate(
            ['nombre_puesto' => 'Conductor', 'departamento_id' => DepartamentoFactory::llamado('Operaciones')->id_departamento],
        );
    }
}
