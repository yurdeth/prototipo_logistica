<?php

namespace App\Enums;

/**
 * Espejo en código del catálogo rutas.tipos_incidencias. Si se agrega o quita
 * un tipo en la base, actualizar aquí y en el seeder en la misma tarea;
 * EstadoCatalogoTest comprueba que ambos lados coinciden.
 */
enum TipoIncidenciaEnum: string
{
    case FALLA_MECANICA = 'FALLA_MECANICA';
    case TRANSITO = 'TRANSITO';
    case CLIMA = 'CLIMA';
    case CLIENTE_AUSENTE = 'CLIENTE_AUSENTE';
    case DIRECCION_ERRONEA = 'DIRECCION_ERRONEA';
    case OTRO = 'OTRO';

    /** Valores del catálogo. */
    public const VALUES = ['FALLA_MECANICA', 'TRANSITO', 'CLIMA', 'CLIENTE_AUSENTE', 'DIRECCION_ERRONEA', 'OTRO'];

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
