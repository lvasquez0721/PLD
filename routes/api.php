<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientesControllerApi;
use Illuminate\Support\Facades\Auth;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', function (Request $request) {
    $credentials = $request->only('email', 'password');

    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Credenciales inválidas'
        ], 401);
    }

    $user = Auth::user();
    // Si usas Sanctum
    $token = $user->createToken('api_token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token,
    ]);
});


// Ruta para inserción masiva de clientes
Route::post('/clientes/masivo', [ClientesControllerApi::class, 'storeMasivo']);

// Ruta para obtener el listado de todos los clientes (GET /clientes)
// Route::get('/clientes', [ClientesControllerApi::class, 'index'])->middleware('auth:sanctum');
