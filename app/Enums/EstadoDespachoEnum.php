<?php

namespace App\Enums;

/**
 * Espejo en código del catálogo rutas.estados_despachos. Si se agrega o quita
 * un estado en la base, actualizar aquí y en el seeder en la misma tarea;
 * EstadoCatalogoTest comprueba que ambos lados coinciden.
 */
enum EstadoDespachoEnum: string
{
    case PLANIFICADO = 'PLANIFICADO';
    case EN_CURSO = 'EN_CURSO';
    case COMPLETADO = 'COMPLETADO';

    /** Valores del catálogo, en el orden del ciclo de vida. */
    public const VALUES = ['PLANIFICADO', 'EN_CURSO', 'COMPLETADO'];

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
