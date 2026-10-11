<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Marca de vehículo del catálogo fijo (Toyota, Nissan, Mitsubishi, Kia, Chevrolet...).
 *
 * @property int $id_marca
 * @property string $nombre
 * @property bool $activa
 */
#[Table('flota.marca_vehiculo')]
#[WithoutTimestamps]
#[Fillable(['nombre', 'activa'])]
class MarcaVehiculo extends Model
{
    protected $primaryKey = 'id_marca';

    /**
     * @return HasMany<ModeloVehiculo, $this>
     */
    public function modelos(): HasMany
    {
        return $this->hasMany(ModeloVehiculo::class, 'marca_vehiculo_id', 'id_marca');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }
}
