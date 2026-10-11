<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id_incidencia
 * @property int $tipo_incidencia_id
 * @property int|null $despacho_id
 * @property int|null $envio_id
 * @property string $ocurrio_en
 * @property string|null $descripcion
 * @property int $minutos_retraso
 */
#[Table('rutas.incidencias')]
#[WithoutTimestamps]
#[Fillable(['tipo_incidencia_id', 'despacho_id', 'envio_id', 'ocurrio_en', 'descripcion', 'minutos_retraso'])]
class Incidencia extends Model
{
    protected $primaryKey = 'id_incidencia';

    /**
     * @return BelongsTo<TipoIncidencia, $this>
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoIncidencia::class, 'tipo_incidencia_id', 'id_tipo');
    }

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
            'ocurrio_en' => 'datetime',
            'minutos_retraso' => 'integer',
        ];
    }
}
