<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ListaNegraCNSF;
use Illuminate\Support\Facades\Validator;

class ListaNegraCNSFController extends Controller
{
    /**
     * Insertar registros de forma masiva en tbListaNegraCNSF
     *
     * Espera un array de objetos, cada uno con los campos requeridos por ListaNegraCNSF::$fillable
     *
     * Ejemplo de request payload:
     * [
     *   {
     *      "Nombres": "Juan Perez",
     *      "Direccion": "Av. Siempre Viva 123",
     *      ...
     *   },
     *   ...
     * ]
     */
    public function bulkInsert(Request $request)
    {
        $data = $request->all();

        if (!is_array($data)) {
            return response()->json([
                'success' => false,
                'message' => 'El payload debe ser un array de objetos.',
            ], 400);
        }

        $rules = [
            '*.Nombres' => 'required|string',
            '*.Direccion' => 'nullable|string',
            '*.Empresa' => 'nullable|string',
            '*.Cedula' => 'nullable|string',
            '*.Pasaporte' => 'nullable|string',
            '*.NIT' => 'nullable|string',
            '*.IFE' => 'nullable|string',
            '*.RFC' => 'nullable|string',
            '*.CURP' => 'nullable|string',
            '*.Pais' => 'nullable|string',
            '*.FechaNacimiento' => 'nullable|date',
            '*.Usuario' => 'nullable|string',
            '*.TimeStampAlta' => 'nullable|date',
            '*.TimeStampModif' => 'nullable|date',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos.',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            ListaNegraCNSF::insert($data);
            return response()->json([
                'success' => true,
                'message' => 'Registros insertados exitosamente.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al insertar registros.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
