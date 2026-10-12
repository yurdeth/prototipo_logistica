<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware('auth:api')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/admin/only', fn (): JsonResponse => response()->json(['message' => 'admin area']))
        ->middleware('role:admin');

    Route::get('/analista/only', fn (): JsonResponse => response()->json(['message' => 'analista area']))
        ->middleware('role:analista');
});
