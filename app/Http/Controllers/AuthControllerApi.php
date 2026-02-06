<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthControllerApi extends Controller
{
    //

    /**
     * Handle an authentication request using 'usuario' and 'password'.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Validación de datos
            $validated = $request->validate([
                'usuario' => 'required|string',
                'password' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $ve->errors(),
                'exception' => $ve->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado en la validación',
                'exception' => $e->getMessage(),
            ], 500);
        }

        $credentials = [
            'usuario' => $request->input('usuario'),
            'password' => $request->input('password'),
        ];

        try {
            if (! \Auth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales inválidas',
                    'details' => 'No coincide el usuario y/o la contraseña.',
                ], 401);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al intentar autenticar.',
                'exception' => $e->getMessage(),
            ], 500);
        }

        try {
            $user = \Auth::user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el usuario autenticado.',
                ], 500);
            }

            try {
                $token = $user->createToken('api_token')->plainTextToken;
            } catch (\Throwable $te) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar el token.',
                    'exception' => $te->getMessage(),
                ], 500);
            }

            return response()->json([
                'success' => true,
                'token' => $token,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado durante el proceso de login.',
                'exception' => $e->getMessage(),
            ], 500);
        }
    }
}
