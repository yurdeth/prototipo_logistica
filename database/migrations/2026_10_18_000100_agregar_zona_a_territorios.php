<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Zona geográfica (polígono) de cada territorio. Los envíos solo guardan su territorio de
     * destino, así que el mapa de rutas ubica cada parada en el centro de esa zona.
     * Es opcional: un territorio sin zona simplemente no aparece en el mapa.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE territorio.territorios
            ADD COLUMN IF NOT EXISTS zona geography(Polygon, 4326)');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_territorios_zona
            ON territorio.territorios USING GIST (zona)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS territorio.idx_territorios_zona');
        DB::statement('ALTER TABLE territorio.territorios DROP COLUMN IF EXISTS zona');
    }
};
