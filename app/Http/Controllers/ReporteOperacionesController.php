<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteOperacionesController extends Controller
{
    public function index()
    {
        return Inertia::render('ReporteOperaciones/Index');
    }

    public function obtenerReporte(Request $request)
    {
        // Lista de filtros esperados
        $filtros = $request->only([
            'estatus', 'tipo', 'fecha_ini', 'fecha_fin',
        ]);

        // Validar que todos los campos requeridos estén presentes
        if (isset($filtros['estatus']) && isset($filtros['tipo']) && isset($filtros['fecha_ini']) && isset($filtros['fecha_fin'])) {
            return response()->json([
                'message' => 'ok',
                'received' => $filtros,
            ], 200);
        }

        // Si falta algún campo, responder con error
        return response()->json([
            'message' => 'faltan campos',
            'received' => $filtros,
        ], 400);
    }
}
