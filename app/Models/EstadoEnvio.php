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
#[Table('envios.estados_envios')]
#[WithoutTimestamps]
#[Fillable(['nombre_estado'])]
class EstadoEnvio extends Model
{
    protected $primaryKey = 'id_estado';

    /**
     * @return HasMany<Envio, $this>
     */
    public function envios(): HasMany
    {
        return $this->hasMany(Envio::class, 'estado_envio_id', 'id_estado');
    }

    /**
     * @return HasMany<EnvioEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EnvioEvento::class, 'estado_envio_id', 'id_estado');
    }
}
