<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id_departamento
 * @property string $nombre_departamento
 * @property string|null $descripcion
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('personal.departamentos')]
#[Fillable(['nombre_departamento', 'descripcion', 'activo'])]
class Departamento extends Model
{
    protected $primaryKey = 'id_departamento';

    /**
     * @return HasMany<Puesto, $this>
     */
    public function puestos(): HasMany
    {
        return $this->hasMany(Puesto::class, 'departamento_id', 'id_departamento');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
