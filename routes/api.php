<?php

use App\Http\Controllers\AlertasController;
use App\Http\Controllers\CalculoInusualidadPrimaEmitidaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientesControllerApi;
use App\Http\Controllers\EstadosController;
use App\Http\Controllers\IDRRPLDController;
use App\Http\Controllers\IncisosController;
use App\Http\Controllers\ListaNegraCNSFController;
use App\Http\Controllers\ListasNegrasUIFController;
use App\Http\Controllers\OperacionesController;
use App\Http\Controllers\PagosPorCompensacionController;
use App\Http\Controllers\ParametriaController;
use App\Http\Controllers\ParametrosController;
use App\Http\Controllers\PerfilTransaccionalController;
use App\Http\Controllers\PolizasController;
use App\Http\Controllers\ReportesOpController;
use App\Http\Controllers\SolicitudesController;
use App\Http\Controllers\TipoCambioController;
use App\Http\Controllers\TotalPagosController;
use Illuminate\Support\Facades\Auth;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', function (Request $request) {
    $credentials = $request->only('usuario', 'password');

    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Credenciales inválidas'
        ], 401);
    }

    $user = Auth::user();
    // Si usas Sanctum
    $token = $user->createToken('api_token')->plainTextToken;

    return response()->json([
        'token' => $token,
    ]);
});

Route::post('/insertar/opereacion', [OperacionesController::class, 'insertarOperacion'])->middleware('auth:sanctum');


// Ruta para inserción masiva de clientes
Route::post('/clientes/masivo', [ClientesControllerApi::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/solicitudes/masivo', [SolicitudesController::class, 'storeMassive'])->middleware('auth:sanctum');


Route::post('/polizas/masivo', [PolizasController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/incisos/masivo', [IncisosController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/totalpagos/masivo', [TotalPagosController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/pagosporcompensacion/masivo', [PagosPorCompensacionController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/parametriapld/masivo', [ParametriaController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/calculoInusualidadPrimaEmitida/masivo', [CalculoInusualidadPrimaEmitidaController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/perfiltransaccional/masivo', [PerfilTransaccionalController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/parametros/masivo', [ParametrosController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/estados/masivo', [EstadosController::class, 'bulkInsert'])->middleware('auth:sanctum');

Route::post('/tipocambio/masivo', [TipoCambioController::class, 'storeMasivo'])->middleware('auth:sanctum');

Route::post('/idrrpld/masivo', [IDRRPLDController::class, 'bulkInsert'])->middleware('auth:sanctum');

Route::post('/alertas/masivo', [AlertasController::class, 'bulkInsert'])->middleware('auth:sanctum');

Route::post('/reportesop/masivo', [ReportesOpController::class, 'bulkInsert'])->middleware('auth:sanctum');

Route::post('/listanegracnsf/masivo', [ListaNegraCNSFController::class, 'bulkInsert'])->middleware('auth:sanctum');

Route::post('/listas-negras-uif/masivo', [ListasNegrasUIFController::class, 'bulkInsert'])->middleware('auth:sanctum');


//Ruta para guardar clientes generados desde el SIT
Route::post('/clientes/guardarCliente', [ClientesControllerApi::class, 'guardarCliente'])->middleware(['auth:sanctum']);



// Ruta para obtener el listado de todos los clientes (GET /clientes)
// Route::get('/clientes', [ClientesControllerApi::class, 'index'])->middleware('auth:sanctum');


//Perfil Transaccional BICV-----------------------------------------------------------------------------------------------------------------------
//use App\Http\Controllers\PerfilTransaccionalController;
Route::post('/perfil-transaccional/buscar', [PerfilTransaccionalController::class, 'buscar']); // Buscar registros (por fecha, nombre, etc.)

