<?php

namespace Database\Factories;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Departamento>
 */
class DepartamentoFactory extends Factory
{
    /** @var list<string> */
    private const NOMBRES = ['Operaciones', 'Mantenimiento', 'Administración', 'Logística', 'Recursos Humanos'];

    public function definition(): array
    {
        static $indice = 0;

        $nombre = self::NOMBRES[$indice % count(self::NOMBRES)];
        if ($indice >= count(self::NOMBRES)) {
            $nombre .= ' '.intdiv($indice, count(self::NOMBRES));
        }
        $indice++;

        return [
            'nombre_departamento' => $nombre,
            'activo' => true,
        ];
    }

    /** Departamento por nombre (creándolo solo la primera vez). */
    public static function llamado(string $nombre): Departamento
    {
        return Departamento::query()->firstOrCreate(['nombre_departamento' => $nombre]);
    }
}
