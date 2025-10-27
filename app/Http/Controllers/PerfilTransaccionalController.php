<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PerfilTransaccionalController extends Controller
{
    //
    /**
     * Inserta de forma masiva uno o más Perfiles Transaccionales.
     * POST /api/perfiltransaccional/masivo
     */
    public function storeMasivo(Request $request)
    {
        // Validar que se proporciona el arreglo de registros
        $datos = $request->input('registros');

        if (!is_array($datos) || empty($datos)) {
            return response()->json([
                'message' => 'El parámetro "registros" debe ser un arreglo de objetos.'
            ], 400);
        }

        // Definir las reglas básicas de validación para cada perfil
        $reglas = [
            'IDPerfil' => 'required|integer',
            'NCliente' => 'required',
            'Nombre' => 'required',
            'EdoNacimiento' => 'nullable',
            'NivelRiesgoNac' => 'nullable',
            'CalculoNacimiento' => 'nullable',
            'EdoDomicilio' => 'nullable',
            'NivelRiesgoDoc' => 'nullable',
            'CalculoResidencia' => 'nullable',
            'EdoLabora' => 'nullable',
            'NivelRiesgoResidencia' => 'nullable',
            'CalculoLaboral' => 'nullable',
            'TotalUbicacion' => 'nullable|numeric',
            'Origen' => 'nullable',
            'ORecursos' => 'nullable',
            'Ingresos' => 'nullable',
            'PromedioHA' => 'nullable|numeric',
            'TotalEconomico' => 'nullable|numeric',
            'OcupGiro' => 'nullable',
            'NivelRiesgo' => 'nullable',
            'CalculoOcupacion' => 'nullable',
            'Perfil' => 'nullable',
            'Periodo' => 'nullable',
            'IDTipoEjecuccion' => 'nullable|integer',
            'AVGPrimaTotal' => 'nullable|numeric',
            'AVGHaTotal' => 'nullable|numeric',
            'STDEVPrimaTotal' => 'nullable|numeric',
            'STDEVHaTotal' => 'nullable|numeric',
            'origenRecursos' => 'nullable',
            'ValorIngresoEstimado' => 'nullable|numeric',
            'ValorHaEstimado' => 'nullable|numeric',
        ];

        $errores = [];
        $registrosAInsertar = [];

        foreach ($datos as $i => $item) {
            $localErrors = [];
            foreach ($reglas as $campo => $regla) {
                $requisitos = explode('|', $regla);

                foreach ($requisitos as $req) {
                    if ($req === 'required' && (!isset($item[$campo]) || $item[$campo] === null || $item[$campo] === '')) {
                        $localErrors[] = "El campo $campo es obligatorio.";
                        continue;
                    }
                    if ($req === 'integer' && isset($item[$campo]) && $item[$campo] !== '' && !is_numeric($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser entero.";
                    }
                    if ($req === 'numeric' && isset($item[$campo]) && $item[$campo] !== '' && !is_numeric($item[$campo])) {
                        $localErrors[] = "El campo $campo debe ser numérico.";
                    }
                }
            }
            if (!empty($localErrors)) {
                $errores[$i] = $localErrors;
                continue;
            }
            $registrosAInsertar[] = $item;
        }

        if (!empty($errores)) {
            return response()->json([
                'message' => 'Algunos objetos no cumplen los criterios de validación.',
                'errores' => $errores,
            ], 422);
        }

        try {
            // Utilizar el modelo para insertar múltiples registros
            \App\Models\PerfilTransaccional::insert($registrosAInsertar);

            return response()->json([
                'message' => 'Registros insertados correctamente.',
                'insertados' => $registrosAInsertar
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar los registros: ' . $e->getMessage(),
            ], 500);
        }
    }
}
