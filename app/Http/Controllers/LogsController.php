<?php

namespace App\Http\Controllers;

use App\Models\LogApi;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $logs = LogApi::orderBy('id', 'desc')
            ->limit(200)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'usuario' => $log->Usuario,
                    'metodo' => $log->Metodo,
                    'ruta' => $log->Ruta,
                    'ip' => $log->IP,
                    'estatus' => $log->Estatus,
                    'duracion_ms' => $log->DuracionMs,
                    'fecha' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : null,
                    'tiene_body' => ! empty($log->Payload),
                    'tiene_response' => ! empty($log->Respuesta),
                ];
            });

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
        ]);
    }

    public function show(Request $request, $id)
    {
        $log = LogApi::find($id);

        if (! $log) {
            return response()->json(['success' => false, 'message' => 'Log no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'body' => $log->Payload,
            'response' => $log->Respuesta,
        ]);
    }
}
