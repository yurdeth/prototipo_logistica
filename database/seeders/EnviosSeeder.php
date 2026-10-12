<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EnviosSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = now();

        /*
        |--------------------------------------------------------------------------
        | 1. TERRITORIOS
        |--------------------------------------------------------------------------
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

        $idsTerritorios = DB::table('territorio.territorios')
            ->pluck('id_territorio', 'nombre_territorio');

        /*
        |--------------------------------------------------------------------------
        | 2. SUCURSALES
        |--------------------------------------------------------------------------
        */

        $sucursales = [
            [
                'SUC-CEN',
                'Sucursal Central',
                'San Salvador',
                'Boulevard de los Héroes, San Salvador',
                13.698900,
                -89.191400,
            ],
            [
                'SUC-ORI',
                'Sucursal Oriente',
                'San Miguel',
                'Avenida Roosevelt, San Miguel',
                13.483300,
                -88.177800,
            ],
            [
                'SUC-OCC',
                'Sucursal Occidente',
                'Santa Ana',
                'Calle Libertad, Santa Ana',
                13.994200,
                -89.559700,
            ],
        ];

        foreach ($sucursales as [
            $codigo,
            $nombre,
            $territorio,
            $direccion,
            $latitud,
            $longitud,
        ]) {
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
        }

        $idsSucursales = DB::table('territorio.sucursales')
            ->pluck('id_sucursal', 'codigo');

        /*
        |--------------------------------------------------------------------------
        | 3. CLIENTES
        |--------------------------------------------------------------------------
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

        $idsClientes = DB::table('envios.clientes')
            ->pluck('id_cliente', 'nombre');

        /*
        |--------------------------------------------------------------------------
        | 4. ESTADOS DE ENVÍOS
        |--------------------------------------------------------------------------
        */

        $estados = [
            'REGISTRADO',
            'RECOLECTADO',
            'EN_TRANSITO',
            'EN_CENTRO',
            'EN_RUTA',
            'ENTREGADO',
            'DEVUELTO',
            'FALLIDO',
        ];

        foreach ($estados as $estado) {
            DB::table('envios.estados_envios')->insertOrIgnore([
                'nombre_estado' => $estado,
            ]);
        }

        $idsEstados = DB::table('envios.estados_envios')
            ->pluck('id_estado', 'nombre_estado');

        /*
        |--------------------------------------------------------------------------
        | 5. ENVÍOS DE EJEMPLO
        |--------------------------------------------------------------------------
        */

        $envios = [
            [
                'GUIA-0001',
                'Distribuidora Andina',
                'María López',
                'SUC-CEN',
                'Santa Tecla',
                'Residencial Las Palmas, Santa Tecla',
                2.50,
                0.030,
                'EN_TRANSITO',
                'NORMAL',
                2,
            ],
            [
                'GUIA-0002',
                'Comercial Sur',
                'Carlos Pérez',
                'SUC-ORI',
                'San Miguel',
                'Colonia Ciudad Pacífica, San Miguel',
                5.00,
                0.080,
                'REGISTRADO',
                'URGENTE',
                1,
            ],
            [
                'GUIA-0003',
                'Importadora Norte',
                'Ana Rivas',
                'SUC-OCC',
                'Sonsonate',
                'Centro de Sonsonate',
                1.25,
                0.015,
                'ENTREGADO',
                'BAJA',
                5,
            ],
        ];

        foreach ($envios as [
            $guia,
            $remitente,
            $destinatario,
            $sucursal,
            $territorio,
            $direccion,
            $peso,
            $volumen,
            $estado,
            $prioridad,
            $dias,
        ]) {
            DB::table('envios.envios')->updateOrInsert(
                ['codigo_guia' => $guia],
                [
                    'cliente_remitente_id' => $idsClientes[$remitente],

                    'cliente_destinatario_id' => $idsClientes[$destinatario],

                    'sucursal_origen_id' => $idsSucursales[$sucursal],

                    'direccion_destino' => $direccion,

                    'territorio_destino_id' => $idsTerritorios[$territorio],

                    'peso_kg' => $peso,
                    'volumen_m3' => $volumen,

                    'estado_envio_id' => $idsEstados[$estado],

                    'prioridad' => $prioridad,

                    'fecha_registro' => $ahora,

                    'fecha_comprometida' => $ahora->copy()->addDays($dias),

                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]
            );
        }
    }
}
