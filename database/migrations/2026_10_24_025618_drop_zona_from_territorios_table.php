<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS territorio.idx_territorios_zona');
        DB::statement('ALTER TABLE territorio.territorios DROP COLUMN IF EXISTS zona');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE territorio.territorios
            ADD COLUMN IF NOT EXISTS zona geography(Polygon, 4326)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_territorios_zona
            ON territorio.territorios USING GIST (zona)');
    }
};
