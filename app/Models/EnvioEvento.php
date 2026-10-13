<?php

namespace App\Models;

use Database\Factories\EnvioEventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id_evento
 * @property int $envio_id
 * @property int $estado_envio_id
 * @property string $ocurrido_en
 * @property string|null $nota
 */
#[Table('envios.envio_eventos')]
#[WithoutTimestamps]
#[Fillable(['envio_id', 'estado_envio_id', 'ocurrido_en', 'nota'])]
class EnvioEvento extends Model
{
    /** @use HasFactory<EnvioEventoFactory> */
    use HasFactory;

    protected $primaryKey = 'id_evento';

    // la columna ubicacion (geography Point 4326) se escribe via SQL (ST_SetSRID/ST_MakePoint); queda fuera del fillable

    /**
     * @return BelongsTo<Envio, $this>
     */
    public function envio(): BelongsTo
    {
        return $this->belongsTo(Envio::class, 'envio_id', 'id_envio');
    }

    /**
     * @return BelongsTo<EstadoEnvio, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoEnvio::class, 'estado_envio_id', 'id_estado');
    }

    /**
     * @param  Builder<EnvioEvento>  $query
     */
    public function scopeCronologico(Builder $query): void
    {
        $query->orderBy('ocurrido_en')->orderBy('id_evento');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocurrido_en' => 'datetime',
        ];
    }
}
