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
                    'tiene_payload' => ! empty($log->Payload),
                ];
            });

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
        ]);
    }
}
