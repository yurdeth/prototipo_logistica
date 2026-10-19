<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Zonas de DEMOSTRACIÓN: un círculo aproximado alrededor del centro de cada territorio.
 * Sirven para ver el mapa de inmediato; después se pueden redibujar desde el sistema
 * (Rutas > Mapa > Editar zonas). No pisa las zonas que ya existan.
 *
 *   php artisan db:seed --class=ZonasTerritoriosSeeder
 */
class ZonasTerritoriosSeeder extends Seeder
{
    public function run(): void
    {
        // [territorio, latitud, longitud, radio en metros]
        $zonas = [
            ['San Salvador', 13.6929, -89.2182, 6000],
            ['Santa Tecla', 13.6769, -89.2797, 4500],
            ['Soyapango', 13.7103, -89.1397, 4000],
            ['San Miguel', 13.4833, -88.1833, 7000],
            ['Santa Ana', 13.9942, -89.5597, 7000],
            ['Usulután', 13.3500, -88.4500, 6000],
            ['Sonsonate', 13.7189, -89.7242, 6000],
            ['La Unión', 13.3369, -87.8439, 6000],
        ];

        foreach ($zonas as [$nombre, $lat, $lng, $radio]) {
            DB::update(
                'UPDATE territorio.territorios
                 SET zona = ST_Buffer(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?, 12)
                 WHERE nombre_territorio = ? AND zona IS NULL',
                [$lng, $lat, $radio, $nombre]
            );
        }
    }
}
