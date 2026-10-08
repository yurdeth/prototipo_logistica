<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['territorio', 'flota', 'envios', 'rutas', 'personal'] as $esquema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$esquema}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse(['territorio', 'flota', 'envios', 'rutas', 'personal']) as $esquema) {
            DB::statement("DROP SCHEMA IF EXISTS {$esquema} CASCADE");
        }
    }
};
