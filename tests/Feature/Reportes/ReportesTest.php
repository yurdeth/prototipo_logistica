<?php

namespace Tests\Feature\Reportes;

use App\Models\Empleado;
use App\Models\Envio;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\RedLogisticaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RedLogisticaSeeder::class);
        $this->usuario = User::factory()->create();
    }

    /** Envío con el compromiso fijado a N horas desde ahora. Se fija por SQL en hora local de pared (la convención del sistema); para vencidos el registro se reescribe también, porque la base exige compromiso posterior al registro. */
    private function envioConCompromiso(int $horas): Envio
    {
        $envio = Envio::factory()->hasta('EN_CENTRO')->create();

        $local = fn (int $horasDesdeAhora) => now()
            ->setTimezone('America/El_Salvador')
            ->addHours($horasDesdeAhora)
            ->format('Y-m-d H:i:s');

        if ($horas < 0) {
            DB::table('envios.envios')
                ->where('id_envio', $envio->id_envio)
                ->update([
                    'fecha_registro' => $local(-72),
                    'fecha_comprometida' => $local($horas),
                ]);
        } else {
            // Fija exacto, sin el azar del factory
            DB::table('envios.envios')
                ->where('id_envio', $envio->id_envio)
                ->update(['fecha_comprometida' => $local($horas)]);
        }

        return $envio;
    }

    private function despachoActivo(Envio $envio): int
    {
        foreach (['PLANIFICADO', 'EN_CURSO', 'COMPLETADO'] as $estado) {
            DB::table('rutas.estados_despachos')->insertOrIgnore(['nombre_estado' => $estado]);
        }

        $despachoId = (int) DB::table('rutas.despachos')->insertGetId([
            'sucursal_origen_id' => $envio->sucursal_origen_id,
            'conductor_empleado_id' => Empleado::factory()->create()->id_empleado,
            'vehiculo_id' => Vehiculo::factory()->create()->id_vehiculo,
            'fecha' => now()->toDateString(),
            'estado_despacho_id' => DB::table('rutas.estados_despachos')->where('nombre_estado', 'EN_CURSO')->value('id_estado'),
        ], 'id_despacho');

        DB::table('rutas.despacho_paradas')->insert([
            'despacho_id' => $despachoId,
            'envio_id' => $envio->id_envio,
            'orden' => 1,
        ]);

        return $despachoId;
    }

    public function test_clasifica_los_envios_abiertos_por_plazo(): void
    {
        $this->envioConCompromiso(-5); // vencido
        $this->envioConCompromiso(24); // vence pronto
        $this->envioConCompromiso(120); // en plazo
        Envio::factory()->entregadoTarde()->create(); // cerrado: no cuenta

        $this->actingAs($this->usuario)
            ->get('/reportes')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('reportes/ReportesIndex')
                    ->where('kpis.vencidos', 1)
                    ->where('kpis.vence_pronto', 1)
                    ->where('kpis.en_plazo', 1)
                    ->has('envios.data', 3),
            );
    }

    public function test_el_filtro_de_plazo_deja_solo_los_vencidos(): void
    {
        $this->envioConCompromiso(-5);
        $this->envioConCompromiso(120);

        $this->actingAs($this->usuario)
            ->get('/reportes?plazo=vencido')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('envios.total', 1)
                    ->where('envios.data.0.plazo', 'vencido'),
            );
    }

    public function test_los_despachos_activos_se_clasifican_por_el_compromiso_de_sus_envios(): void
    {
        $vencido = $this->envioConCompromiso(-3);
        $enPlazo = $this->envioConCompromiso(120);
        $this->despachoActivo($vencido);
        $this->despachoActivo($enPlazo);

        // Sin filtro: ambos, el que tiene vencidos primero
        $this->actingAs($this->usuario)
            ->get('/reportes')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('despachos', 2)
                    ->where('despachos.0.vencidos', 1)
                    ->where('despachos.1.vencidos', 0)
                    ->where('despachos.1.en_plazo', 1),
            );

        // Filtro vencido: solo el despacho con parada vencida
        $this->actingAs($this->usuario)
            ->get('/reportes?plazo=vencido')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page->has('despachos', 1),
            );
    }

    public function test_las_incidencias_se_resumen_por_tipo_y_periodo(): void
    {
        $envio = $this->envioConCompromiso(30);
        $transito = DB::table('rutas.tipos_incidencias')->where('nombre_tipo', 'TRANSITO')->value('id_tipo');
        $clima = DB::table('rutas.tipos_incidencias')->where('nombre_tipo', 'CLIMA')->value('id_tipo');

        DB::table('rutas.incidencias')->insert([
            ['tipo_incidencia_id' => $transito, 'envio_id' => $envio->id_envio, 'minutos_retraso' => 40, 'ocurrio_en' => now(), 'descripcion' => 'Trancón en la carretera'],
            ['tipo_incidencia_id' => $clima, 'envio_id' => $envio->id_envio, 'minutos_retraso' => 10, 'ocurrio_en' => now()->subDays(20), 'descripcion' => null],
        ]);

        // Últimos 7 días: solo la de tránsito
        $this->actingAs($this->usuario)
            ->get('/reportes?dias=7')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('kpis.incidencias', 1)
                    ->where('kpis.minutos_retraso', 40)
                    ->has('incidencias.data', 1)
                    ->where('incidencias.data.0.tipo', 'TRANSITO')
                    ->where('incidencias.data.0.descripcion', 'Trancón en la carretera')
                    ->has('incidencias.por_tipo', 1),
            );

        // Todo el historial: las dos, con su cuadre por tipo
        $this->actingAs($this->usuario)
            ->get('/reportes?dias=0')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('kpis.incidencias', 2)
                    ->where('kpis.minutos_retraso', 50)
                    ->has('incidencias.por_tipo', 2),
            );

        // Filtro por tipo
        $this->actingAs($this->usuario)
            ->get("/reportes?dias=0&tipo={$clima}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->where('incidencias.total', 1)
                    ->where('incidencias.data.0.tipo', 'CLIMA'),
            );
    }

    public function test_exporta_el_reporte_en_csv_con_las_tres_secciones(): void
    {
        $envio = $this->envioConCompromiso(-5); // vencido
        $this->envioConCompromiso(120); // en plazo
        $transito = DB::table('rutas.tipos_incidencias')->where('nombre_tipo', 'TRANSITO')->value('id_tipo');
        DB::table('rutas.incidencias')->insert([
            ['tipo_incidencia_id' => $transito, 'envio_id' => $envio->id_envio, 'minutos_retraso' => 25, 'ocurrio_en' => now(), 'descripcion' => '=PELIGRO'],
        ]);

        $respuesta = $this->actingAs($this->usuario)
            ->get('/reportes/exportar?plazo=vencido');

        $respuesta->assertOk();
        expect($respuesta->headers->get('Content-Type'))->toContain('text/csv');

        $csv = $respuesta->streamedContent();
        expect($csv)->toContain('ENVÍOS POR VENCER')
            ->and($csv)->toContain($envio->codigo_guia)
            ->and($csv)->toContain('DESPACHOS POR PLAZO')
            ->and($csv)->toContain('INCIDENCIAS')
            // La incidencia sale aunque el filtro de plazo sea vencido (afecta envíos y despachos, no incidencias)
            ->and($csv)->toContain('25')
            // Texto que empieza con = sale neutralizado para Excel
            ->and($csv)->toContain("'=PELIGRO");
    }
}
