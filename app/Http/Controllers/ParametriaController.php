<?php

namespace App\Http\Controllers;

use App\Models\ParametriaPLD;
use Illuminate\Http\Request;

class ParametriaController extends Controller
{
    /**
     * Inserta de forma masiva uno o más registros en catParametriaPLD.
     * POST /api/parametriapld/masivo
     */
    public function storeMasivo(Request $request)
    {
        $datos = $request->input('parametrias');

        if (! is_array($datos) || empty($datos)) {
            return response()->json([
                'message' => 'El parámetro "parametrias" debe ser un arreglo de objetos.',
            ], 400);
        }

        // Validación básica
        $reglas = [
            'Parametro' => 'required',
            'Valor' => 'required',
            'Tipo' => 'required',
            'Activo' => 'required',
        ];

        $errores = [];
        $registrosAInsertar = [];

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
            ParametriaPLD::insert($registrosAInsertar);

            return response()->json([
                'message' => 'Registros insertados correctamente.',
                'insertados' => $registrosAInsertar,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar los registros: '.$e->getMessage(),
            ], 500);
        }
    }
}
