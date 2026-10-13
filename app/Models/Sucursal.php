<?php

namespace App\Models;

use Database\Factories\SucursalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id_sucursal
 * @property string $codigo
 * @property string $nombre_sucursal
 * @property int $territorio_id
 * @property string $direccion
 * @property string|null $latitud
 * @property string|null $longitud
 * @property string|null $telefono
 * @property bool $activa
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('territorio.sucursales')]
#[Fillable(['codigo', 'nombre_sucursal', 'territorio_id', 'direccion', 'latitud', 'longitud', 'telefono', 'activa'])]
class Sucursal extends Model
{
    /** @use HasFactory<SucursalFactory> */
    use HasFactory;

    protected $primaryKey = 'id_sucursal';

    /**
     * @return BelongsTo<Territorio, $this>
     */
    public function territorio(): BelongsTo
    {
        return $this->belongsTo(Territorio::class, 'territorio_id', 'id_territorio');
    }

    /** @return HasMany<Envio, $this> */
    public function enviosOrigen(): HasMany
    {
        return $this->hasMany(Envio::class, 'sucursal_origen_id', 'id_sucursal');
    }

    /** @return HasMany<Despacho, $this> */
    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class, 'sucursal_origen_id', 'id_sucursal');
    }

    /** @return HasMany<Vehiculo, $this> */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'sucursal_id', 'id_sucursal');
    }

    // ubicacion es columna GENERATED geography(Point,4326) derivada de latitud/longitud: se expone calculada y nunca se escribe
    /**
     * @return array{latitud: float, longitud: float}|null
     */
    public function getUbicacionAttribute(): ?array
    {
        if ($this->latitud === null || $this->longitud === null) {
            return null;
        }

        return [
            'latitud' => (float) $this->latitud,
            'longitud' => (float) $this->longitud,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'latitud' => 'decimal:6',
            'longitud' => 'decimal:6',
        ];
    }
}
