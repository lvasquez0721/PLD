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



require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
