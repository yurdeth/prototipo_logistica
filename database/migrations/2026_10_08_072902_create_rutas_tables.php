<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rutas.estados_despachos', function (Blueprint $table) {
            $table->increments('id_estado');
            $table->string('nombre_estado', 40);
            $table->unique('nombre_estado', 'uk_estados_despachos_nombre');
        });

        DB::table('rutas.estados_despachos')->insertOrIgnore(array_map(
            static fn (string $estado): array => ['nombre_estado' => $estado],
            ['PLANIFICADO', 'EN_CURSO', 'COMPLETADO']
        ));

        Schema::create('rutas.despachos', function (Blueprint $table) {
            $table->increments('id_despacho');
            $table->integer('sucursal_origen_id');
            $table->integer('conductor_empleado_id');
            $table->integer('vehiculo_id');
            $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
            $table->integer('estado_despacho_id');
            $table->decimal('km_planificados', 10, 2)->nullable();
            $table->decimal('km_recorridos', 10, 2)->nullable();
            $table->decimal('costo_estimado', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('sucursal_origen_id', 'despachos_sucursal_fk')
                ->references('id_sucursal')
                ->on('territorio.sucursales');

            $table->foreign('conductor_empleado_id', 'despachos_conductor_fk')
                ->references('id_empleado')
                ->on('personal.empleados');

            $table->foreign('vehiculo_id', 'despachos_vehiculo_fk')
                ->references('id_vehiculo')
                ->on('flota.vehiculos');

            $table->foreign('estado_despacho_id', 'despachos_estado_fk')
                ->references('id_estado')
                ->on('rutas.estados_despachos');

            $table->index('fecha', 'idx_despachos_fecha');
            $table->index('vehiculo_id', 'idx_despachos_vehiculo');
            $table->index('conductor_empleado_id', 'idx_despachos_conductor');
        });

        DB::statement('ALTER TABLE rutas.despachos ADD CONSTRAINT chk_km_planificados_despachos
            CHECK (km_planificados IS NULL OR km_planificados > 0)');
        DB::statement('ALTER TABLE rutas.despachos ADD CONSTRAINT chk_km_recorridos_despachos
            CHECK (km_recorridos IS NULL OR km_recorridos >= 0)');
        DB::statement('ALTER TABLE rutas.despachos ADD CONSTRAINT chk_costo_despachos
            CHECK (costo_estimado IS NULL OR costo_estimado >= 0)');

        Schema::create('rutas.despacho_paradas', function (Blueprint $table) {
            $table->increments('id_parada');
            $table->integer('despacho_id');
            $table->integer('envio_id');
            $table->smallInteger('orden');
            $table->timestampTz('hora_estimada')->nullable();
            $table->timestampTz('hora_real')->nullable();

            $table->foreign('despacho_id', 'despacho_paradas_despacho_fk')
                ->references('id_despacho')
                ->on('rutas.despachos')
                ->cascadeOnDelete();

            $table->foreign('envio_id', 'despacho_paradas_envio_fk')
                ->references('id_envio')
                ->on('envios.envios');

            $table->unique(['despacho_id', 'orden'], 'uk_despacho_paradas_orden');
            $table->unique(['despacho_id', 'envio_id'], 'uk_despacho_paradas_envio');
            $table->index('envio_id', 'idx_despacho_paradas_envio');
        });

        DB::statement('ALTER TABLE rutas.despacho_paradas ADD CONSTRAINT chk_paradas_horas
            CHECK (hora_real IS NULL OR hora_estimada IS NULL OR hora_real >= hora_estimada)');

        Schema::create('rutas.conexiones', function (Blueprint $table) {
            $table->increments('id_conexion');
            $table->integer('origen_sucursal_id');
            $table->integer('destino_sucursal_id');
            $table->decimal('distancia_km', 10, 2);
            $table->smallInteger('tiempo_min');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->foreign('origen_sucursal_id', 'conexiones_origen_fk')
                ->references('id_sucursal')
                ->on('territorio.sucursales');

            $table->foreign('destino_sucursal_id', 'conexiones_destino_fk')
                ->references('id_sucursal')
                ->on('territorio.sucursales');

            $table->unique(['origen_sucursal_id', 'destino_sucursal_id'], 'uk_conexiones_par');
            $table->index('destino_sucursal_id', 'idx_conexiones_destino');
        });

        DB::statement('ALTER TABLE rutas.conexiones ADD CONSTRAINT chk_distancia_conexiones
            CHECK (distancia_km > 0)');
        DB::statement('ALTER TABLE rutas.conexiones ADD CONSTRAINT chk_tiempo_conexiones
            CHECK (tiempo_min > 0)');
        DB::statement('ALTER TABLE rutas.conexiones ADD CONSTRAINT chk_conexiones_autoreferencia
            CHECK (origen_sucursal_id <> destino_sucursal_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rutas.conexiones');
        Schema::dropIfExists('rutas.despacho_paradas');
        Schema::dropIfExists('rutas.despachos');
        Schema::dropIfExists('rutas.estados_despachos');
    }
};
