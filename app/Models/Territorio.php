<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id_territorio
 * @property string $nombre_territorio
 * @property string $tipo_territorio
 * @property int|null $territorio_padre_id
 * @property Carbon|null $created_at
 */
#[Table('territorio.territorios')]
#[Fillable(['nombre_territorio', 'tipo_territorio', 'territorio_padre_id'])]
class Territorio extends Model
{
    protected $primaryKey = 'id_territorio';

    // la tabla solo tiene created_at
    const UPDATED_AT = null;

    /**
     * @return HasMany<Sucursal, $this>
     */
    public function sucursales(): HasMany
    {
        return $this->hasMany(Sucursal::class, 'territorio_id', 'id_territorio');
    }
}
