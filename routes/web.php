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

Route::get('/alertas', [App\Http\Controllers\AlertasController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('alertas.index');

Route::get('/buzon-preocupantes', [App\Http\Controllers\BuzonPreocupantesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('buzon-preocupantes.index');

Route::get('/lista-negra', [App\Http\Controllers\ListaNegraController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('lista-negra.index');

Route::get('/reporte-operaciones', [App\Http\Controllers\ReporteOperacionesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('reporte-operaciones.index');

Route::get('/reporte-operaciones/obtener', [App\Http\Controllers\ReporteOperacionesController::class, 'obtenerReporte']);

// Rutas para Parametria PLD
Route::get('/parametria-pld', [App\Http\Controllers\ParametriaPLDController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('parametria-pld.index');

Route::post('/parametria-pld/actualizar', [App\Http\Controllers\ParametriaPLDController::class, 'actualizar'])
    ->middleware(['auth', 'verified'])
    ->name('parametria-pld.actualizar');

Route::get('/listas-uif', [App\Http\Controllers\ListasUIFController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('listas-uif.index');

Route::post('/listas-uif/altaListas', [App\Http\Controllers\ListasUIFController::class, 'altaListas'])
    ->middleware(['auth', 'verified'])
    ->name('listas-uif.altaListas');

Route::post('/listas-uif/bajaListas', [App\Http\Controllers\ListasUIFController::class, 'bajaListas'])
    ->middleware(['auth', 'verified'])
    ->name('listas-uif.bajaListas');

Route::post('/listas-uif/actualizaListas', [App\Http\Controllers\ListasUIFController::class, 'actualizaListas'])
    ->middleware(['auth', 'verified'])
    ->name('listas-uif.actualizaListas');

Route::get('/listas-uif/consultaListas', [App\Http\Controllers\ListasUIFController::class, 'getConsultaListas'])
    ->middleware(['auth', 'verified'])
    ->name('listas-uif.consultaListas');

Route::get('/consulta-inusualidad', [App\Http\Controllers\ConsultaInusualidadController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('consulta-inusualidad.index');


//Rutas de Listas Negra CNSF---------------------------------------------------------------------------------------------------------
use App\Http\Controllers\ListaNegraController;

Route::get('/lista-negra', [ListaNegraController::class, 'index'])->name('lista-negra.index');
Route::post('/lista-negra/insert', [ListaNegraController::class, 'insert'])->name('lista-negra.insert');
Route::post('/lista-negra/update/{id}', [ListaNegraController::class, 'update'])->name('lista-negra.update');
Route::post('/lista-negra/delete/{id}', [ListaNegraController::class, 'delete'])->name('lista-negra.delete');



require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
