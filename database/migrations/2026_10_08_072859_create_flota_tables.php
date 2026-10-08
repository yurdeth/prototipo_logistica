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
        Schema::create('flota.tipos_vehiculos', function (Blueprint $table) {
            $table->increments('id_tipo_vehiculo');
            $table->string('nombre', 60);
            $table->string('marca', 100);
            $table->decimal('capacidad_kg', 10, 2);
            $table->decimal('capacidad_m3', 10, 2)->nullable();
        });

        DB::statement('ALTER TABLE flota.tipos_vehiculos ADD CONSTRAINT chk_capacidad_kg_vehiculo
            CHECK (capacidad_kg > 0)');

        DB::statement('ALTER TABLE flota.tipos_vehiculos ADD CONSTRAINT chk_capacidad_m3_vehiculo
            CHECK (capacidad_m3 > 0)');

        Schema::create('flota.estados_vehiculos', function (Blueprint $table) {
            $table->increments('id_estado');
            $table->string('nombre_estado', 40);
            $table->unique('nombre_estado', 'uk_estados_vehiculos_nombre');
        });

        Schema::create('flota.vehiculos', function (Blueprint $table) {
            $table->increments('id_vehiculo');
            $table->string('placa', 15);
            $table->integer('tipo_vehiculo_id');
            $table->integer('estado_vehiculo_id');
            $table->integer('sucursal_id');
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->smallInteger('anio')->nullable();
            $table->decimal('consumo_km_l', 5, 2)->nullable();
            $table->timestamps();

            $table->foreign('tipo_vehiculo_id', 'vehiculos_tipo_fk')
                ->references('id_tipo_vehiculo')
                ->on('flota.tipos_vehiculos');

            $table->foreign('estado_vehiculo_id', 'vehiculos_estado_fk')
                ->references('id_estado')
                ->on('flota.estados_vehiculos');

            $table->foreign('sucursal_id', 'vehiculos_sucursal_fk')
                ->references('id_sucursal')
                ->on('territorio.sucursales');

            $table->unique('placa', 'uk_placa_vehiculos');
        });

        DB::statement('ALTER TABLE flota.vehiculos ADD CONSTRAINT chk_anio_vehiculos
            CHECK (anio BETWEEN 1990 AND 2100)');

        DB::statement('ALTER TABLE flota.vehiculos ADD CONSTRAINT chk_consumo_km_l_vehiculos
            CHECK (consumo_km_l > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flota.vehiculos');
        Schema::dropIfExists('flota.estados_vehiculos');
        Schema::dropIfExists('flota.tipos_vehiculos');
    }
};
