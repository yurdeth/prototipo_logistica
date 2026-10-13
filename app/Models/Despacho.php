<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id_despacho
 * @property int $sucursal_origen_id
 * @property int $conductor_empleado_id
 * @property int $vehiculo_id
 * @property Carbon $fecha
 * @property int $estado_despacho_id
 * @property string|null $km_planificados
 * @property string|null $km_recorridos
 * @property string|null $costo_estimado
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('rutas.despachos')]
#[Fillable(['sucursal_origen_id', 'conductor_empleado_id', 'vehiculo_id', 'fecha', 'estado_despacho_id', 'km_planificados', 'km_recorridos', 'costo_estimado'])]
class Despacho extends Model
{
    protected $primaryKey = 'id_despacho';

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id', 'id_sucursal');
    }

    /**
     * @return BelongsTo<Empleado, $this>
     */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'conductor_empleado_id', 'id_empleado');
    }

    /**
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id', 'id_vehiculo');
    }

    /**
     * @return BelongsTo<EstadoDespacho, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoDespacho::class, 'estado_despacho_id', 'id_estado');
    }

    /**
     * @return HasMany<DespachoParada, $this>
     */
    public function paradas(): HasMany
    {
        return $this->hasMany(DespachoParada::class, 'despacho_id', 'id_despacho')->orderBy('orden');
    }

    /**
     * @return HasMany<Incidencia, $this>
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'despacho_id', 'id_despacho');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'km_planificados' => 'decimal:2',
            'km_recorridos' => 'decimal:2',
            'costo_estimado' => 'decimal:2',
        ];
    }
}
