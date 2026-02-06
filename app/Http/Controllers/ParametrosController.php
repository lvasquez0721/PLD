<?php

namespace App\Http\Controllers;

use App\Models\Parametros;
use Illuminate\Http\Request;

class ParametrosController extends Controller
{
    /**
     * Inserta masivamente uno o más parámetros.
     * POST /api/parametros/masivo
     */
    public function storeMasivo(Request $request)
    {
        // Validar que se proporcione el arreglo de registros
        $datos = $request->input('registros');

        if (! is_array($datos) || empty($datos)) {
            return response()->json([
                'message' => 'El parámetro "registros" debe ser un arreglo de objetos.',
            ], 400);
        }

        // Definir las reglas básicas de validación para cada parámetro
        $reglas = [
            'IDParametro' => 'required|integer',
            'PorNacimiento' => 'nullable|numeric',
            'PorResidencia' => 'nullable|numeric',
            'PorPredio' => 'nullable|numeric',
            'PorNacionalidad' => 'nullable|numeric',
            'PorAmbitoLaboral' => 'nullable|numeric',
            'PorUbicacion' => 'nullable|numeric',
            'PorOrigenRecursos' => 'nullable|numeric',
            'PorIngresosEstimados' => 'nullable|numeric',
            'PorPromedioUR' => 'nullable|numeric',
            'PorDatosEconomicos' => 'nullable|numeric',
            'PorDatosLaborales' => 'nullable|numeric',
            'FechaActualizacion' => 'nullable|date',
        ];

        $errores = [];
        $registrosAInsertar = [];

        foreach ($datos as $i => $item) {
            $localErrors = [];

            foreach ($reglas as $campo => $regla) {
                $requisitos = explode('|', $regla);

                foreach ($requisitos as $req) {
                    if ($req === 'required' && (! isset($item[$campo]) || $item[$campo] === null || $item[$campo] === '')) {
                        $localErrors[] = "El campo $campo es obligatorio.";

                        continue;
                    }
                    if ($req === 'integer' && isset($item[$campo]) && $item[$campo] !== '' && ! is_numeric($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser entero.";
                    }
                    if ($req === 'numeric' && isset($item[$campo]) && $item[$campo] !== '' && ! is_numeric($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser numérico.";
                    }
                    if ($req === 'date' && isset($item[$campo]) && $item[$campo] !== '' && ! strtotime($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser una fecha válida.";
                    }
                }
            }

            if (! empty($localErrors)) {
                $errores[$i] = $localErrors;

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
            Parametros::insert($registrosAInsertar);

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
