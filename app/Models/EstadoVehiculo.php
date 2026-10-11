<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id_estado
 * @property string $nombre_estado
 */
#[Table('flota.estados_vehiculos')]
#[WithoutTimestamps]
#[Fillable(['nombre_estado'])]
class EstadoVehiculo extends Model
{
    protected $primaryKey = 'id_estado';

    /**
     * @return HasMany<Vehiculo, $this>
     */
    public function vehiculos(): HasMany
    {
        return $this->hasMany(Vehiculo::class, 'estado_vehiculo_id', 'id_estado');
    }
}
