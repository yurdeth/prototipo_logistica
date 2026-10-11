<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id_tipo
 * @property string $nombre_tipo
 */
#[Table('rutas.tipos_incidencias')]
#[WithoutTimestamps]
#[Fillable(['nombre_tipo'])]
class TipoIncidencia extends Model
{
    protected $primaryKey = 'id_tipo';

    /**
     * @return HasMany<Incidencia, $this>
     */
    public function incidencias(): HasMany
    {
        return $this->hasMany(Incidencia::class, 'tipo_incidencia_id', 'id_tipo');
    }
}
