<?php

namespace Database\Factories;

use App\Models\Empleado;
use App\Models\Puesto;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empleado>
 */
class EmpleadoFactory extends Factory
{
    public function definition(): array
    {
        static $secuencia = 0;

        return [
            'usuario_id' => null,
            'puesto_id' => (Puesto::query()->inRandomOrder()->first() ?? Puesto::factory()->create())->id_puesto,
            'sucursal_id' => (Sucursal::query()->inRandomOrder()->first() ?? Sucursal::factory()->create())->id_sucursal,
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            // 8 dígitos con formato de DUI; la secuencia propia evita choques
            // con documentos escritos a mano en los tests.
            'documento_identidad' => str_pad((string) (++$secuencia + 10000000), 8, '0', STR_PAD_LEFT),
            'telefono' => fake()->numerify('7###-####'),
            'numero_licencia' => null,
            'fecha_contratacion' => fake()->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'activo' => true,
        ];
    }

    /**
     * Empleado conductor: puesto de Conductor y licencia; los despachos solo
     * pueden asignarse a alguien con este perfil.
     */
    public function conductor(): static
    {
        return $this->for(PuestoFactory::conductor(), 'puesto')
            ->set('numero_licencia', fake()->numerify('LIC-######'));
    }
}
