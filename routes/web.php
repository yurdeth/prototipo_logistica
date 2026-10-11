<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Verificación de email deshabilitada: cualquier visita a estas URLs va al dashboard
Route::redirect('email/verify/{any?}', '/dashboard')->where('any', '.*');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('envios', 'shipments/EnviosIndex')->name('envios');
    Route::inertia('envios/crear', 'shipments/EnviosCreate')->name('envios.create');

});

require __DIR__.'/settings.php';
