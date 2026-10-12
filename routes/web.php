<?php

use App\Http\Controllers\Envios\EnvioController;
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

    // Vistas del parque de vehículos; los endpoints están en routes/api.php
    Route::inertia('vehiculos', 'flota/VehiculosIndex')->name('vehiculos.index');
    Route::inertia('vehiculos/crear', 'flota/VehiculosCreate')->name('vehiculos.create');
    Route::inertia('vehiculos/{vehiculo}/editar', 'flota/VehiculosEdit')->name('vehiculos.edit');
});

require __DIR__.'/settings.php';
