<?php

namespace Database\Factories;

use App\Enums\EstadoEnvioEnum;
use App\Models\Envio;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * Envío con su historia completa: cada transición de estado pasa por el modelo,
 * así que son los hooks de trazabilidad los que encadenan los eventos; al final
 * se reasientan los tiempos para que queden crecientes y coherentes con la fecha
 * de registro y el estado final (sin eso, los indicadores darían números falsos).
 *
 * Estados útiles:
 *   Envio::factory()->hasta('EN_RUTA')       envío en camino
 *   Envio::factory()->entregadoATiempo()     entrega antes de la comprometida
 *   Envio::factory()->entregadoTarde()       entrega fuera de plazo (incidencia)
 *   Envio::factory()->hace(45)->hasta(...)   historia simulada de hace N días
 *
 * @extends Factory<Envio>
 */
class EnvioFactory extends Factory
{
    /** Camino normal del ciclo de vida; DEVUELTO/FALLIDO salen de un punto intermedio. */
    private const CAMINO = ['RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA', 'ENTREGADO'];

    /** Nota por estado, redactada como la dejaría el operador en el rastro. */
    private const NOTAS = [
        'RECOLECTADO' => 'Paquete recolectado en sucursal de origen',
        'EN_TRANSITO' => 'En tránsito hacia el centro de distribución',
        'EN_CENTRO' => 'Clasificado en centro de distribución',
        'EN_RUTA' => 'Salida a reparto',
        'ENTREGADO' => 'Entregado al destinatario',
        'DEVUELTO' => 'Retornado a la sucursal de origen',
        'FALLIDO' => 'Entrega fallida; pendiente reintentar',
    ];

    /**
     * Plan del rastro para el próximo lote creado. Es estática porque Model::factory()
     * encadena count()/state() sobre clones de la factory: los hooks afterCreating
     * quedan ligados a la instancia original, así que una propiedad de instancia
     * mutada por hasta()/entregadoTarde() no llegaría al callback.
     *
     * @var array{final: string, diasAtras: int, aTiempo: bool|null}
     */
    private static array $plan = ['final' => 'ENTREGADO', 'diasAtras' => 0, 'aTiempo' => null];

    public function definition(): array
    {
        $registro = now()->subDays(self::$plan['diasAtras'])->setTime(
            (int) fake()->numberBetween(8, 17),
            (int) fake()->numberBetween(0, 59),
        );

        // Peso según lo que cabe en la flota: desde documentos hasta paquetes de bodega
        $peso = fake()->randomFloat(2, 0.5, fake()->randomElement([15, 60, 300, 800]));

        // Un paquete rara vez viaja de alguien a sí mismo: si hay más de un
        // cliente, destinatario y remitente difieren.
        $remitente = ClienteFactory::existente();

        return [
            'cliente_remitente_id' => $remitente->id_cliente,
            'cliente_destinatario_id' => ClienteFactory::distintoDe($remitente)->id_cliente,
            'sucursal_origen_id' => (Sucursal::query()->inRandomOrder()->first() ?? Sucursal::factory()->create())->id_sucursal,
            'direccion_destino' => fake()->streetAddress().', '.fake()->city(),
            'territorio_destino_id' => TerritorioFactory::departamento(fake()->randomElement(TerritorioFactory::DEPARTAMENTOS))->id_territorio,
            'peso_kg' => $peso,
            'volumen_m3' => fake()->boolean(60) ? fake()->randomFloat(2, 0.01, 2) : null,
            'estado_envio_id' => $this->estadoId(EstadoEnvioEnum::REGISTRADO->value),
            'prioridad' => fake()->randomElement(['BAJA', 'NORMAL', 'NORMAL', 'URGENTE']),
            'fecha_registro' => $registro,
            'fecha_comprometida' => $registro->copy()->addDays((int) fake()->numberBetween(1, 4)),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Envio $envio): void {
            $this->encadenar($envio, self::$plan['final']);
            $this->reasentarTiempos($envio, self::$plan['final'], self::$plan['aTiempo']);
        });
    }

    /** Estado final del rastro (por defecto ENTREGADO). */
    public function hasta(string $estado): static
    {
        self::$plan['final'] = $estado;

        return $this;
    }

    /** Historia simulada: registrado hace N días. */
    public function hace(int $dias): static
    {
        self::$plan['diasAtras'] = $dias;

        return $this;
    }

    /** Entrega dentro del plazo (feliz). */
    public function entregadoATiempo(): static
    {
        self::$plan['final'] = 'ENTREGADO';
        self::$plan['aTiempo'] = true;

        return $this;
    }

    /** Entrega fuera de plazo: alimenta la tasa de cumplimiento y las incidencias. */
    public function entregadoTarde(): static
    {
        self::$plan['final'] = 'ENTREGADO';
        self::$plan['aTiempo'] = false;

        return $this;
    }

    /**
     * Recorre el ciclo hasta el estado final vía save(), para que el modelo
     * registre cada evento dentro de su transacción.
     */
    private function encadenar(Envio $envio, string $final): void
    {
        $pasos = [];

        if (in_array($final, self::CAMINO, true)) {
            $pasos = array_slice(self::CAMINO, 0, array_search($final, self::CAMINO, true) + 1);
        } else {
            // DEVUELTO/FALLIDO: el paquete avanzó algo antes de la novedad
            $avance = array_slice(self::CAMINO, 0, (int) random_int(1, 3));
            $pasos = [...$avance, $final];
        }

        foreach ($pasos as $paso) {
            $envio->notaEvento = self::NOTAS[$paso];
            $envio->estado_envio_id = $this->estadoId($paso);
            $envio->save();
        }
    }

    /**
     * Los hooks sellaron los eventos "ahora"; aquí se reescriben los tiempos
     * para que la historia quede creciente entre fecha_registro y un cierre
     * coherente con el plazo (a tiempo o tarde).
     */
    private function reasentarTiempos(Envio $envio, string $final, ?bool $aTiempo): void
    {
        $envio->refresh();

        $inicio = $envio->fecha_registro->copy();
        $comprometida = $envio->fecha_comprometida->copy();

        $cierre = match ($aTiempo) {
            true => $comprometida->copy()->subHours((int) random_int(2, 20)),
            false => $comprometida->copy()->addHours((int) random_int(2, 30)),
            null => $inicio->copy()->addHours(max(4, (int) $inicio->diffInHours($comprometida) - (int) random_int(1, 12))),
        };

        $eventos = DB::table('envios.envio_eventos')
            ->where('envio_id', $envio->id_envio)
            ->orderBy('id_evento')
            ->pluck('id_evento');

        $tramo = $inicio->diffInSeconds($cierre);
        $total = $eventos->count();

        foreach ($eventos->values() as $i => $idEvento) {
            DB::table('envios.envio_eventos')
                ->where('id_evento', $idEvento)
                ->update(['ocurrido_en' => $inicio->copy()->addSeconds((int) round($tramo * ($i + 1) / $total))]);
        }

        DB::table('envios.envios')
            ->where('id_envio', $envio->id_envio)
            ->update(['updated_at' => $cierre]);
    }

    private function estadoId(string $nombre): int
    {
        return (int) DB::table('envios.estados_envios')->where('nombre_estado', $nombre)->value('id_estado');
    }
}
