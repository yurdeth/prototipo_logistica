<?php

namespace Database\Seeders;

use App\Enums\TipoIncidenciaEnum;
use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\Envio;
use App\Models\Parametro;
use App\Models\Vehiculo;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Historia simulada de la operación: entre 30 y 60 días con 20 a 80 envíos por
 * día, despachos con vehículo y conductor, incidencias ocasionales y al menos
 * un día con el tramo congestionado penalizado (sin ese día, la demo de
 * congestión no tiene datos que mostrar).
 *
 * El volumen es configurable: la demo corre con los valores por defecto y los
 * tests usan un rango corto para no esperar minutos por semilla.
 */
class HistoriaSeeder extends Seeder
{
    /** Volumen por defecto de la demo. */
    /** Censo mínimo de clientes: remitentes y destinatarios variados. */
    private const CLIENTES_MIN = 32;

    private const DIAS = 45;

    private const ENVIO_MIN = 20;

    private const ENVIO_MAX = 80;

    /** Umbral de cumplimiento razonable: la mayoría de las entregas llegan a tiempo. */
    private const PORCENTAJE_A_TIEMPO = 85;

    private const PORCENTAJE_FALLIDO = 8;

    private const PORCENTAJE_DEVUELTO = 5;

    /** Uno de cada ~16 despachos tiene una incidencia menor. */
    private const PROBABILIDAD_INCIDENCIA = 0.06;

    public function __construct(
        private int $dias = self::DIAS,
        private int $envioMin = self::ENVIO_MIN,
        private int $envioMax = self::ENVIO_MAX,
    ) {}

    public function run(): void
    {
        $this->asegurarFlotaYPersonal();

        // El día congestionado va a mitad de la historia simulada
        $diaCongestionado = now()->subDays(max(1, intdiv($this->dias, 2)));

        DB::transaction(function () use ($diaCongestionado): void {
            $this->marcarCongestion($diaCongestionado);
        });

        for ($atras = $this->dias; $atras >= 1; $atras--) {
            $dia = now()->subDays($atras);
            $esDiaCongestionado = $dia->isSameDay($diaCongestionado);

            DB::transaction(function () use ($atras, $dia, $esDiaCongestionado): void {
                $this->enviosDelDia($atras, $esDiaCongestionado);
                $this->despachosDelDia($dia, $esDiaCongestionado);
            });
        }
    }

    /**
     * Envíos del día: reparto de estados finales (entregados, en camino,
     * fallidos y devueltos). Los desvíos del camino feliz son los que dan
     * materia a los indicadores de incidencias.
     */
    private function enviosDelDia(int $atras, bool $congestionado): void
    {
        $cantidad = (int) random_int($this->envioMin, $this->envioMax);

        for ($i = 0; $i < $cantidad; $i++) {
            $factory = Envio::factory()->hace($atras);
            $suerte = random_int(1, 100);

            $factoryFinal = match (true) {
                $suerte <= self::PORCENTAJE_FALLIDO => $factory->hasta('FALLIDO'),
                $suerte <= self::PORCENTAJE_FALLIDO + self::PORCENTAJE_DEVUELTO => $factory->hasta('DEVUELTO'),
                $suerte <= self::PORCENTAJE_FALLIDO + self::PORCENTAJE_DEVUELTO + 15 => $factory->hasta(
                    fake()->randomElement(['RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA']),
                ),
                // En el día congestionado, la mitad de los entregados llega tarde
                $congestionado && random_int(1, 100) <= 50 => $factory->entregadoTarde(),
                default => random_int(1, 100) <= self::PORCENTAJE_A_TIEMPO
                    ? $factory->entregadoATiempo()
                    : $factory->entregadoTarde(),
            };

            $factoryFinal->create();
        }
    }

    /**
     * Despachos del día: salida a reparto desde una sucursal con vehículo y
     * conductor, con 2 a 5 envíos como paradas y, de vez en cuando, una
     * incidencia con su retraso.
     */
    private function despachosDelDia(CarbonInterface $dia, bool $congestionado): void
    {
        $conductores = Empleado::query()->whereNotNull('numero_licencia')->inRandomOrder()->get();
        $vehiculos = Vehiculo::query()->inRandomOrder()->get();
        // Despachar exige envíos que salgan ese día de la sucursal
        $sucursales = DB::table('envios.envios')
            ->whereDate('fecha_registro', $dia->toDateString())
            ->distinct()
            ->pluck('sucursal_origen_id');

        if ($conductores->isEmpty() || $vehiculos->isEmpty() || $sucursales->isEmpty()) {
            return;
        }

        $enCurso = $dia->diffInDays(now()) < 2;

        for ($i = 0; $i < min((int) random_int(3, 6), $sucursales->count()); $i++) {
            $sucursal = (int) $sucursales->random();
            $km = (float) random_int(40, 180);

            $despachoId = (int) DB::table('rutas.despachos')->insertGetId([
                'sucursal_origen_id' => $sucursal,
                'conductor_empleado_id' => $conductores->random()->id_empleado,
                'vehiculo_id' => $vehiculos->random()->id_vehiculo,
                'fecha' => $dia->toDateString(),
                'estado_despacho_id' => $this->estadoDespachoId($enCurso ? 'EN_CURSO' : 'COMPLETADO'),
                'km_planificados' => $km,
                'km_recorridos' => $enCurso ? null : round($km * random_int(95, 115) / 100, 2),
                'costo_estimado' => round($km * 1.25, 2),
                'created_at' => $dia,
                'updated_at' => $dia,
            ], 'id_despacho');

            $this->paradasDelDespacho($despachoId, $dia, $sucursal, $congestionado);
        }
    }

