<?php

namespace App\Models;

use Database\Factories\EnvioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Trazabilidad: cada alta y cada cambio de `estado_envio_id` insertan su fila en
 * `envios.envio_eventos` dentro de la MISMA transacción, sin importar desde dónde se
 * guarde (controlador, tinker, seeder). Ver booted(), save() y registrarEvento().
 *
 * Límite conocido: una actualización masiva (`Envio::where(...)->update([...])`) no pasa
 * por el modelo y por tanto no genera evento. Para cambiar estados hay que cargar el
 * modelo y llamar a save()/update().
 *
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
    /** @use HasFactory<EnvioFactory> */
    use HasFactory;

    protected $primaryKey = 'id_envio';

    /**
     * Datos opcionales del evento que se generará en el próximo save().
     * No son columnas de `envios`; se consumen al registrar el evento.
     */
    public ?string $notaEvento = null;

    public mixed $latitudEvento = null;

    public mixed $longitudEvento = null;

    protected static function booted(): void
    {
        // La guía definitiva se arma con el id; mientras tanto se usa una temporal única.
        static::creating(function (Envio $e): void {
            $e->codigo_guia ??= 'TMP-'.Str::random(20);
        });

        static::created(function (Envio $e): void {
            $guia = 'PK-'.str_pad((string) $e->getKey(), 6, '0', STR_PAD_LEFT);

            DB::table($e->getTable())->where($e->getKeyName(), $e->getKey())->update(['codigo_guia' => $guia]);
            $e->setAttribute('codigo_guia', $guia)->syncOriginalAttribute('codigo_guia');

            $e->registrarEvento('Envío registrado');
        });

        static::updated(function (Envio $e): void {
            if ($e->wasChanged('estado_envio_id')) {
                $e->registrarEvento($e->notaEvento ?? 'Cambio de estado');
            }
        });
    }

    /** Todo guardado corre en una transacción: el envío y su evento se confirman o se revierten juntos. */
    public function save(array $options = []): bool
    {
        return DB::transaction(fn (): bool => parent::save($options));
    }

    /** Inserta el evento del estado actual; si hay coordenadas guarda el punto geográfico (lng, lat). */
    protected function registrarEvento(string $nota): void
    {
        $usuario = Auth::id();
        $lat = $this->latitudEvento;
        $lng = $this->longitudEvento;

        if ($lat !== null && $lng !== null) {
            DB::insert(
                'INSERT INTO envios.envio_eventos (envio_id, estado_envio_id, nota, usuario_id, ubicacion)
                 VALUES (?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?::double precision, ?::double precision), 4326)::geography)',
                [$this->getKey(), $this->estado_envio_id, $nota, $usuario, (float) $lng, (float) $lat]
            );
        } else {
            DB::table('envios.envio_eventos')->insert([
                'envio_id' => $this->getKey(),
                'estado_envio_id' => $this->estado_envio_id,
                'nota' => $nota,
                'usuario_id' => $usuario,
            ]);
        }

        // Ya se usaron: que no se arrastren a un guardado posterior
        $this->notaEvento = null;
        $this->latitudEvento = null;
        $this->longitudEvento = null;
    }

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
