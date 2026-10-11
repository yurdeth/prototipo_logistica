<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flota.marca_vehiculo', function (Blueprint $table) {
            $table->increments('id_marca');
            $table->string('nombre', 60);
            $table->boolean('activa')->default(true);

            $table->unique('nombre', 'uk_nombre_marca_vehiculo');
        });

        Schema::create('flota.modelo_vehiculo', function (Blueprint $table) {
            $table->increments('id_modelo');
            $table->integer('marca_vehiculo_id');
            $table->string('nombre', 60);
            $table->boolean('activa')->default(true);

            $table->foreign('marca_vehiculo_id', 'modelo_marca_fk')
                ->references('id_marca')
                ->on('flota.marca_vehiculo')
                ->cascadeOnDelete();

            // Permite el FK compuesto de la pivote: el modelo debe pertenecer a la marca indicada
            $table->unique(['id_modelo', 'marca_vehiculo_id'], 'uk_modelo_marca');
            $table->unique(['marca_vehiculo_id', 'nombre'], 'uk_nombre_modelo_por_marca');
        });

        Schema::create('flota.vehiculo_marca_modelo', function (Blueprint $table) {
            $table->increments('id_vehiculo_marca_modelo');
            $table->integer('vehiculo_id');
            $table->integer('marca_vehiculo_id');
            $table->integer('modelo_vehiculo_id');
            $table->timestamps();

            $table->foreign('vehiculo_id', 'vmm_vehiculo_fk')
                ->references('id_vehiculo')
                ->on('flota.vehiculos')
                ->cascadeOnDelete();

            $table->foreign('marca_vehiculo_id', 'vmm_marca_fk')
                ->references('id_marca')
                ->on('flota.marca_vehiculo');

            // FK compuesto: garantiza que el modelo registrado pertenezca a la marca del registro
            $table->foreign(['modelo_vehiculo_id', 'marca_vehiculo_id'], 'vmm_modelo_marca_fk')
                ->references(['id_modelo', 'marca_vehiculo_id'])
                ->on('flota.modelo_vehiculo');

            $table->unique(
                ['vehiculo_id', 'marca_vehiculo_id', 'modelo_vehiculo_id'],
                'uk_vehiculo_marca_modelo',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flota.vehiculo_marca_modelo');
        Schema::dropIfExists('flota.modelo_vehiculo');
        Schema::dropIfExists('flota.marca_vehiculo');
    }
};
