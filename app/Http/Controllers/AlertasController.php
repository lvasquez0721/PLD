<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alertas;
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
        $validatedData = $request->validate([
            '*.IDAlertas' => 'required|integer',
            '*.Folio' => 'required|string|max:255',
            '*.Patron' => 'required|string|max:255',
            '*.NCliente' => 'required|string|max:255',
            '*.Nombre' => 'required|string|max:255',
            '*.NoOperacion' => 'required|string|max:255',
            '*.NoPoliza' => 'required|string|max:255',
            '*.FechaDeteccion' => 'required|date',
            '*.Hora' => 'required|string|max:255',
            '*.FechaOperacion' => 'required|date',
            '*.HoraOperacion' => 'required|string|max:255',
            '*.NoMovimiento' => 'required|string|max:255',
            '*.Monto' => 'required|numeric',
            '*.InstrumentoMonetario' => 'required|string|max:255',
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
            $alert['created_at'] = now();
            $alert['updated_at'] = now();
        }

        DB::table('tbAlertas')->insert($validatedData);

        return response()->json(['message' => 'Alertas insertadas masivamente con éxito'], 201);
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

        $alertas = Alertas::whereBetween('FechaDeteccion', [$fechaInicio, $fechaFin])
            ->get();

        return response()->json($alertas);
    }
}
