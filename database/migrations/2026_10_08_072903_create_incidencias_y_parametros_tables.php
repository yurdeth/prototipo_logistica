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
        Schema::create('rutas.tipos_incidencias', function (Blueprint $table) {
            $table->increments('id_tipo');
            $table->string('nombre_tipo', 60);
            $table->unique('nombre_tipo', 'uk_tipos_incidencias_nombre');
        });

        DB::table('rutas.tipos_incidencias')->insertOrIgnore(array_map(
            static fn (string $tipo): array => ['nombre_tipo' => $tipo],
            ['FALLA_MECANICA', 'TRANSITO', 'CLIMA', 'CLIENTE_AUSENTE', 'DIRECCION_ERRONEA', 'OTRO']
        ));

        Schema::create('rutas.incidencias', function (Blueprint $table) {
            $table->increments('id_incidencia');
            $table->integer('tipo_incidencia_id');
            $table->integer('despacho_id')->nullable();
            $table->integer('envio_id')->nullable();
            $table->timestampTz('ocurrio_en')->useCurrent();
            $table->text('descripcion')->nullable();
            $table->smallInteger('minutos_retraso')->default(0);

            $table->foreign('tipo_incidencia_id', 'incidencias_tipo_fk')
                ->references('id_tipo')
                ->on('rutas.tipos_incidencias');

            $table->foreign('despacho_id', 'incidencias_despacho_fk')
                ->references('id_despacho')
                ->on('rutas.despachos')
                ->cascadeOnDelete();

            $table->foreign('envio_id', 'incidencias_envio_fk')
                ->references('id_envio')
                ->on('envios.envios');

            $table->index('despacho_id', 'idx_incidencias_despacho');
            $table->index('tipo_incidencia_id', 'idx_incidencias_tipo');
        });

        DB::statement('ALTER TABLE rutas.incidencias ADD CONSTRAINT chk_minutos_incidencias
            CHECK (minutos_retraso >= 0)');
        DB::statement('ALTER TABLE rutas.incidencias ADD CONSTRAINT chk_incidencias_contexto
            CHECK (despacho_id IS NOT NULL OR envio_id IS NOT NULL)');

        Schema::create('rutas.parametros', function (Blueprint $table) {
            $table->increments('id_parametro');
            $table->string('clave', 60);
            $table->decimal('valor', 12, 4);
            $table->date('vigente_desde')->default(DB::raw('CURRENT_DATE'));

            $table->unique(['clave', 'vigente_desde'], 'uk_parametros_clave_vigencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rutas.parametros');
        Schema::dropIfExists('rutas.incidencias');
        Schema::dropIfExists('rutas.tipos_incidencias');
    }
};
