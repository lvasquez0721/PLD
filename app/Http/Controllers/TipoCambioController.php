<?php

namespace App\Http\Controllers;

use App\Models\TipoCambio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TipoCambioController extends Controller
{
    /**
     * Inserta de forma masiva uno o varios registros de tipo de cambio.
     */
    public function storeMasivo(Request $request)
    {
        $data = $request->input('tipocambio');

        if (! is_array($data)) {
            return response()->json(['error' => 'El formato de entrada es incorrecto. Se esperaba un arreglo de tipo de cambio en "tipocambio".'], 400);
        }

        $rules = [
            '*.fecha' => 'required|date',
            '*.campko' => 'nullable|numeric',
            '*.DOF_dolar' => 'nullable|numeric',
            '*.DOF_udi' => 'nullable|numeric',
            '*.FIX_dolar' => 'nullable|numeric',
            '*.DOF_TIIE28' => 'nullable|numeric',
            '*.DOF_TIIE91' => 'nullable|numeric',
            '*.FIX_udi' => 'nullable|numeric',
            '*.FIX_TIIE28' => 'nullable|numeric',
            '*.FIX_CETES' => 'nullable|numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            TipoCambio::insert($data);

            return response()->json([
                'message' => 'Registros de tipo de cambio insertados correctamente',
                'success' => true,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al insertar los registros de tipo de cambio.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
