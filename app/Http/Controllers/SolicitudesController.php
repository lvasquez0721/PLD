<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SolicitudesController extends Controller
{
    //
    public function storeMassive(Request $request)
    {
        // Validar que recibimos un array de solicitudes
        $validated = $request->validate([
            'solicitudes' => 'required|array|min:1',
            'solicitudes.*.IDSolicitud' => 'required|integer',
            'solicitudes.*.IDSolicitante' => 'required|integer',
            'solicitudes.*.FCreacion' => 'required|date',
            'solicitudes.*.FActualizacion' => 'required|date',
            'solicitudes.*.FGenerada' => 'nullable|date',
            'solicitudes.*.StatusSolicitud' => 'required|string',
            'solicitudes.*.NoSolicitud' => 'required|string',
            'solicitudes.*.Usuario' => 'required|string',
            'solicitudes.*.IDAgente' => 'nullable|integer',
            'solicitudes.*.año' => 'required|integer',
        ]);

        $data = $validated['solicitudes'];
        $inserted = [];
        $errors = [];

        foreach ($data as $k => $solicitud) {
            try {
                // Puedes ajustar para omitir 'created_at', 'updated_at', pues Eloquent lo maneja si es necesario
                $inserted[] = \App\Models\Solicitudes::create($solicitud);
            } catch (\Exception $e) {
                $errors[] = [
                    'key' => $k,
                    'input' => $solicitud,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => empty($errors)
                ? 'Las solicitudes fueron insertadas correctamente.'
                : 'Algunas solicitudes no pudieron ser insertadas.',
            'solicitudes' => $inserted,
        ], empty($errors) ? 201 : 207);
    }
}
