<?php

namespace App\Enums;

/**
 * Espejo en código del catálogo envios.estados_envios. Si se agrega o quita
 * un estado en la base, actualizar aquí y en el seeder en la misma tarea;
 * EstadoCatalogoTest comprueba que ambos lados coinciden.
 */
enum EstadoEnvioEnum: string
{
    case REGISTRADO = 'REGISTRADO';
    case RECOLECTADO = 'RECOLECTADO';
    case EN_TRANSITO = 'EN_TRANSITO';
    case EN_CENTRO = 'EN_CENTRO';
    case EN_RUTA = 'EN_RUTA';
    case ENTREGADO = 'ENTREGADO';
    case DEVUELTO = 'DEVUELTO';
    case FALLIDO = 'FALLIDO';

    /** Estados de cierre: no admiten más transiciones. */
    public const CIERRE = ['ENTREGADO', 'DEVUELTO', 'FALLIDO'];

    // Duplicada de casos() porque PHP no permite llamadas a métodos en constantes de clase,
    // y EnvioController::ESTADOS necesita un const, no una llamada.
    public const VALUES = ['REGISTRADO', 'RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA', 'ENTREGADO', 'DEVUELTO', 'FALLIDO'];

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
