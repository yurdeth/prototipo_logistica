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
        Schema::create('envios.clientes', function (Blueprint $table) {
            $table->increments('id_cliente');
            $table->string('nombre', 150);
            $table->string('tipo_cliente', 10)->default('PERSONA');
            $table->string('contacto', 150)->nullable();
            $table->integer('territorio_id');
            $table->timestamps();

            $table->foreign('territorio_id', 'clientes_territorio_fk')
                ->references('id_territorio')
                ->on('territorio.territorios');
        });

        DB::statement("ALTER TABLE envios.clientes ADD CONSTRAINT chk_tipo_cliente
            CHECK (tipo_cliente IN ('PERSONA', 'EMPRESA'))");

        Schema::create('envios.estados_envios', function (Blueprint $table) {
            $table->increments('id_estado');
            $table->string('nombre_estado', 40);
            $table->unique('nombre_estado', 'uk_estados_envios_nombre');
        });

        DB::table('envios.estados_envios')->insertOrIgnore(array_map(
            static fn (string $estado): array => ['nombre_estado' => $estado],
            ['REGISTRADO', 'RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA', 'ENTREGADO', 'DEVUELTO', 'FALLIDO']
        ));

        Schema::create('envios.envios', function (Blueprint $table) {
            $table->increments('id_envio');
            $table->string('codigo_guia', 30);
            $table->integer('cliente_remitente_id');
            $table->integer('cliente_destinatario_id');
            $table->integer('sucursal_origen_id');
            $table->string('direccion_destino', 255);
            $table->integer('territorio_destino_id');
            $table->decimal('peso_kg', 10, 2);
            $table->decimal('volumen_m3', 10, 2)->nullable();
            $table->integer('estado_envio_id');
            $table->string('prioridad', 15)->default('NORMAL');
            $table->timestampTz('fecha_registro')->useCurrent();
            $table->timestampTz('fecha_comprometida');
            $table->timestamps();

            $table->foreign('cliente_remitente_id', 'envios_remitente_fk')
                ->references('id_cliente')
                ->on('envios.clientes');

            $table->foreign('cliente_destinatario_id', 'envios_destinatario_fk')
                ->references('id_cliente')
                ->on('envios.clientes');

            $table->foreign('sucursal_origen_id', 'envios_sucursal_origen_fk')
                ->references('id_sucursal')
                ->on('territorio.sucursales');

            $table->foreign('territorio_destino_id', 'envios_territorio_destino_fk')
                ->references('id_territorio')
                ->on('territorio.territorios');

            $table->foreign('estado_envio_id', 'envios_estado_fk')
                ->references('id_estado')
                ->on('envios.estados_envios');

            $table->unique('codigo_guia', 'uk_codigo_guia');
            $table->index('estado_envio_id', 'idx_envios_estado');
            $table->index('fecha_registro', 'idx_envios_fecha_registro');
            $table->index('cliente_remitente_id', 'idx_envios_cliente_remitente');
        });

        DB::statement('ALTER TABLE envios.envios ADD CONSTRAINT chk_peso_envios CHECK (peso_kg > 0)');
        DB::statement('ALTER TABLE envios.envios ADD CONSTRAINT chk_volumen_envios
            CHECK (volumen_m3 IS NULL OR volumen_m3 > 0)');
        DB::statement("ALTER TABLE envios.envios ADD CONSTRAINT chk_prioridad_envios
            CHECK (prioridad IN ('BAJA', 'NORMAL', 'URGENTE'))");
        DB::statement('ALTER TABLE envios.envios ADD CONSTRAINT chk_fechas_envios
            CHECK (fecha_comprometida > fecha_registro)');

        Schema::create('envios.envio_eventos', function (Blueprint $table) {
            $table->increments('id_evento');
            $table->integer('envio_id');
            $table->integer('estado_envio_id');
            $table->timestampTz('ocurrido_en')->useCurrent();
            $table->text('nota')->nullable();

            $table->foreign('envio_id', 'envio_eventos_envio_fk')
                ->references('id_envio')
                ->on('envios.envios')
                ->cascadeOnDelete();

            $table->foreign('estado_envio_id', 'envio_eventos_estado_fk')
                ->references('id_estado')
                ->on('envios.estados_envios');

            $table->index(['envio_id', 'ocurrido_en'], 'idx_envio_eventos_envio');
        });

        DB::statement('ALTER TABLE envios.envio_eventos
            ADD COLUMN IF NOT EXISTS ubicacion geography(Point, 4326)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('envios.envio_eventos');
        Schema::dropIfExists('envios.envios');
        Schema::dropIfExists('envios.estados_envios');
        Schema::dropIfExists('envios.clientes');
    }
};
