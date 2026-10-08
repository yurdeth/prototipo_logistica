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
        Schema::create('territorio.territorios', function (Blueprint $table) {
            $table->increments('id_territorio');
            $table->string('nombre_territorio', 120);
            $table->string('tipo_territorio', 15);
            $table->integer('territorio_padre_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('territorio_padre_id', 'territorios_padre_fk')
                ->references('id_territorio')
                ->on('territorio.territorios');

            $table->index('territorio_padre_id', 'territorios_padre_idx');
        });

        DB::statement("ALTER TABLE territorio.territorios ADD CONSTRAINT territorios_tipo_chk
            CHECK (tipo_territorio IN ('DEPARTAMENTO', 'MUNICIPIO', 'DISTRITO'))");

        Schema::create('territorio.sucursales', function (Blueprint $table) {
            $table->increments('id_sucursal');
            $table->string('codigo', 20);
            $table->string('nombre_sucursal', 120);
            $table->integer('territorio_id');
            $table->string('direccion', 255);
            $table->decimal('latitud', 9, 6)->nullable();
            $table->decimal('longitud', 9, 6)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->foreign('territorio_id', 'sucursales_territorio_fk')
                ->references('id_territorio')
                ->on('territorio.territorios');

            $table->unique('codigo', 'uk_codigo_sucursales');
        });

        DB::statement('ALTER TABLE territorio.sucursales ADD CONSTRAINT chk_latitud_sucursales
            CHECK (latitud BETWEEN -90 AND 90)');

        DB::statement('ALTER TABLE territorio.sucursales ADD CONSTRAINT chk_longitud_sucursales
            CHECK (longitud BETWEEN -180 AND 180)');

        // geography derivado de latitud/longitud; los cálculos espaciales van sobre esta columna
        DB::statement('ALTER TABLE territorio.sucursales
            ADD COLUMN IF NOT EXISTS ubicacion geography(Point, 4326)
            GENERATED ALWAYS AS (
                ST_SetSRID(
                    ST_MakePoint(longitud::double precision, latitud::double precision),
                    4326
                )::geography
            ) STORED');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_sucursales_ubicacion
            ON territorio.sucursales USING GIST (ubicacion)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('territorio.sucursales');
        Schema::dropIfExists('territorio.territorios');
    }
};
