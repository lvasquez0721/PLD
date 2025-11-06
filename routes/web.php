<?php

use App\Http\Controllers\UsuariosController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () {
//     return Inertia::render('Welcome');
// })->name('home');

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified'])->name('home');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/usuarios', [UsuariosController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('usuarios.index');

Route::post('/usuarios', [UsuariosController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('usuarios.store');

Route::put('/usuarios/{id}', [UsuariosController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('usuarios.update');

Route::delete('/usuarios/{id}', [UsuariosController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('usuarios.destroy');

// nuevas rutas
Route::get('/perfil-transaccional', [App\Http\Controllers\PerfilTransaccionalController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('perfil-transaccional.index');


// ALERTAS
Route::get('/alertas', [App\Http\Controllers\AlertasController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('alertas.index');

// Ruta para obtener alertas por rango de fechas
Route::get('/alertas/date-range', [App\Http\Controllers\AlertasController::class, 'getAlertasByDateRange'])
    ->name('alertas.date-range');

Route::get('/buzon-preocupantes', [App\Http\Controllers\BuzonPreocupantesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('buzon-preocupantes.index');

Route::get('/lista-negra', [App\Http\Controllers\ListaNegraController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('lista-negra.index');

Route::get('/reporte-operaciones', [App\Http\Controllers\ReporteOperacionesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('reporte-operaciones.index');

Route::get('/parametria-pld', [App\Http\Controllers\ParametriaPLDController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('parametria-pld.index');

Route::get('/listas-uif', [App\Http\Controllers\ListasUIFController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('listas-uif.index');

Route::get('/consulta-inusualidad', [App\Http\Controllers\ConsultaInusualidadController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('consulta-inusualidad.index');

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
