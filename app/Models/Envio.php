<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id_envio
 * @property string $codigo_guia
 * @property int $cliente_remitente_id
 * @property int $cliente_destinatario_id
 * @property int $sucursal_origen_id
 * @property string $direccion_destino
 * @property int $territorio_destino_id
 * @property string $peso_kg
 * @property string|null $volumen_m3
 * @property int $estado_envio_id
 * @property string $prioridad
 * @property Carbon $fecha_registro
 * @property Carbon $fecha_comprometida
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('envios.envios')]
#[Fillable(['codigo_guia', 'cliente_remitente_id', 'cliente_destinatario_id', 'sucursal_origen_id', 'direccion_destino', 'territorio_destino_id', 'peso_kg', 'volumen_m3', 'estado_envio_id', 'prioridad', 'fecha_registro', 'fecha_comprometida'])]
class Envio extends Model
{
    protected $primaryKey = 'id_envio';

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function remitente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_remitente_id', 'id_cliente');
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function destinatario(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_destinatario_id', 'id_cliente');
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id', 'id_sucursal');
    }

    /**
     * @return BelongsTo<Territorio, $this>
     */
    public function territorioDestino(): BelongsTo
    {
        return $this->belongsTo(Territorio::class, 'territorio_destino_id', 'id_territorio');
    }

    /**
     * @return BelongsTo<EstadoEnvio, $this>
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoEnvio::class, 'estado_envio_id', 'id_estado');
    }

    /**
     * @return HasMany<EnvioEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(EnvioEvento::class, 'envio_id', 'id_envio');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso_kg' => 'decimal:2',
            'volumen_m3' => 'decimal:2',
            'fecha_registro' => 'datetime',
            'fecha_comprometida' => 'datetime',
        ];
    }
}