    /**
     * Paradas del despacho con envíos entregados o en reparto de la misma
     * sucursal; en el día congestionado las horas reales se despegan de las
     * estimadas y suele registrar una incidencia de tránsito.
     */
    private function paradasDelDespacho(int $despachoId, CarbonInterface $dia, int $sucursal, bool $congestionado): void
    {
        $envios = Envio::query()
            ->where('sucursal_origen_id', $sucursal)
            ->whereDate('fecha_registro', $dia->toDateString())
            ->inRandomOrder()
            ->limit((int) random_int(2, 5))
            ->get();

        $base = $dia->copy()->setTime(9, 0);
        $orden = 0;

        foreach ($envios as $envio) {
            $orden++;
            $estimada = $base->copy()->addMinutes(40 * $orden);
            // El retraso típico: 5-25 minutos; en el día congestionado, 40-90
            $retraso = $congestionado ? (int) random_int(40, 90) : (int) random_int(0, 25);
            $real = $envio->estado->nombre_estado === 'ENTREGADO' ? $estimada->copy()->addMinutes($retraso) : null;

            DB::table('rutas.despacho_paradas')->insert([
                'despacho_id' => $despachoId,
                'envio_id' => $envio->id_envio,
                'orden' => $orden,
                'hora_estimada' => $estimada,
                'hora_real' => $real,
            ]);

            if ($congestionado && $orden === 1) {
                DB::table('rutas.incidencias')->insert([
                    'tipo_incidencia_id' => $this->tipoIncidenciaId(TipoIncidenciaEnum::TRANSITO->value),
                    'despacho_id' => $despachoId,
                    'envio_id' => $envio->id_envio,
                    'ocurrio_en' => $real ?? $estimada,
                    'descripcion' => 'Congestión en el tramo San Salvador-Santa Ana; ruta alternativa por Sonsonate',
                    'minutos_retraso' => $retraso,
                ]);
            } elseif (mt_rand() / mt_getrandmax() < self::PROBABILIDAD_INCIDENCIA) {
                DB::table('rutas.incidencias')->insert([
                    'tipo_incidencia_id' => $this->tipoIncidenciaId(
                        fake()->randomElement(['TRANSITO', 'CLIENTE_AUSENTE', 'FALLA_MECANICA', 'CLIMA']),
                    ),
                    'despacho_id' => $despachoId,
                    'envio_id' => $envio->id_envio,
                    'ocurrio_en' => $real ?? $estimada,
                    'descripcion' => 'Novedad registrada por el conductor en ruta',
                    'minutos_retraso' => (int) random_int(5, 40),
                ]);
            }
        }
    }

    /** Recursos mínimos para la simulación, solo si la base está vacía. */
    private function asegurarFlotaYPersonal(): void
    {
        // Un censo bajo de clientes hace que remitente y destinatario sean
        // siempre los mismos; el demo necesita variedad de nombres.
        if (Cliente::query()->count() < self::CLIENTES_MIN) {
            Cliente::factory()->count(self::CLIENTES_MIN - Cliente::query()->count())->create();
        }

        if (Vehiculo::query()->count() === 0) {
            Vehiculo::factory()->count(18)->create();
        }

        if (Empleado::query()->whereNotNull('numero_licencia')->count() < 6) {
            Empleado::factory()->conductor()->count(8)->create();
        }
    }

    /**
     * Re-versiona el parámetro del tramo congestionado al día simulado: así el
     * ETL y la demo leen "ese día, ese tramo pesó más".
     */
    private function marcarCongestion(CarbonInterface $diaCongestionado): void
    {
        $idConexion = (int) Parametro::query()
            ->vigente(RedLogisticaSeeder::CLAVE_CONGESTION)
            ->value('valor');

        if ($idConexion > 0) {
            Parametro::query()->updateOrCreate(
                ['clave' => RedLogisticaSeeder::CLAVE_CONGESTION, 'vigente_desde' => $diaCongestionado->toDateString()],
                ['valor' => $idConexion],
            );
        }
    }

    private function estadoDespachoId(string $nombre): int
    {
        return (int) DB::table('rutas.estados_despachos')->where('nombre_estado', $nombre)->value('id_estado');
    }

    private function tipoIncidenciaId(string $nombre): int
    {
        return (int) DB::table('rutas.tipos_incidencias')->where('nombre_tipo', $nombre)->value('id_tipo');
    }
}
