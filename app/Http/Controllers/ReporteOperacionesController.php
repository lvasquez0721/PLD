<?php

namespace App\Http\Controllers;

use App\Models\TbAlertas;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteOperacionesController extends Controller
{
    private const PATRONES = ['Relevante', 'Inusual', 'Preocupante'];

    private const ESTATUS = ['Enviado', 'Por reportar'];

    public function index()
    {
        $alertas = TbAlertas::whereIn('Patron', self::PATRONES)
            ->whereIn('Estatus', self::ESTATUS)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('ReporteOperaciones/Index', [
            'alertas' => $alertas,
        ]);
    }

    public function obtenerReporte(Request $request)
    {
        $filtros = $request->only(['patron', 'estatus', 'fecha_ini', 'fecha_fin']);

        $query = TbAlertas::whereIn('Patron', self::PATRONES)
            ->whereIn('Estatus', self::ESTATUS);

        if (! empty($filtros['patron']) && $filtros['patron'] !== 'Todos') {
            $query->where('Patron', $filtros['patron']);
        }
        if (! empty($filtros['estatus']) && $filtros['estatus'] !== 'Todos') {
            $query->where('Estatus', $filtros['estatus']);
        }
        if (! empty($filtros['fecha_ini']) && ! empty($filtros['fecha_fin'])) {
            $inicio = $filtros['fecha_ini'].' 00:00:00';
            $fin = $filtros['fecha_fin'].' 23:59:59';
            $query->whereBetween('created_at', [$inicio, $fin]);
        }

        $alertas = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['alertas' => $alertas]);
    }

    public function exportarCSV(Request $request)
    {
        $filtros = $request->only(['patron', 'estatus', 'fecha_ini', 'fecha_fin']);

        $query = TbAlertas::whereIn('Patron', self::PATRONES)
            ->whereIn('Estatus', self::ESTATUS);

        if (! empty($filtros['patron']) && $filtros['patron'] !== 'Todos') {
            $query->where('Patron', $filtros['patron']);
        }
        if (! empty($filtros['estatus']) && $filtros['estatus'] !== 'Todos') {
            $query->where('Estatus', $filtros['estatus']);
        }
        if (! empty($filtros['fecha_ini']) && ! empty($filtros['fecha_fin'])) {
            $inicio = $filtros['fecha_ini'].' 00:00:00';
            $fin = $filtros['fecha_fin'].' 23:59:59';
            $query->whereBetween('created_at', [$inicio, $fin]);
        }

        $alertas = $query->orderBy('created_at', 'desc')->get();

        if ($alertas->isEmpty()) {
            return response()->json(['message' => 'No hay datos para exportar'], 404);
        }

        $fileName = 'reporte_operaciones_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($alertas) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, array_keys($alertas->first()->toArray()));
            foreach ($alertas as $alerta) {
                fputcsv($file, $alerta->toArray());
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
