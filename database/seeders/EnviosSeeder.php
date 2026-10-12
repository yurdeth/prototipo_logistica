<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnviosSeeder extends Seeder
{
    /** Cantidad de envíos de demostración a generar. */
    private const TOTAL_ENVIOS = 60;

    public function run(): void
    {
        DB::transaction(function (): void {
            $ahora = now();
            /*
            |------------------------------------------------------------------
            | 1. TERRITORIOS
            |------------------------------------------------------------------
            */
            $territorios = [
                ['San Salvador', 'DEPARTAMENTO'],
                ['Santa Tecla', 'MUNICIPIO'],
                ['Soyapango', 'MUNICIPIO'],
                ['San Miguel', 'DEPARTAMENTO'],
                ['Santa Ana', 'DEPARTAMENTO'],
                ['Usulután', 'DEPARTAMENTO'],
                ['Sonsonate', 'DEPARTAMENTO'],
                ['La Unión', 'DEPARTAMENTO'],
            ];

            foreach ($territorios as [$nombre, $tipo]) {
                DB::table('territorio.territorios')->updateOrInsert(
                    ['nombre_territorio' => $nombre],
                    ['tipo_territorio' => $tipo]
                );
            }

            $idsTerritorios = DB::table('territorio.territorios')->pluck('id_territorio', 'nombre_territorio');

            /*
            |------------------------------------------------------------------
            | 2. SUCURSALES  [codigo, nombre, territorio, direccion, lat, lng]
            |------------------------------------------------------------------
            */
            $sucursales = [
                ['SUC-CEN', 'Sucursal Central', 'San Salvador', 'Boulevard de los Héroes, San Salvador', 13.698900, -89.191400],
                ['SUC-ORI', 'Sucursal Oriente', 'San Miguel', 'Avenida Roosevelt, San Miguel', 13.483300, -88.177800],
                ['SUC-OCC', 'Sucursal Occidente', 'Santa Ana', 'Calle Libertad, Santa Ana', 13.994200, -89.559700],
            ];

            $coordenadas = []; // codigo => [lat, lng], para ubicar el primer evento de cada envío

            foreach ($sucursales as [$codigo, $nombre, $territorio, $direccion, $latitud, $longitud]) {
                DB::table('territorio.sucursales')->updateOrInsert(
                    ['codigo' => $codigo],
                    [
                        'nombre_sucursal' => $nombre,
                        'territorio_id' => $idsTerritorios[$territorio],
                        'direccion' => $direccion,
                        'latitud' => $latitud,
                        'longitud' => $longitud,
                        'activa' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]
                );
                $coordenadas[$codigo] = [$latitud, $longitud];
            }

            $idsSucursales = DB::table('territorio.sucursales')->pluck('id_sucursal', 'codigo');

            /*
            |------------------------------------------------------------------
            | 3. CLIENTES
            |------------------------------------------------------------------
            */
            $clientes = [
                ['Distribuidora Andina', 'EMPRESA', 'San Salvador'],
                ['Comercial Sur', 'EMPRESA', 'Usulután'],
                ['Importadora Norte', 'EMPRESA', 'Santa Ana'],
                ['Farmacias Vida', 'EMPRESA', 'Santa Tecla'],
                ['Ferretería Central', 'EMPRESA', 'Soyapango'],
                ['María López', 'PERSONA', 'San Salvador'],
                ['Carlos Pérez', 'PERSONA', 'San Miguel'],
                ['Ana Rivas', 'PERSONA', 'Sonsonate'],
            ];

            foreach ($clientes as [$nombre, $tipo, $territorio]) {
                DB::table('envios.clientes')->updateOrInsert(
                    ['nombre' => $nombre],
                    [
                        'tipo_cliente' => $tipo,
                        'contacto' => null,
                        'territorio_id' => $idsTerritorios[$territorio],
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ]
                );
            }

            $idsClientes = DB::table('envios.clientes')->pluck('id_cliente', 'nombre');

            /*
            |------------------------------------------------------------------
            | 4. ESTADOS DE ENVÍOS
            |------------------------------------------------------------------
            */
            foreach (['REGISTRADO', 'RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA', 'ENTREGADO', 'DEVUELTO', 'FALLIDO'] as $estado) {
                DB::table('envios.estados_envios')->insertOrIgnore(['nombre_estado' => $estado]);
            }

            $idsEstados = DB::table('envios.estados_envios')->pluck('id_estado', 'nombre_estado');

            /*
            |------------------------------------------------------------------
            | 5. ENVÍOS DE EJEMPLO (con historial de eventos)
            |------------------------------------------------------------------
            */
            // Se eliminan los 3 envíos de la versión anterior del seeder (GUIA-000X);
            // sus eventos se borran en cascada.
            DB::table('envios.envios')->where('codigo_guia', 'like', 'GUIA-%')->delete();

            // Los envíos generados se crean una sola vez: si ya hay datos, no se agregan más.
            if (DB::table('envios.envios')->exists()) {
                $this->command?->info('Ya existen envíos: no se generan más.');

                return;
            }

            mt_srand(2026); // semilla fija: siempre se generan los mismos datos
            $pick = fn (array $lista) => $lista[mt_rand(0, count($lista) - 1)];

            $nombresClientes = $idsClientes->keys()->all();
            $codigosSucursal = array_keys($coordenadas);
            $nombresTerritorios = $idsTerritorios->keys()->all();
            $colonias = ['Escalón', 'Centro', 'Las Palmas', 'San Benito', 'Miramonte', 'Cumbres', 'Jardines'];
            $flujo = ['REGISTRADO', 'RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA'];

            for ($i = 0; $i < self::TOTAL_ENVIOS; $i++) {
                // Fechas: registrado en los últimos 30 días; compromiso entre 24 y 96 h después
                $registro = $ahora->copy()->subMinutes(mt_rand(30, 30 * 24 * 60));
                $compromiso = $registro->copy()->addHours(24 + mt_rand(0, 72));
                $edadHoras = ($ahora->timestamp - $registro->timestamp) / 3600;

                // Estado según la antigüedad (algunos quedan abiertos y vencidos a propósito)
                $r = mt_rand(1, 100);
                if ($edadHoras < 6) {
                    $estado = $pick(['REGISTRADO', 'RECOLECTADO']);
                } elseif ($edadHoras < 36) {
                    $estado = $pick(['RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA']);
                } elseif ($r <= 78) {
                    $estado = 'ENTREGADO';
                } elseif ($r <= 86) {
                    $estado = 'FALLIDO';
                } elseif ($r <= 92) {
                    $estado = 'DEVUELTO';
                } else {
                    $estado = $pick(['EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA']);
                }

                // Remitente y destinatario distintos (regla del sistema)
                $remitente = $pick($nombresClientes);
                do {
                    $destinatario = $pick($nombresClientes);
                } while ($destinatario === $remitente);

                $sucursal = $pick($codigosSucursal);
                $territorio = $pick($nombresTerritorios);
                $rp = mt_rand(1, 100);

                $id = DB::table('envios.envios')->insertGetId([
                    'codigo_guia' => 'TMP-'.Str::random(20), // temporal; se reemplaza con el id
                    'cliente_remitente_id' => $idsClientes[$remitente],
                    'cliente_destinatario_id' => $idsClientes[$destinatario],
                    'sucursal_origen_id' => $idsSucursales[$sucursal],
                    'direccion_destino' => 'Col. '.$pick($colonias).', calle '.mt_rand(1, 40).', '.$territorio,
                    'territorio_destino_id' => $idsTerritorios[$territorio],
                    'peso_kg' => round(0.5 + mt_rand(0, 4000) / 100, 2),
                    'volumen_m3' => mt_rand(1, 100) <= 70 ? round(0.01 + mt_rand(0, 150) / 100, 2) : null,
                    'estado_envio_id' => $idsEstados[$estado],
                    'prioridad' => $rp <= 15 ? 'URGENTE' : ($rp <= 80 ? 'NORMAL' : 'BAJA'),
                    'fecha_registro' => $registro,
                    'fecha_comprometida' => $compromiso,
                    'created_at' => $registro,
                    'updated_at' => $registro,
                ], 'id_envio');

                // Misma convención que el sistema: PK-000123
                DB::table('envios.envios')
                    ->where('id_envio', $id)
                    ->update(['codigo_guia' => 'PK-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT)]);

                /*
                | Historial: del REGISTRADO hasta el estado actual, repartido en el tiempo.
                | Un envío ENTREGADO llega a veces a tiempo y a veces tarde.
                */
                $etapas = in_array($estado, $flujo, true)
                    ? array_slice($flujo, 0, array_search($estado, $flujo, true) + 1)
                    : [...$flujo, $estado];

                $inicio = $registro->timestamp;
                $duracion = $compromiso->timestamp - $inicio;
                $fin = $estado === 'ENTREGADO'
                    ? min($inicio + (int) ($duracion * (0.6 + mt_rand(0, 70) / 100)), $ahora->timestamp - 600)
                    : max($inicio + 60, $ahora->timestamp - 300);

                foreach ($etapas as $k => $etapa) {
                    $instante = $inicio + (int) (($fin - $inicio) * $k / max(count($etapas) - 1, 1));
                    $nota = match ($etapa) {
                        'REGISTRADO' => 'Envío registrado',
                        'FALLIDO' => 'Destinatario ausente en dos intentos',
                        'DEVUELTO' => 'Devuelto a sucursal de origen',
                        default => null,
                    };

                    // El primer evento queda ubicado en la sucursal de origen
                    $this->registrarEvento(
                        $id,
                        $idsEstados[$etapa],
                        $instante,
                        $nota,
                        $k === 0 ? $coordenadas[$sucursal] : null
                    );
                }
            }
        });
    }

    /** Inserta un evento con su fecha; si hay coordenadas, guarda el punto geográfico. */
    private function registrarEvento(int $envioId, int $estadoId, int $instante, ?string $nota, ?array $coordenadas): void
    {
        $fecha = date('Y-m-d H:i:sP', $instante);

        if ($coordenadas !== null) {
            [$lat, $lng] = $coordenadas;
            DB::insert(
                'INSERT INTO envios.envio_eventos (envio_id, estado_envio_id, ocurrido_en, nota, ubicacion)
                 VALUES (?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326)::geography)',
                [$envioId, $estadoId, $fecha, $nota, (float) $lng, (float) $lat]
            );

            return;
        }

        DB::table('envios.envio_eventos')->insert([
            'envio_id' => $envioId,
            'estado_envio_id' => $estadoId,
            'ocurrido_en' => $fecha,
            'nota' => $nota,
        ]);
    }
}