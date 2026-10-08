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
        Schema::create('personal.departamentos', function (Blueprint $table) {
            $table->increments('id_departamento');
            $table->string('nombre_departamento', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique('nombre_departamento', 'uk_nombre_departamentos');
        });

        Schema::create('personal.puestos', function (Blueprint $table) {
            $table->increments('id_puesto');
            $table->integer('departamento_id');
            $table->string('nombre_puesto', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('departamento_id', 'puestos_departamento_fk')
                ->references('id_departamento')
                ->on('personal.departamentos');

            $table->unique(['departamento_id', 'nombre_puesto'], 'uk_nombre_puesto_departamento');
        });

        Schema::create('personal.empleados', function (Blueprint $table) {
            $table->increments('id_empleado');
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->integer('puesto_id');
            $table->integer('sucursal_id');
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('documento_identidad', 20);
            $table->string('telefono', 20)->nullable();
            $table->string('numero_licencia', 30)->nullable();
            $table->date('fecha_contratacion')->default(DB::raw('CURRENT_DATE'));
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('usuario_id', 'empleados_usuario_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('puesto_id', 'empleados_puesto_fk')
                ->references('id_puesto')
                ->on('personal.puestos');

            $table->foreign('sucursal_id', 'empleados_sucursal_fk')
                ->references('id_sucursal')
                ->on('territorio.sucursales');

            $table->unique('usuario_id', 'uk_empleados_usuario');
            $table->unique('documento_identidad', 'uk_documento_empleados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal.empleados');
        Schema::dropIfExists('personal.puestos');
        Schema::dropIfExists('personal.departamentos');
    }
};
