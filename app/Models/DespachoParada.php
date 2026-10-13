<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id_parada
 * @property int $despacho_id
 * @property int $envio_id
 * @property int $orden
 * @property Carbon|null $hora_estimada
 * @property Carbon|null $hora_real
 */
#[Table('rutas.despacho_paradas')]
#[WithoutTimestamps]
#[Fillable(['despacho_id', 'envio_id', 'orden', 'hora_estimada', 'hora_real'])]
class DespachoParada extends Model
{
    protected $primaryKey = 'id_parada';

    /**
     * @return BelongsTo<Despacho, $this>
     */
    public function despacho(): BelongsTo
    {
        return $this->belongsTo(Despacho::class, 'despacho_id', 'id_despacho');
    }

    /**
     * @return BelongsTo<Envio, $this>
     */
    public function envio(): BelongsTo
    {
        return $this->belongsTo(Envio::class, 'envio_id', 'id_envio');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hora_estimada' => 'datetime',
            'hora_real' => 'datetime',
        ];
    }
}
