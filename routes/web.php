<?php

use App\Http\Controllers\Clientes\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Envios\EnvioController;
use App\Http\Controllers\Flota\VehiculoController;
use App\Http\Controllers\Personal\EmpleadoController;
use App\Http\Controllers\Rutas\DespachoController;
use App\Http\Controllers\Rutas\RutaMapaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Verificación de email deshabilitada: cualquier visita a estas URLs va al dashboard
Route::redirect('email/verify/{any?}', '/dashboard')->where('any', '.*');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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

    /*  Rutas de despachos: listado y programación con el controlador real
        --------------------------------------------------------------------------
        La red logística (conexiones) la alimenta RedLogisticaSeeder.
    */
    Route::prefix('rutas')->name('rutas.')->group(function () {
        Route::get('/', [DespachoController::class, 'index'])->name('index');
        Route::get('mapa', [RutaMapaController::class, 'index'])->name('mapa');
        Route::get('mapa/datos', [RutaMapaController::class, 'datos'])->name('mapa.datos');
        Route::post('mapa/zonas/{territorio}', [RutaMapaController::class, 'guardarZona'])
            ->whereNumber('territorio')
            ->middleware('can:rutas.zonas')
            ->name('mapa.zonas.guardar');

        Route::middleware('can:despachos.gestionar')->group(function () {
            Route::get('crear', [DespachoController::class, 'create'])->name('create');
            Route::post('/', [DespachoController::class, 'store'])->name('store');
        });
    });

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

    /*  Clientes
        --------------------------------------------------------------------------
        Cualquier usuario autenticado consulta. Registrar, editar y eliminar dependen
        de los gates 'clientes.registrar', 'clientes.editar' y 'clientes.eliminar'.
    */
    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('index');
        Route::get('exportar', [ClienteController::class, 'exportar'])->name('exportar');

        Route::middleware('can:clientes.registrar')->group(function () {
            Route::get('crear', [ClienteController::class, 'create'])->name('create');
            Route::post('/', [ClienteController::class, 'store'])->name('store');
        });

        Route::middleware('can:clientes.editar')->group(function () {
            Route::get('{cliente}/editar', [ClienteController::class, 'edit'])->whereNumber('cliente')->name('edit');
            Route::put('{cliente}', [ClienteController::class, 'update'])->whereNumber('cliente')->name('update');
        });

        Route::delete('{cliente}', [ClienteController::class, 'destroy'])
            ->whereNumber('cliente')
            ->middleware('can:clientes.eliminar')
            ->name('destroy');
    });
});

require __DIR__.'/settings.php';
