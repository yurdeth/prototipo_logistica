<?php

namespace Database\Seeders;

use App\Models\Parametro;
use Illuminate\Database\Seeder;

/**
 * Parámetros operativos versionados (rutas.parametros): cada clave puede
 * tener varias filas con vigencia; aquí se garantiza que la clave exista.
 *
 * penalizacion_congestion: minutos adicionales que el cálculo de ruta aplica
 * a un tramo marcado como congestionado (escenario de congestión de la demo).
 */
class ParametroSeeder extends Seeder
{
    /**
     * @var array<string, float>
     */
    private const PARAMETROS = [
        'penalizacion_congestion' => 15.0,
    ];

    public function run(): void
    {
        foreach (self::PARAMETROS as $clave => $valor) {
            Parametro::updateOrCreate(
                ['clave' => $clave],
                ['valor' => $valor]
            );
        }
    }
}
