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

    /**
     * Estados de cierre: no admiten más transiciones (el envío terminó su ciclo).
     * FALLIDO no es cierre: admite reintento a EN_RUTA o devolución.
     */
    public const CIERRE = ['ENTREGADO', 'DEVUELTO'];

    /**
     * Matriz de transiciones del ciclo de vida (Figura §3.3.1 de la tesis):
     * REGISTRADO→RECOLECTADO→EN_TRANSITO→EN_CENTRO→EN_RUTA→{ENTREGADO, FALLIDO};
     * FALLIDO→EN_RUTA (reintento) o DEVUELTO. Los estados de CIERRE no transitan.
     *
     * @var array<string, list<string>>
     */
    public const TRANSICIONES = [
        'REGISTRADO' => ['RECOLECTADO'],
        'RECOLECTADO' => ['EN_TRANSITO'],
        'EN_TRANSITO' => ['EN_CENTRO'],
        'EN_CENTRO' => ['EN_RUTA'],
        'EN_RUTA' => ['ENTREGADO', 'FALLIDO'],
        'FALLIDO' => ['EN_RUTA', 'DEVUELTO'],
        'ENTREGADO' => [],
        'DEVUELTO' => [],
    ];

    /** ¿El envío puede pasar de su estado actual a $destino según la matriz? */
    public function puedeTransicionarA(self $destino): bool
    {
        return in_array($destino->value, self::TRANSICIONES[$this->value], true);
    }

    // Duplicada de casos() porque PHP no permite llamadas a métodos en constantes de clase,
    // y EnvioController::ESTADOS necesita un const, no una llamada.
    public const VALUES = ['REGISTRADO', 'RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA', 'ENTREGADO', 'DEVUELTO', 'FALLIDO'];

    /** @return list<string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
