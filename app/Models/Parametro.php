<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id_parametro
 * @property string $clave
 * @property string $valor
 * @property string $vigente_desde
 */
#[Table('rutas.parametros')]
#[WithoutTimestamps]
#[Fillable(['clave', 'valor', 'vigente_desde'])]
class Parametro extends Model
{
    protected $primaryKey = 'id_parametro';

    // aqui vive la penalizacion de congestion (ej. clave 'penalizacion_congestion') que aplican los calculos de ruta

    /**
     * Última versión vigente de un parámetro por su clave.
     */
    public function scopeVigente(Builder $query, string $clave): void
    {
        $query->where('clave', $clave)
            ->orderByDesc('vigente_desde')
            ->limit(1);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:4',
            'vigente_desde' => 'date',
        ];
    }
}
