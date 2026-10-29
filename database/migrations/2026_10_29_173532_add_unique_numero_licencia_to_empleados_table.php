<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La licencia identifica al conductor (Figura del escenario de la tesis),
     * así que no puede repetirse. En Postgres el índice unique sobre una
     * columna nullable admite múltiples NULL, por lo que los empleados sin
     * licencia no se ven afectados.
     */
    public function up(): void
    {
        Schema::table('personal.empleados', function (Blueprint $table) {
            $table->unique('numero_licencia', 'uk_licencia_empleados');
        });
    }

    public function down(): void
    {
        Schema::table('personal.empleados', function (Blueprint $table) {
            $table->dropUnique('uk_licencia_empleados');
        });
    }
};
