<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;
use RuntimeException;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    private const NOMBRES = [
        'María', 'Carlos', 'Ana', 'José', 'Rosa', 'Luis', 'Carmen', 'Jorge',
        'Diana', 'Miguel', 'Sofía', 'Roberto', 'Patricia', 'Fernando', 'Gabriela',
        'Ricardo', 'Verónica', 'Manuel', 'Claudia', 'Daniel', 'Lucía', 'Mario',
        'Elena', 'Oscar', 'Karla',
    ];

    private const APELLIDOS = [
        'López', 'Pérez', 'Rivas', 'Hernández', 'Martínez', 'Gómez', 'Flores', 'Díaz',
        'Ramírez', 'Castillo', 'Mejía', 'Alvarado', 'Guzmán', 'Cruz', 'Ortiz',
        'Mendoza', 'Salazar', 'Quintanilla', 'Aguilar', 'Rodríguez', 'Vásquez',
        'Menéndez', 'Portillo', 'Escobar', 'Ayala',
    ];

    private const EMPRESA_TIPO = [
        'Distribuidora', 'Comercial', 'Importadora', 'Farmacias', 'Ferretería',
        'Supermercados', 'Textiles', 'Electro', 'Inversiones', 'Servicios',
        'Agroindustrias', 'Transportes',
    ];

    private const EMPRESA_NOMBRE = [
        'Andina', 'Sur', 'Norte', 'Vida', 'Central', 'La Despensa', 'del Este', 'Hogar',
        'Cuscatlán', 'Pacífico', 'Lempa', 'Izalco', 'San Miguel', 'Metrópolis',
        'El Faro', 'La Unión', 'Salvadoreña', 'Santa Ana',
    ];

    /**
     * Nombres ya entregados en este proceso.
     *
     * @var array<string, true>
     */
    private static array $usados = [];

    public function definition(): array
    {
        $esEmpresa = fake()->boolean(30);

        return [
            'nombre' => $esEmpresa ? $this->empresaUnica() : $this->personaUnica(),
            'tipo_cliente' => $esEmpresa ? 'EMPRESA' : 'PERSONA',
            'contacto' => fake()->numerify('7###-####'),
            'territorio_id' => TerritorioFactory::departamento(
                fake()->randomElement(TerritorioFactory::DEPARTAMENTOS)
            )->id_territorio,
        ];
    }

    private function personaUnica(): string
    {
        return $this->generarUnico(fn () => fake()->randomElement(self::NOMBRES)
            .' '.fake()->randomElement(self::APELLIDOS));
    }

    private function empresaUnica(): string
    {
        return $this->generarUnico(fn () => fake()->randomElement(self::EMPRESA_TIPO)
            .' '.fake()->randomElement(self::EMPRESA_NOMBRE));
    }

    /**
     * Genera nombres hasta dar con uno que no exista en BD ni en este proceso.
     *
     * @param  callable(): string  $generador
     */
    private function generarUnico(callable $generador): string
    {
        for ($i = 0; $i < 500; $i++) {
            $nombre = $generador();

            if (isset(self::$usados[$nombre])
                || Cliente::query()->where('nombre', $nombre)->exists()) {
                continue;
            }

            self::$usados[$nombre] = true;

            return $nombre;
        }

        throw new RuntimeException(
            'No quedan nombres únicos disponibles; amplía las listas de la factory.'
        );
    }

    /** Cliente ya registrado, o uno nuevo si la base está vacía. */
    public static function existente(): Cliente
    {
        return Cliente::query()->inRandomOrder()->first() ?? Cliente::factory()->create();
    }

    /** Cliente distinto del dado (mismo pool si hay variedad, uno nuevo si no). */
    public static function distintoDe(Cliente $cliente): Cliente
    {
        return Cliente::query()
            ->where('id_cliente', '!=', $cliente->id_cliente)
            ->inRandomOrder()
            ->first() ?? Cliente::factory()->create();
    }
}
