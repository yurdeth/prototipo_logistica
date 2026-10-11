<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id_tipo_vehiculo
 * @property string $nombre
 * @property string $marca
 * @property string $capacidad_kg
 * @property string|null $capacidad_m3
 */
#[Table('flota.tipos_vehiculos')]
#[WithoutTimestamps]
#[Fillable(['nombre', 'marca', 'capacidad_kg', 'capacidad_m3'])]
class TipoVehiculo extends Model
{
    protected $primaryKey = 'id_tipo_vehiculo';

    /**
     * @return HasMany<Vehiculo, $this>
     */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'tipo_vehiculo_id', 'id_tipo_vehiculo');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacidad_kg' => 'decimal:2',
            'capacidad_m3' => 'decimal:2',
        ];
    }
}
