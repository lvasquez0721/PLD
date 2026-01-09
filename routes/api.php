<?php

use App\Http\Controllers\AlertasController;
use App\Http\Controllers\AuthControllerApi;
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


Route::post('/login', [AuthControllerApi::class, 'login']);


// Rutas de OperacionesController
Route::post('/insertar/operacion', [OperacionesController::class, 'insertarOperacion'])->middleware('auth:sanctum');
Route::post('/insertar/operacion-pago', [OperacionesController::class, 'insertarOperacionPago'])->middleware('auth:sanctum');

// Ruta para inserción masiva de clientes
// Route::post('/clientes/masivo', [ClientesControllerApi::class, 'storeMasivo'])->middleware('auth:sanctum');

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
Route::post('/clientes/guardarCliente', [ClientesControllerApi::class, 'darAltaCliente'])->middleware(['auth:sanctum']);
// Ruta para actualizar datos de cliente (PUT /clientes/{id})
Route::put('/clientes/{id}', [ClientesControllerApi::class, 'actualizarCliente'])->middleware(['auth:sanctum']);

// Ruta para obtener el listado de todos los clientes (GET /clientes)
// Route::get('/clientes', [ClientesControllerApi::class, 'index'])->middleware('auth:sanctum');

//Perfil Transaccional BICV-----------------------------------------------------------------------------------------------------------------------
//use App\Http\Controllers\PerfilTransaccionalController;
// Route::post('/perfil-transaccional/buscar', [PerfilTransaccionalController::class, 'buscar']); // Buscar registros (por fecha, nombre, etc.)
Route::post('/perfil-transaccional/buscar', [PerfilTransaccionalController::class, 'buscar']);

Route::post('/api-login', function(Request $request) {
    $request->validate([
        'usuario' => 'required|string',
        'contraseña' => 'required|string',
    ]);

    $credentials = [
        'usuario' => $request->usuario,
        'password' => $request->contraseña,
    ];

    if (Auth::attempt($credentials)) {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Credenciales incorrectas.',
    ], 401);
});


use App\Http\Controllers\ListasNegrasControllerApi;

// Ruta para consultar listas negras por IDCliente (Consulta cruzada UIF y CNSF)
Route::post('/listas-negras/consultar-por-cliente', [ListasNegrasControllerApi::class, 'getConsultaListasByIDCliente'])->middleware(['auth:sanctum']);


//------------------------------------------------------------------------------------------------------------------------------------------------
