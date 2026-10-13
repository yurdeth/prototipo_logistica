<?php

namespace App\Models;

use Database\Factories\VehiculoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Vehículo de la flota, identificado por su placa en formato nacional salvadoreño:
 * letra de categoría + 6 dígitos (P particular, C carga, M motocicleta, A transporte
 * público, N no remunerado, RE remolques, CD cuerpo diplomático, CC cuerpo consular).
 * La validación del formato vive en StoreVehiculoRequest::FORMATO_PLACA.
 *
 * @property int $id_vehiculo
 * @property string $placa
 * @property int $tipo_vehiculo_id
 * @property int $estado_vehiculo_id
 * @property int $sucursal_id
 * @property string|null $marca
 * @property string|null $modelo
 * @property int|null $anio
 * @property string|null $consumo_km_gal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('flota.vehiculos')]
#[Fillable(['placa', 'tipo_vehiculo_id', 'estado_vehiculo_id', 'sucursal_id', 'marca', 'modelo', 'anio', 'consumo_km_gal'])]
class Vehiculo extends Model
{
    /** @use HasFactory<VehiculoFactory> */
    use HasFactory;

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
     * Registro marca-modelo del vehículo en la pivote flota.vehiculo_marca_modelo.
     *
     * @return BelongsToMany<ModeloVehiculo, $this>
     */
    public function modelos(): BelongsToMany
    {
        return $this->belongsToMany(
            ModeloVehiculo::class,
            'flota.vehiculo_marca_modelo',
            'vehiculo_id',
            'modelo_vehiculo_id',
        )->withPivot('marca_vehiculo_id');
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
            'consumo_km_gal' => 'decimal:2',
        ];
    }
}
