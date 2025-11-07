<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TbAlertas;
use Illuminate\Support\Facades\DB;
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
        try {
            $validatedData = $request->validate([
                '*.IDRegistroAlerta' => 'required|integer',
                '*.Folio' => 'required|string|max:255',
                '*.Patron' => 'required|string|max:255',
                '*.IDCliente' => 'required|string|max:255',
                '*.Cliente' => 'required|string|max:255',
                '*.Poliza' => 'required|string|max:255',
                '*.FechaDeteccion' => 'required|date',
                '*.IDOperacionPago' => 'required|integer',
                '*.HoraDeteccion' => 'required|string|max:255',
                '*.FechaOperacion' => 'required|date',
                '*.HoraOperacion' => 'required|string|max:255',
                '*.MontoOperacion' => 'required|numeric',
                '*.InstrumentoMonetario' => 'required|string|max:255',
                '*.RFCAgente' => 'required|string|max:255',
                '*.Agente' => 'required|string|max:255',
                '*.Estatus' => 'required|string|max:255',
                '*.Descripcion' => 'required|string',
                '*.Razones' => 'required|string',
                '*.Evidencias' => 'required|string',
                '*.IDReporteOP' => 'required|integer',
                '*.IDPago' => 'required|integer',
            ]);

            foreach ($validatedData as &$alert) {
                $alert['FechaDeteccion'] = \Carbon\Carbon::parse($alert['FechaDeteccion'])->format('Y-m-d H:i:s');
                $alert['FechaOperacion'] = \Carbon\Carbon::parse($alert['FechaOperacion'])->format('Y-m-d H:i:s');
                // Renaming fields to match TbAlertas model
                $alert['IDRegistroAlerta'] = $alert['IDRegistroAlerta'] ?? null;
                $alert['IDCliente'] = $alert['IDCliente'] ?? null;
                $alert['Cliente'] = $alert['Cliente'] ?? null;
                $alert['Poliza'] = $alert['Poliza'] ?? null;
                $alert['IDOperacionPago'] = $alert['IDOperacionPago'] ?? null;
                $alert['HoraDeteccion'] = $alert['HoraDeteccion'] ?? null;
                $alert['MontoOperacion'] = $alert['MontoOperacion'] ?? null;
                $alert['RFCAgente'] = $alert['RFCAgente'] ?? null;

                // Remove old fields if they exist
                unset($alert['IDAlertas']);
                unset($alert['NCliente']);
                unset($alert['Nombre']);
                unset($alert['NoOperacion']);
                unset($alert['NoPoliza']);
                unset($alert['Hora']);
                unset($alert['NoMovimiento']);
                unset($alert['Monto']);

                $alert['created_at'] = now();
                $alert['updated_at'] = now();
            }

            TbAlertas::insert($validatedData);

            return response()->json(['message' => 'Alertas insertadas masivamente con éxito'], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error inesperado',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene alertas por rango de fechas.
     */
    public function getAlertasByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date|after_or_equal:fechaInicio',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $fechaInicio = $request->input('fechaInicio');
        $fechaFin = $request->input('fechaFin');

        $alertas = TbAlertas::whereBetween('FechaDeteccion', [$fechaInicio, $fechaFin])
            ->get();

        return response()->json($alertas);
    }
}
