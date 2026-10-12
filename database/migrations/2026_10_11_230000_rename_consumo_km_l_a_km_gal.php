<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El combustible en El Salvador se vende por galón, y el estándar práctico
 * del sector de distribución local es kilómetros por galón. Se renombra la
 * columna y su CHECK para alinear el esquema con esa unidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flota.vehiculos', function (Blueprint $table) {
            $table->renameColumn('consumo_km_l', 'consumo_km_gal');
        });

        DB::statement('ALTER TABLE flota.vehiculos DROP CONSTRAINT IF EXISTS chk_consumo_km_l_vehiculos');
        DB::statement('ALTER TABLE flota.vehiculos ADD CONSTRAINT chk_consumo_km_gal_vehiculos CHECK (consumo_km_gal > 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE flota.vehiculos DROP CONSTRAINT IF EXISTS chk_consumo_km_gal_vehiculos');
        DB::statement('ALTER TABLE flota.vehiculos ADD CONSTRAINT chk_consumo_km_l_vehiculos CHECK (consumo_km_l > 0)');

        Schema::table('flota.vehiculos', function (Blueprint $table) {
            $table->renameColumn('consumo_km_gal', 'consumo_km_l');
        });
    }
};
