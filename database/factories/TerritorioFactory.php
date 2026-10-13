<?php

namespace Database\Factories;

use App\Models\Territorio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Territorio>
 */
class TerritorioFactory extends Factory
{
    /** Departamentos reales del país; la lista cubre la red de sucursales. */
    public const DEPARTAMENTOS = [
        'San Salvador', 'Santa Ana', 'San Miguel', 'Sonsonate', 'Usulután',
        'La Libertad', 'La Unión', 'San Vicente', 'Chalatenango',
    ];

    public function definition(): array
    {
        return [
            'nombre_territorio' => fake()->unique()->randomElement(self::DEPARTAMENTOS),
            'tipo_territorio' => 'DEPARTAMENTO',
        ];
    }

    /**
     * Devuelve (creándolo si hace falta) el departamento con ese nombre; las
     * sucursales de una misma ciudad deben compartir territorio.
     */
    public static function departamento(string $nombre): Territorio
    {
        return Territorio::query()->firstOrCreate(
            ['nombre_territorio' => $nombre],
            ['tipo_territorio' => 'DEPARTAMENTO'],
        );
    }
}
