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

//Recuperar contraseña
Route::post('/recuperar-password', [AutenticacionController::class, 'recuperarPassword']);
/*
|--------------------------------------------------------------------------
| API Protected Routes
|--------------------------------------------------------------------------
*/
Route::middleware(TokenMongodbMiddleware::class)->group(function () {

    Route::post('/logout', [AutenticacionController::class, 'logout']);

    /**
     * CATÁLOGO DE PRODUCTOS (CRUD)
     */
    Route::get('/productos', [ProductController::class, 'listar']);
    Route::post('/productos', [ProductController::class, 'guardar']);
    Route::get('/productos/{id}', [ProductController::class, 'mostrar']);
    Route::put('/productos/{id}', [ProductController::class, 'actualizar']);
    Route::delete('/productos/{id}', [ProductController::class, 'eliminar']);

    /**
     * CATÁLOGO DE PERFILES
     */
    Route::get('/perfiles', [ProfileController::class, 'listar']);
    Route::post('/perfiles', [ProfileController::class, 'guardar']);
    Route::get('/perfiles/{id}', [ProfileController::class, 'mostrar']);
    Route::put('/perfiles/{id}', [ProfileController::class, 'actualizar']);
    Route::delete('/perfiles/{id}', [ProfileController::class, 'eliminar']);

    /**
     * CATÁLOGO DE USUARIOS
     */
    Route::get('/usuarios', [UserController::class, 'listar']);
    Route::post('/usuarios', [UserController::class, 'guardar']);
    Route::get('/usuarios/{id}', [UserController::class, 'mostrar']);
    Route::put('/usuarios/{id}', [UserController::class, 'actualizar']);
    Route::delete('/usuarios/{id}', [UserController::class, 'eliminar']);

    // Modulo de Auditoria (Solo paa revisar los movimientos) - PRODUCTOS
    Route::get('/auditoria/productos', [ProductController::class, 'listarAuditoriaProducto']);
    // Modulo de Auditoria (Solo paa revisar los movimientos) - PERFILES
    Route::get('/auditoria/perfiles', [ProfileController::class, 'listarAuditoriaPerfil']);
    // Modulo de Auditoria (Solo paa revisar los movimientos) - USUARIOS
    Route::get('/auditoria/usuarios', [UserController::class, 'listarAuditoriaUsuario']);
});
