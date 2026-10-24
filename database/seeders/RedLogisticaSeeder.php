<?php

namespace Database\Seeders;

use App\Models\Parametro;
use App\Models\Sucursal;
use Database\Factories\SucursalFactory;
use Database\Factories\TerritorioFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Red logística de la demo: las cabeceras reales de SucursalFactory conectadas
 * por tramos cuyo peso es la distancia geodésica PostGIS entre sucursales
 * (ST_DistanceSphere sobre la columna generada ubicacion), no números inventados.
 *
 * El grafo garantiza dos propiedades que los escenarios de demo necesitan:
 *   - alternativa: entre San Salvador y San Miguel hay ruta directa y otra por
 *     San Vicente/Usulután, para comparar caminos;
 *   - congestión: el tramo San Salvador-Santa Ana (corredor urbano más cargado)
 *     queda marcado como candidato a penalización vía el parámetro versionado
 *     conexion_congestion; la alternativa es pasar por Sonsonate.
 */
class RedLogisticaSeeder extends Seeder
{
    /** Códigos fijos por ciudad, para que despachos y escenarios referencien la red con estabilidad. */
    private const CODIGOS = [
        'San Salvador' => 'SUC-SSS',
        'Santa Ana' => 'SUC-SAN',
        'San Miguel' => 'SUC-SMG',
        'Sonsonate' => 'SUC-SON',
        'Usulután' => 'SUC-USU',
        'La Unión' => 'SUC-LUN',
        'San Vicente' => 'SUC-SVC',
        'Chalatenango' => 'SUC-CHA',
    ];

    /** @var list<array{string, string}> */
    private const ARISTAS = [
        ['San Salvador', 'Santa Ana'],
        ['San Salvador', 'Sonsonate'],
        ['San Salvador', 'Chalatenango'],
        ['San Salvador', 'San Vicente'],
        ['San Salvador', 'San Miguel'],
        ['Sonsonate', 'Santa Ana'],
        ['San Vicente', 'San Miguel'],
        ['San Vicente', 'Usulután'],
        ['San Miguel', 'Usulután'],
        ['San Miguel', 'La Unión'],
        ['Usulután', 'La Unión'],
    ];

    /** @var array{string, string} */
    private const TRAMO_CONGESTION = ['San Salvador', 'Santa Ana'];

    /** Clave del parámetro versionado que señala el tramo congestionado (valor = id_conexion). */
    public const CLAVE_CONGESTION = 'conexion_congestion';

    public function run(): void
    {
        $sucursales = $this->sucursales();

        DB::transaction(function () use ($sucursales): void {
            $this->conexiones($sucursales);
            $this->marcarCongestion($sucursales);
        });
    }

    /**
     * Sucursales de la red, idempotente por código; los pesos dependen de sus
     * coordenadas reales (que vienen de SucursalFactory::UBICACIONES).
     *
     * @return array<string, int>
     */
    private function sucursales(): array
    {
        $ids = [];

        foreach (SucursalFactory::UBICACIONES as $lugar) {
            $sucursal = Sucursal::query()->updateOrCreate(
                ['codigo' => self::CODIGOS[$lugar['ciudad']]],
                [
                    'nombre_sucursal' => 'Sucursal '.$lugar['ciudad'],
                    'territorio_id' => TerritorioFactory::departamento($lugar['ciudad'])->id_territorio,
                    'direccion' => $lugar['direccion'],
                    'latitud' => $lugar['lat'],
                    'longitud' => $lugar['lng'],
                    'activa' => true,
                ],
            );

            $ids[$lugar['ciudad']] = $sucursal->id_sucursal;
        }

        return $ids;
    }

    /**
     * Tramos con peso geodésico real; el tiempo se deriva de la distancia a la
     * velocidad promedio del corredor (los que tocan San Salvador son más lentos).
     *
     * @param  array<string, int>  $sucursales
     */
    private function conexiones(array $sucursales): void
    {
        foreach (self::ARISTAS as [$ciudadOrigen, $ciudadDestino]) {
            // ubicacion es geography: ST_Distance devuelve metros sobre el elipsoide
            $distancia = (float) DB::selectOne(
                'SELECT ST_Distance(a.ubicacion, b.ubicacion) / 1000 AS km
                 FROM territorio.sucursales a, territorio.sucursales b
                 WHERE a.id_sucursal = ? AND b.id_sucursal = ?',
                [$sucursales[$ciudadOrigen], $sucursales[$ciudadDestino]],
            )->km;

            // Los tramos que pasan por el área urbana de San Salvador son más lentos.
            $tocaCapital = in_array('San Salvador', [$ciudadOrigen, $ciudadDestino], true);
            $velocidad = $tocaCapital ? 45 : 65;

            DB::table('rutas.conexiones')->updateOrInsert(
                ['origen_sucursal_id' => $sucursales[$ciudadOrigen], 'destino_sucursal_id' => $sucursales[$ciudadDestino]],
                [
                    'distancia_km' => round($distancia, 2),
                    'tiempo_min' => max(1, (int) round($distancia / $velocidad * 60)),
                    'activa' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * Señala el tramo congestionado como parámetro versionado; el escenario de
     * congestión (HistoriaSeeder) lo re-versiona al día simulado.
     *
     * @param  array<string, int>  $sucursales
     */
    private function marcarCongestion(array $sucursales): void
    {
        $idConexion = DB::table('rutas.conexiones')
            ->where('origen_sucursal_id', $sucursales[self::TRAMO_CONGESTION[0]])
            ->where('destino_sucursal_id', $sucursales[self::TRAMO_CONGESTION[1]])
            ->value('id_conexion');

        if ($idConexion !== null) {
            Parametro::query()->updateOrCreate(
                ['clave' => self::CLAVE_CONGESTION, 'vigente_desde' => now()->toDateString()],
                ['valor' => $idConexion],
            );
        }
    }
}
