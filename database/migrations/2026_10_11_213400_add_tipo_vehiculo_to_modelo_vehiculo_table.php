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
        Schema::table('flota.modelo_vehiculo', function (Blueprint $table) {
            // Tipo al que pertenece el modelo (Motocicleta, Van, Camion Ligero...);
            // determina la categoria del vehiculo y habilita la seleccion del tipo en el formulario
            $table->integer('tipo_vehiculo_id')->nullable()->after('marca_vehiculo_id');

            $table->foreign('tipo_vehiculo_id', 'modelo_tipo_fk')
                ->references('id_tipo_vehiculo')
                ->on('flota.tipos_vehiculos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('flota.modelo_vehiculo', function (Blueprint $table) {
            $table->dropForeign('modelo_tipo_fk');
            $table->dropColumn('tipo_vehiculo_id');
        });
    }
};
