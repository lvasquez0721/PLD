<?php

namespace App\Http\Controllers;

use App\Models\CalculoInusualidadPrimaEmitida;
use Illuminate\Http\Request;

class CalculoInusualidadPrimaEmitidaController extends Controller
{
    /**
     * Inserta de forma masiva uno o más registros en CalculoInusualidadPrimaEmitida.
     * POST /api/calculoInusualidadPrimaEmitida/masivo
     */
    public function storeMasivo(Request $request)
    {
        $datos = $request->input('registros');

        if (! is_array($datos) || empty($datos)) {
            return response()->json([
                'message' => 'El parámetro "registros" debe ser un arreglo de objetos.',
            ], 400);
        }

        // Validación básica por campos requeridos
        $reglas = [
            'PolizaHistorico' => 'required',
            'EndosoHistorico' => 'required',
            'FechaEmision' => 'required|date',
            'PrimaTotalHistorica' => 'required|numeric',
            'NCliente' => 'required',
            'Cliente' => 'required',
            'FechaInicioMuestra' => 'required|date',
            'FechaFinMuestra' => 'required|date',
            'PolizaEmitida' => 'required',
            'EndosoEmitido' => 'required',
            'PrimaEmitida' => 'required|numeric',
            'Detectado' => 'required|boolean',
            'AniosAnterioresConsiderados' => 'required|integer',
            'Promedio' => 'required|numeric',
            'DesviacionEstandar' => 'required|numeric',
            'FactorDesviacionEstandar' => 'required|numeric',
            'LimiteInferior' => 'required|numeric',
            'LimiteSuperior' => 'required|numeric',
            'TimeStamp' => 'required|date',
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
                    if ($req === 'date' && isset($item[$campo]) && ! strtotime($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser una fecha válida.";
                    }
                    if ($req === 'numeric' && isset($item[$campo]) && ! is_numeric($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser numérico.";
                    }
                    if ($req === 'integer' && isset($item[$campo]) && ! is_numeric($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser entero.";
                    }
                    if ($req === 'boolean' && isset($item[$campo]) && ! in_array($item[$campo], [true, false, 0, 1, '0', '1'], true)) {
                        $localErrors[] = "El campo $campo debe ser booleano.";
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
            CalculoInusualidadPrimaEmitida::insert($registrosAInsertar);

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
