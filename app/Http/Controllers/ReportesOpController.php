<?php

namespace App\Http\Controllers;

use App\Models\ReportesOp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportesOpController extends Controller
{
    /**
     * Inserta de forma masiva uno o más registros de ReportesOp.
     */
    public function bulkInsert(Request $request)
    {
        $data = $request->input('registros');

        if (! is_array($data)) {
            return response()->json([
                'error' => 'El formato de entrada es incorrecto. Se esperaba un arreglo de registros bajo la clave "registros".',
            ], 400);
        }

        $rules = [
            '*.IDReporteOP' => 'required|integer',
            '*.Fecha' => 'required|date',
            '*.Descripcion' => 'nullable|string',
            '*.Usuario' => 'nullable|string',
            '*.StatusReporte' => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Limpiar y convertir valores nulos tipo string vacía a null para los campos nullable
            $cleanData = [];
            foreach ($data as $registro) {
                $row = [];
                $row['IDReporteOP'] = isset($registro['IDReporteOP']) ? $registro['IDReporteOP'] : null;
                $row['Fecha'] = empty($registro['Fecha']) ? null : $registro['Fecha'];
                $row['Descripcion'] = empty($registro['Descripcion']) ? null : $registro['Descripcion'];
                $row['Usuario'] = empty($registro['Usuario']) ? null : $registro['Usuario'];
                $row['StatusReporte'] = empty($registro['StatusReporte']) ? null : $registro['StatusReporte'];
                $cleanData[] = $row;
            }

            ReportesOp::insert($cleanData);

            return response()->json([
                'message' => 'Registros de reportes insertados correctamente',
                'success' => true,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al insertar los registros de reportes.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
}
