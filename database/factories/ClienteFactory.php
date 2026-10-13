<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /** Empresas con nombres creíbles del comercio salvadoreño. */
    private const EMPRESAS = [
        'Distribuidora Andina', 'Comercial Sur', 'Importadora Norte', 'Farmacias Vida',
        'Ferretería Central', 'Supermercados La Despensa', 'Textiles del Este', 'Electro Hogar SV',
    ];

    /** @var list<string> */
    private const PERSONAS = ['María López', 'Carlos Pérez', 'Ana Rivas', 'José Hernández', 'Rosa Martínez', 'Luis Gómez', 'Carmen Flores', 'Jorge Díaz'];

    public function definition(): array
    {
        static $secuencia = 0;

        $esEmpresa = fake()->boolean(30);

        if ($esEmpresa) {
            $nombre = self::EMPRESAS[$secuencia % count(self::EMPRESAS)];
            if ($secuencia >= count(self::EMPRESAS)) {
                $nombre .= ' '.intdiv($secuencia, count(self::EMPRESAS));
            }
        } else {
            $nombre = self::PERSONAS[$secuencia % count(self::PERSONAS)];
            if ($secuencia >= count(self::PERSONAS)) {
                $nombre .= ' '.intdiv($secuencia, count(self::PERSONAS));
            }
        }
        $secuencia++;

        return [
            'nombre' => $nombre,
            'tipo_cliente' => $esEmpresa ? 'EMPRESA' : 'PERSONA',
            'contacto' => fake()->numerify('7###-####'),
            'territorio_id' => TerritorioFactory::departamento(fake()->randomElement(TerritorioFactory::DEPARTAMENTOS))->id_territorio,
        ];
    }

    /** Cliente ya registrado, o uno nuevo si la base está vacía. */
    public static function existente(): Cliente
    {
        return Cliente::query()->inRandomOrder()->first() ?? Cliente::factory()->create();
    }
}
