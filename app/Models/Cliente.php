<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id_cliente
 * @property string $nombre
 * @property string $tipo_cliente
 * @property string|null $contacto
 * @property int $territorio_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('envios.clientes')]
#[Fillable(['nombre', 'tipo_cliente', 'contacto', 'territorio_id'])]
class Cliente extends Model
{
    protected $primaryKey = 'id_cliente';

    /**
     * @return BelongsTo<Territorio, $this>
     */
    public function territorio(): BelongsTo
    {
        return $this->belongsTo(Territorio::class, 'territorio_id', 'id_territorio');
    }
}
