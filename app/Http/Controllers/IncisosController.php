<?php

namespace App\Http\Controllers;

use App\Models\Inciso;
use Illuminate\Http\Request;

class IncisosController extends Controller
{
    /**
     * Inserción masiva de incisos
     * POST /api/incisos/masivo
     */
    public function storeMasivo(Request $request)
    {
        $datos = $request->input('incisos');

        if (! is_array($datos) || empty($datos)) {
            return response()->json([
                'message' => 'El parámetro "incisos" debe ser un arreglo de objetos inciso.',
            ], 400);
        }

        // Validar campos requeridos por cada inciso
        $reglas = [
            // Puedes ajustar los requeridos según tu lógica / modelo
            'IDPoliza' => 'required|integer',
            'NoPoliza' => 'required|string',
            'FEmisionPoliza' => 'required|date',
            'StatusPoliza' => 'nullable|string',
            'SuperAsegurada' => 'nullable|boolean',
            'SumaAsegurada' => 'nullable|numeric',
            'SumaASeguradaTotal' => 'nullable|numeric',
            'PrimaTotal' => 'nullable|numeric',
            'FIV' => 'nullable|date',
            'FFV' => 'nullable|date',
            // ...agrega otras reglas según sea necesario...
        ];

        $errores = [];
        $incisosAInsertar = [];

        foreach ($datos as $i => $inciso) {
            // Validación sencilla, puedes usar Validator si prefieres
            foreach ($reglas as $campo => $regla) {
                if (strpos($regla, 'required') !== false && (! isset($inciso[$campo]) || $inciso[$campo] === null || $inciso[$campo] === '')) {
                    $errores[$i][] = "El campo $campo es obligatorio.";
                }
            }
            if (isset($errores[$i])) {
                continue;
            }
            $incisosAInsertar[] = $inciso;
        }

        if (! empty($errores)) {
            return response()->json([
                'message' => 'Algunos incisos no cumplen los criterios de validación.',
                'errores' => $errores,
            ], 422);
        }

        try {
            Inciso::insert($incisosAInsertar);

            return response()->json([
                'message' => 'Incisos insertados correctamente.',
                'insertados' => $incisosAInsertar,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar los incisos: '.$e->getMessage(),
            ], 500);
        }
    }
}
