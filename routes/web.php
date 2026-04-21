<?php

use App\Http\Controllers\AlertasController;
use App\Http\Controllers\ExportarLayoutController;
use App\Http\Controllers\BuzonPreocupantesController;
use App\Http\Controllers\ClientesController;
use App\Http\Controllers\ConsultaInusualidadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ListaNegraController;
use App\Http\Controllers\ListasUIFController;
use App\Http\Controllers\ParametriaPLDController;
use App\Http\Controllers\PerfilTransaccionalController;
use App\Http\Controllers\ReporteOperacionesController;
use App\Http\Controllers\UsuariosController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Home & Dashboard
Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware(['auth', 'verified'])->name('home');

Route::get('/dashboard', [DashboardController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Usuarios
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/usuarios', [UsuariosController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuariosController::class, 'store'])->name('usuarios.store');
    Route::put('/usuarios/{id}', [UsuariosController::class, 'update'])->name('usuarios.update');
    Route::delete('/usuarios/{id}', [UsuariosController::class, 'destroy'])->name('usuarios.destroy');
});

// Perfil Transaccional
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/perfil-transaccional', [PerfilTransaccionalController::class, 'index'])->name('perfil-transaccional.index');
    Route::post('/perfil-transaccional/insert', [PerfilTransaccionalController::class, 'insert'])->name('perfil-transaccional.insert');
    Route::post('/perfil-transaccional/buscar', [PerfilTransaccionalController::class, 'buscar'])->name('perfil-transaccional.buscar');
    Route::post('/perfil-transaccional/ejecutar', [PerfilTransaccionalController::class, 'ejecutar'])->name('perfil-transaccional.ejecutar');
});

// Alertas
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/alertas', [AlertasController::class, 'index'])->name('alertas.index');
    Route::put('/alertas/actualizar', [AlertasController::class, 'actualizarAlerta'])->name('alertas.actualizar');
    Route::post('/alertas/editar-dos', [AlertasController::class, 'editarAlertaDos'])->name('alertas.editar-dos');
    Route::get('/alertas/get-alertas', [AlertasController::class, 'getAlertas'])->name('alertas.get-alertas');
    Route::get('/alertas/download-csv', [AlertasController::class, 'downloadAlertasCsvByDateRange'])->name('alertas.download-csv');
    Route::get('/clientes/{id}/polizas', [AlertasController::class, 'getPolizasPorCliente'])->name('clientes.polizas');
    Route::delete('/alertas/evidencias', [AlertasController::class, 'eliminarEvidenciaDeAlerta'])->name('alertas.evidencias.eliminar');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/alertas/{idAlerta}/detalles', [AlertasController::class, 'detalleAlerta'])->name('alertas.detalles');
});


// Buzón Preocupantes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/buzon-preocupantes', [BuzonPreocupantesController::class, 'index'])->name('buzon-preocupantes.index');
    Route::post('/buzon-preocupantes/pasar-alertas', [BuzonPreocupantesController::class, 'pasarAlertas'])->name('buzon.pasarAlertas');
    Route::post('/buzon-preocupantes/guardar', [BuzonPreocupantesController::class, 'store'])->name('buzon.store');
});

// Listas Negra CNSF
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/lista-negra', [ListaNegraController::class, 'index'])->name('lista-negra.index');
    Route::get('/lista-negra/exportar', [ListaNegraController::class, 'exportCsv'])->name('lista-negra.exportar');
    Route::post('/lista-negra/insert', [ListaNegraController::class, 'insert'])->name('lista-negra.insert');
    Route::post('/lista-negra/update/{id}', [ListaNegraController::class, 'update'])->name('lista-negra.update');
    Route::post('/lista-negra/delete/{id}', [ListaNegraController::class, 'delete'])->name('lista-negra.delete');
    Route::get('/lista-negra/oficios/{id}', [ListaNegraController::class, 'getOficios'])->name('lista-negra.oficios');
});

// Reporte de Operaciones
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reporte-operaciones', [ReporteOperacionesController::class, 'index'])->name('reporte-operaciones.index');
    Route::get('/reporte-operaciones/obtener', [ReporteOperacionesController::class, 'obtenerReporte'])->name('reporte-operaciones.obtener');
    Route::get('/reporte-operaciones/exportar', [ReporteOperacionesController::class, 'exportarCSV'])->name('reporte-operaciones.exportar');
});

// Parametria PLD
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/parametria-pld', [ParametriaPLDController::class, 'index'])->name('parametria-pld.index');
    Route::post('/parametria-pld/actualizar', [ParametriaPLDController::class, 'actualizar'])->name('parametria-pld.actualizar');
});

// Listas UIF
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/listas-uif', [ListasUIFController::class, 'index'])->name('listas-uif.index');
    Route::get('/listas-uif/exportar', [ListasUIFController::class, 'exportarCSV'])->name('listas-uif.exportar');
    Route::post('/listas-uif/altaListas', [ListasUIFController::class, 'altaListasUIF'])->name('listas-uif.altaListas');
    Route::post('/listas-uif/bajaListas', [ListasUIFController::class, 'bajaListas'])->name('listas-uif.bajaListas');
    Route::post('/listas-uif/actualizaListas', [ListasUIFController::class, 'actualizaListas'])->name('listas-uif.actualizaListas');
    Route::get('/listas-uif/consultaListas', [ListasUIFController::class, 'getConsultaListas'])->name('listas-uif.consultaListas');
});

// Consulta Inusualidad
Route::get('/consulta-inusualidad', [ConsultaInusualidadController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('consulta-inusualidad.index');

// Exportar Layout PLD
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/exportar-layout', [ExportarLayoutController::class, 'index'])->name('exportar-layout.index');
    Route::get('/exportar-layout/exportar', [ExportarLayoutController::class, 'exportar'])->name('exportar-layout.exportar');
});

// Clientes
Route::get('/clientes', [ClientesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('clientes.index');
Route::get('/clientes/exportar', [ClientesController::class, 'exportCsv'])
    ->middleware(['auth', 'verified'])
    ->name('clientes.exportar');
Route::get('/clientes/ver-detalles/{id_cliente}', [ClientesController::class, 'verDetallesCliente'])
    ->middleware(['auth', 'verified'])
    ->name('clientes.ver-detalles');
Route::post('/clientes/{id_cliente}/activar', [ClientesController::class, 'activarCliente'])
    ->middleware(['auth', 'verified'])
    ->name('clientes.activar');


// Utilidades del Sistema
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/migraciones/ejecutar', function () {
        if (!app()->environment('local')) abort(403, 'Acceso denegado');
        try {
            Artisan::call('migrate', ['--force' => true]);
            return response()->json(['success' => true, 'output' => Artisan::output()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    });

    Route::post('/storage-link', function () {
        if (!app()->environment('local')) abort(403, 'Acceso denegado');
        try {
            Artisan::call('storage:link');
            return response()->json(['success' => true, 'output' => Artisan::output()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    })->name('storage.link');

    Route::post('/limpiar-cache', function () {
        try {
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            return response()->json(['success' => true, 'output' => Artisan::output()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    })->name('limpiar.cache');

    Route::post('/key-generate', function () {
        if (!app()->environment('local')) abort(403, 'Acceso denegado');
        try {
            Artisan::call('key:generate');
            return response()->json(['success' => true, 'output' => Artisan::output()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    })->name('key.generate');
});

// Auth & Settings
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
