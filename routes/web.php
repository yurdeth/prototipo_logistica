<?php

use App\Http\Controllers\Envios\EnvioController;
use App\Http\Controllers\Flota\VehiculoController;
use App\Http\Controllers\Personal\EmpleadoController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Verificación de email deshabilitada: cualquier visita a estas URLs va al dashboard
Route::redirect('email/verify/{any?}', '/dashboard')->where('any', '.*');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::prefix('envios')->name('envios.')->group(function () {
        Route::get('/', [EnvioController::class, 'index'])->name('index');
        Route::get('exportar', [EnvioController::class, 'exportar'])->name('exportar');

        Route::middleware('can:envios.registrar')->group(function () {
            Route::get('crear', [EnvioController::class, 'create'])->name('create');
            Route::post('/', [EnvioController::class, 'store'])->name('store');
        });

        Route::middleware('can:envios.editar')->group(function () {
            Route::get('{envio}/editar', [EnvioController::class, 'edit'])->whereNumber('envio')->name('edit');
            Route::put('{envio}', [EnvioController::class, 'update'])->whereNumber('envio')->name('update');
            Route::patch('{envio}/estado', [EnvioController::class, 'cambiarEstado'])->whereNumber('envio')->name('estado');
        });

        Route::get('{envio}', [EnvioController::class, 'show'])->whereNumber('envio')->name('show');
    });

    /*  Rutas provisionales para diseño de interfaces
        --------------------------------------------------------------------------
        Estas rutas se definen temporalmente mediante Route::inertia()
        para visualizar y desarrollar las interfaces del sistema.
    */
    Route::inertia('rutas', 'rutas/RutasIndex')->name('rutas');
    Route::inertia('rutas/crear', 'rutas/RutasCreate')->name('rutas.create');

    // Flota: vistas y operaciones (sin API; todo por Inertia)
    Route::prefix('vehiculos')->name('vehiculos.')->group(function (): void {
        Route::get('/', [VehiculoController::class, 'index'])->name('index');
        Route::get('crear', [VehiculoController::class, 'create'])->name('create');
        Route::post('/', [VehiculoController::class, 'store'])->name('store');
        Route::get('{vehiculo}/editar', [VehiculoController::class, 'edit'])->whereNumber('vehiculo')->name('edit');
        Route::put('{vehiculo}', [VehiculoController::class, 'update'])->whereNumber('vehiculo')->name('update');
        Route::post('{vehiculo}/desactivar', [VehiculoController::class, 'desactivar'])->whereNumber('vehiculo')->name('desactivar');
        Route::post('{vehiculo}/reactivar', [VehiculoController::class, 'reactivar'])->whereNumber('vehiculo')->name('reactivar');
    });

    // Personal: vistas y operaciones de empleados (todo por Inertia)
    Route::prefix('empleados')->name('empleados.')->group(function (): void {
        Route::get('/', [EmpleadoController::class, 'index'])->name('index');
        Route::get('crear', [EmpleadoController::class, 'create'])->name('create');
        Route::post('/', [EmpleadoController::class, 'store'])->name('store');
        Route::get('{empleado}/editar', [EmpleadoController::class, 'edit'])->whereNumber('empleado')->name('edit');
        Route::put('{empleado}', [EmpleadoController::class, 'update'])->whereNumber('empleado')->name('update');
        Route::post('{empleado}/desactivar', [EmpleadoController::class, 'desactivar'])->whereNumber('empleado')->name('desactivar');
        Route::post('{empleado}/reactivar', [EmpleadoController::class, 'reactivar'])->whereNumber('empleado')->name('reactivar');
    });
});

require __DIR__.'/settings.php';
