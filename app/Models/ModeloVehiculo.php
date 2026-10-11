<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de vehículo, siempre perteneciente a una marca del catálogo.
 * Su tipo (Motocicleta, Van, Camion Ligero...) determina la categoría del vehículo:
 * los modelos de carga son panel/carga y los de tipo Motocicleta son motos.
 *
 * @property int $id_modelo
 * @property int $marca_vehiculo_id
 * @property int|null $tipo_vehiculo_id
 * @property string $nombre
 * @property bool $activa
 */
#[Table('flota.modelo_vehiculo')]
#[WithoutTimestamps]
#[Fillable(['marca_vehiculo_id', 'tipo_vehiculo_id', 'nombre', 'activa'])]
class ModeloVehiculo extends Model
{
    protected $primaryKey = 'id_modelo';

    /**
     * @return BelongsTo<MarcaVehiculo, $this>
     */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(MarcaVehiculo::class, 'marca_vehiculo_id', 'id_marca');
    }

    /**
     * @return BelongsTo<TipoVehiculo, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoVehiculo::class, 'tipo_vehiculo_id', 'id_tipo_vehiculo');
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
