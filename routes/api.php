<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AutenticacionController;
use App\Http\Middleware\TokenMongodbMiddleware;

// Endpoint de salud del sistema / Monitoreo
Route::get('/estatus', function () {
    return response()->json([
        'success' => true,
        'mensaje' => 'La API de la Prueba Tecnica, corriendo correctamente.',
        'entorno' => config('app.env'),
        'timestamp' => now()->toIso8601String()
    ], 200);
});

// Autenticación pública para obtención de Token
Route::post('/login', [AutenticacionController::class, 'login'])->name('login');

// Recuperar contraseña
Route::post('/recuperar-password', [AutenticacionController::class, 'recuperarPassword']);

/*
|--------------------------------------------------------------------------
| API Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware([TokenMongodbMiddleware::class])->group(function () {

    // CONTROL DE PRODUCTOS
    Route::middleware('validar.seccion:productos')->group(function () {
        Route::apiResource('productos', ProductController::class);
    });

    Route::get('usuarios/{usuario}', [UserController::class, 'show']);
    Route::put('usuarios/{usuario}', [UserController::class, 'update']);

    // CONTROL GENERAL DE USUARIOS
    Route::middleware('validar.seccion:usuarios')->group(function () {
        Route::get('usuarios', [UserController::class, 'index']);
        Route::post('usuarios', [UserController::class, 'store']);
        Route::delete('usuarios/{usuario}', [UserController::class, 'destroy']);
    });

    // CONTROL DE PERFILES / ROLES
    Route::middleware('validar.seccion:perfiles')->group(function () {
        Route::apiResource('perfiles', ProfileController::class);
    });

    // BITÁCORA DE AUDITORÍA
    Route::middleware('validar.seccion:auditoria')->group(function () {
        Route::get('/auditoria/productos', [ProductController::class, 'listarAuditoriaProducto']);
        Route::get('/auditoria/perfiles', [ProfileController::class, 'listarAuditoriaPerfil']);
        Route::get('/auditoria/usuarios', [UserController::class, 'listarAuditoriaUsuario']);
    });

});
