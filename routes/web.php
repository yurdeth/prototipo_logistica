<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Verificación de email deshabilitada: cualquier visita a estas URLs va al dashboard
Route::redirect('email/verify/{any?}', '/dashboard')->where('any', '.*');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    /*  Rutas provisionales para diseño de interfaces
        --------------------------------------------------------------------------
        Estas rutas se definen temporalmente mediante Route::inertia()
        para visualizar y desarrollar las interfaces del sistema.
        Por el momento, se utilizan únicamente con fines de diseño
    */
    Route::inertia('envios', 'shipments/EnviosIndex')->name('envios');
    Route::inertia('envios/crear', 'shipments/EnviosCreate')->name('envios.create');
    Route::inertia('rutas', 'rutas/RutasIndex')->name('rutas');
    Route::inertia('rutas/crear', 'rutas/RutasCreate')->name('rutas.create');

    // Vistas del parque de vehículos; los endpoints están en routes/api.php
    Route::inertia('vehiculos', 'flota/VehiculosIndex')->name('vehiculos.index');
    Route::inertia('vehiculos/crear', 'flota/VehiculosCreate')->name('vehiculos.create');
});

require __DIR__.'/settings.php';
