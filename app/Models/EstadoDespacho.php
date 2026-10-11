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
#[Table('rutas.estados_despachos')]
#[WithoutTimestamps]
#[Fillable(['nombre_estado'])]
class EstadoDespacho extends Model
{
    protected $primaryKey = 'id_estado';

    /**
     * @return HasMany<Despacho, $this>
     */
    public function despachos(): HasMany
    {
        return $this->hasMany(Despacho::class, 'estado_despacho_id', 'id_estado');
    }
}
