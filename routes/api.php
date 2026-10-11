<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Flota\VehiculoController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware('auth:api')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Gestión de la flota de vehículos (autenticación JWT)
    Route::get('/vehiculos', [VehiculoController::class, 'index'])->name('api.vehiculos.index');
    Route::get('/vehiculos/opciones', [VehiculoController::class, 'opciones'])->name('api.vehiculos.opciones');
    Route::post('/vehiculos', [VehiculoController::class, 'store'])->name('api.vehiculos.store');

    Route::get('/admin/only', fn (): JsonResponse => response()->json(['message' => 'admin area']))
        ->middleware('role:admin');

    Route::get('/analista/only', fn (): JsonResponse => response()->json(['message' => 'analista area']))
        ->middleware('role:analista');
});
