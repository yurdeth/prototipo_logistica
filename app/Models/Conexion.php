<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id_conexion
 * @property int $origen_sucursal_id
 * @property int $destino_sucursal_id
 * @property string $distancia_km
 * @property int $tiempo_min
 * @property bool $activa
 */
#[Table('rutas.conexiones')]
#[Fillable(['origen_sucursal_id', 'destino_sucursal_id', 'distancia_km', 'tiempo_min', 'activa'])]
class Conexion extends Model
{
    protected $primaryKey = 'id_conexion';

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function origen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'origen_sucursal_id', 'id_sucursal');
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function destino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'destino_sucursal_id', 'id_sucursal');
    }

    public function scopeActivas(Builder $query): void
    {
        $query->where('activa', true);
    }

    /**
     * Peso legible del tramo para escenarios de bloqueo/congestion: "142.50 km / 180 min" o "INACTIVA".
     */
    public function getPesoAttribute(): string
    {
        if (! $this->activa) {
            return 'INACTIVA';
        }

        return "{$this->distancia_km} km / {$this->tiempo_min} min";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distancia_km' => 'decimal:2',
            'tiempo_min' => 'integer',
            'activa' => 'boolean',
        ];
    }
}
