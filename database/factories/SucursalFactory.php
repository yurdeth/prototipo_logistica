<?php

namespace Database\Factories;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sucursal>
 */
class SucursalFactory extends Factory
{
    /**
     * Cabeceras departamentales con coordenadas reales: la distancia PostGIS
     * entre sucursales (ST_DistanceSphere) solo tiene sentido si estos puntos
     * caen donde la geografía dice.
     *
     * @var list<array{ciudad: string, lat: float, lng: float, direccion: string}>
     */
    public const UBICACIONES = [
        ['ciudad' => 'San Salvador', 'lat' => 13.692900, 'lng' => -89.218200, 'direccion' => 'Alameda Juan Pablo II, San Salvador'],
        ['ciudad' => 'Santa Ana', 'lat' => 13.994200, 'lng' => -89.559700, 'direccion' => 'Avenida Independencia Sur, Santa Ana'],
        ['ciudad' => 'San Miguel', 'lat' => 13.483300, 'lng' => -88.177800, 'direccion' => 'Avenida Roosevelt, San Miguel'],
        ['ciudad' => 'Sonsonate', 'lat' => 13.718900, 'lng' => -89.724200, 'direccion' => 'Calle Enrique Munoz, Sonsonate'],
        ['ciudad' => 'Usulután', 'lat' => 13.444400, 'lng' => -88.437200, 'direccion' => 'Carretera Nacional, Usulután'],
        ['ciudad' => 'La Unión', 'lat' => 13.336900, 'lng' => -87.856200, 'direccion' => 'Avenida Agustin Reyes, La Unión'],
        ['ciudad' => 'San Vicente', 'lat' => 13.583300, 'lng' => -88.750000, 'direccion' => 'Barrio El Calvario, San Vicente'],
        ['ciudad' => 'Chalatenango', 'lat' => 14.035000, 'lng' => -89.150000, 'direccion' => 'Calle Jose Simeon Canas, Chalatenango'],
    ];

    /** Límites del territorio nacional (bounding box) para validar coordenadas. */
    public const LATITUD_MIN = 12.9;

    public const LATITUD_MAX = 14.5;

    public const LONGITUD_MIN = -90.2;

    public const LONGITUD_MAX = -87.6;

    public function definition(): array
    {
        $lugar = fake()->randomElement(self::UBICACIONES);

        return [
            'codigo' => 'SUC-'.fake()->unique()->regexify('[A-Z]{4}'),
            'nombre_sucursal' => 'Sucursal '.$lugar['ciudad'],
            'territorio_id' => TerritorioFactory::departamento($lugar['ciudad'])->id_territorio,
            'direccion' => $lugar['direccion'],
            'latitud' => $lugar['lat'],
            'longitud' => $lugar['lng'],
            'activa' => true,
        ];
    }
}
