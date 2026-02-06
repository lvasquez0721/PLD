<?php

namespace App\Http\Controllers;

use App\Models\TotalPago;
use Illuminate\Http\Request;

class TotalPagosController extends Controller
{
    /**
     * Inserción masiva de totalpagos
     * POST /api/totalpagos/masivo
     */
    public function storeMasivo(Request $request)
    {
        $datos = $request->input('totalpagos');

        if (! is_array($datos) || empty($datos)) {
            return response()->json([
                'message' => 'El parámetro "totalpagos" debe ser un arreglo de objetos totalpago.',
            ], 400);
        }

        // Define reglas mínimas de validación para los campos requeridos
        $reglas = [
            'IDPoliza' => 'required|integer',
            // Agrega más validaciones/obligatorios según tu modelo si lo necesitas
        ];

        $errores = [];
        $registrosAInsertar = [];

        // Validación sencilla para cada objeto
        foreach ($datos as $i => $item) {
            foreach ($reglas as $campo => $regla) {
                if (strpos($regla, 'required') !== false && (! isset($item[$campo]) || $item[$campo] === null || $item[$campo] === '')) {
                    $errores[$i][] = "El campo $campo es obligatorio.";
                }
            }
            if (isset($errores[$i])) {
                continue;
            }
            $registrosAInsertar[] = $item;
        }

        if (! empty($errores)) {
            return response()->json([
                'message' => 'Algunos objetos no cumplen los criterios de validación.',
                'errores' => $errores,
            ], 422);
        }

        try {
            TotalPago::insert($registrosAInsertar);

            return response()->json([
                'message' => 'Totalpagos insertados correctamente.',
                'insertados' => $registrosAInsertar,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar los totalpagos: '.$e->getMessage(),
            ], 500);
        }
    }
}
