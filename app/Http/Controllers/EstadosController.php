<?php

namespace App\Http\Controllers;

use App\Models\Estados;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EstadosController extends Controller
{
    /**
     * Inserta de forma masiva uno o varios estados.
     */
    public function bulkInsert(Request $request)
    {
        $data = $request->input('estados');

        if (! is_array($data)) {
            return response()->json(['error' => 'El formato de entrada es incorrecto. Se esperaba un arreglo de estados en "estados".'], 400);
        }

        $rules = [
            '*.IdEstado' => 'required|integer',
            '*.Estado' => 'required|string',
            '*.CveEntidad' => 'required|string',
            '*.ApoyoSAGARPA16' => 'nullable|string',
            '*.ApoyoSAGARPA17' => 'nullable|string',
            '*.NivelRiesgo' => 'nullable|string',
            '*.TimeStampModif' => 'nullable|date',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $estados = Estados::insert($data);

            return response()->json([
                'message' => 'Estados insertados correctamente',
                'success' => true,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al insertar los estados.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
