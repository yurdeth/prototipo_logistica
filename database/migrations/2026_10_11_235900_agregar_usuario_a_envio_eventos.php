<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda quién hizo cada cambio de estado. Es nullable porque los eventos
     * generados desde tinker o un seeder no tienen usuario autenticado.
     */
    public function up(): void
    {
        Schema::table('envios.envio_eventos', function (Blueprint $table) {
            $table->unsignedBigInteger('usuario_id')->nullable();

            $table->foreign('usuario_id', 'envio_eventos_usuario_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('usuario_id', 'idx_envio_eventos_usuario');
        });
    }

    public function down(): void
    {
        // PostgreSQL elimina junto con la columna su índice y su llave foránea.
        // (dropIndex sin esquema falla en PostgreSQL con tablas fuera de "public".)
        Schema::table('envios.envio_eventos', function (Blueprint $table) {
            $table->dropColumn('usuario_id');
        });
    }
};