<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id_vehiculo
 * @property string $placa
 * @property int $tipo_vehiculo_id
 * @property int $estado_vehiculo_id
 * @property int $sucursal_id
 * @property string|null $marca
 * @property string|null $modelo
 * @property int|null $anio
 * @property string|null $consumo_km_l
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('flota.vehiculos')]
#[Fillable(['placa', 'tipo_vehiculo_id', 'estado_vehiculo_id', 'sucursal_id', 'marca', 'modelo', 'anio', 'consumo_km_l'])]
class Vehiculo extends Model
{
    protected $primaryKey = 'id_vehiculo';

    /**
     * @return BelongsTo<TipoVehiculo, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class, 'tipo_vehiculo_id', 'id_tipo_vehiculo');
    }

    /**
     * @return BelongsTo<EstadoVehiculo, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoVehiculo::class, 'estado_vehiculo_id', 'id_estado');
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id', 'id_sucursal');
    }

    /**
     * @param  Builder<Vehiculo>  $query
     */
    public function scopeActivos(Builder $query): void
    {
        $query->whereHas('estado', fn (Builder $q) => $q->where('nombre_estado', 'DISPONIBLE'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'consumo_km_l' => 'decimal:2',
        ];
    }
}
