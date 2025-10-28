<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alertas;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class AlertasController extends Controller
{
    public function index()
    {
        return Inertia::render('Alertas/Index');
    }

    /**
     * Inserta de forma masiva uno o más registros de Alertas.
     */
    public function bulkInsert(Request $request)
    {
        $data = $request->input('registros');

        if (!is_array($data)) {
            return response()->json([
                'error' => 'El formato de entrada es incorrecto. Se esperaba un arreglo de registros bajo la clave "registros".'
            ], 400);
        }

        // Define las reglas de validación para cada registro (IDAlertas es requerido e integer)
        $rules = [
            '*.IDAlertas' => 'required|integer',
            '*.Folio' => 'nullable|string',
            '*.Patron' => 'nullable|string',
            '*.NCliente' => 'nullable|string',
            '*.Nombre' => 'nullable|string',
            '*.NoOperacion' => 'nullable|string',
            '*.NoPoliza' => 'nullable|string',
            '*.FechaDeteccion' => 'nullable|date',
            '*.Hora' => 'nullable|string',
            '*.FechaOperacion' => 'nullable|date',
            '*.HoraOperacion' => 'nullable|string',
            '*.NoMovimiento' => 'nullable|string',
            '*.Monto' => 'nullable|numeric',
            '*.InstrumentoMonetario' => 'nullable|string',
            '*.Agente' => 'nullable|string',
            '*.Estatus' => 'nullable|string',
            '*.Descripcion' => 'nullable|string',
            '*.Razones' => 'nullable|string',
            '*.Evidencias' => 'nullable|string',
            '*.IDReporteOP' => 'nullable|integer',
            '*.IDPago' => 'nullable|integer',
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
                $row['IDAlertas'] = isset($registro['IDAlertas']) ? $registro['IDAlertas'] : null;
                $row['Folio'] = empty($registro['Folio']) ? null : $registro['Folio'];
                $row['Patron'] = empty($registro['Patron']) ? null : $registro['Patron'];
                $row['NCliente'] = empty($registro['NCliente']) ? null : $registro['NCliente'];
                $row['Nombre'] = empty($registro['Nombre']) ? null : $registro['Nombre'];
                $row['NoOperacion'] = empty($registro['NoOperacion']) ? null : $registro['NoOperacion'];
                $row['NoPoliza'] = empty($registro['NoPoliza']) ? null : $registro['NoPoliza'];
                $row['FechaDeteccion'] = empty($registro['FechaDeteccion']) ? null : $registro['FechaDeteccion'];
                $row['Hora'] = empty($registro['Hora']) ? null : $registro['Hora'];
                $row['FechaOperacion'] = empty($registro['FechaOperacion']) ? null : $registro['FechaOperacion'];
                $row['HoraOperacion'] = empty($registro['HoraOperacion']) ? null : $registro['HoraOperacion'];
                $row['NoMovimiento'] = empty($registro['NoMovimiento']) ? null : $registro['NoMovimiento'];
                $row['Monto'] = isset($registro['Monto']) && $registro['Monto'] !== '' ? $registro['Monto'] : null;
                $row['InstrumentoMonetario'] = empty($registro['InstrumentoMonetario']) ? null : $registro['InstrumentoMonetario'];
                $row['Agente'] = empty($registro['Agente']) ? null : $registro['Agente'];
                $row['Estatus'] = empty($registro['Estatus']) ? null : $registro['Estatus'];
                $row['Descripcion'] = empty($registro['Descripcion']) ? null : $registro['Descripcion'];
                $row['Razones'] = empty($registro['Razones']) ? null : $registro['Razones'];
                $row['Evidencias'] = empty($registro['Evidencias']) ? null : $registro['Evidencias'];
                $row['IDReporteOP'] = isset($registro['IDReporteOP']) && $registro['IDReporteOP'] !== '' ? $registro['IDReporteOP'] : null;
                $row['IDPago'] = isset($registro['IDPago']) && $registro['IDPago'] !== '' ? $registro['IDPago'] : null;
                // created_at y updated_at se setean automáticamente por Eloquent si se usan $timestamps
                $cleanData[] = $row;
            }

            Alertas::insert($cleanData);
            return response()->json([
                'message' => 'Registros de alertas insertados correctamente',
                'success' => true
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Ocurrió un error al insertar los registros de alertas.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
