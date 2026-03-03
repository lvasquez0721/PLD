<?php

namespace App\Http\Controllers;

use App\Models\TbReporteRegulatorioPLD;
use App\Models\CatTipoOperacion;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteOperacionesController extends Controller
{
    public function index()
    {
        // Carga todos los reportes, ordenados por fecha de creación descendente
        $reportes = TbReporteRegulatorioPLD::orderBy('created_at', 'desc')->get();

        // Obtiene todos los valores únicos del campo TipoReporte
        $tiposOperacion = TbReporteRegulatorioPLD::select('TipoReporte')
            ->distinct()
            ->pluck('TipoReporte');



        return Inertia::render('ReporteOperaciones/Index', [
            'reportes' => $reportes,
            'tiposOperacion' => $tiposOperacion,
        ]);
    }

    public function obtenerReporte(Request $request)
    {
        $filtros = $request->only(['tipo', 'estatus', 'fecha_ini', 'fecha_fin']);

        $query = TbReporteRegulatorioPLD::query();

        if (!empty($filtros['tipo']) && $filtros['tipo'] !== 'Todos') {
            $query->where('TipoReporte', $filtros['tipo']);
        }
        if (!empty($filtros['estatus']) && $filtros['estatus'] !== 'Todos') {
            $query->where('Estatus', $filtros['estatus']);
        }
        if (!empty($filtros['fecha_ini']) && !empty($filtros['fecha_fin'])) {
            $inicio = $filtros['fecha_ini'] . ' 00:00:00';
            $fin = $filtros['fecha_fin'] . ' 23:59:59';
            $query->whereBetween('created_at', [$inicio, $fin]);
        }

        $reportes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'reportes' => $reportes,
        ]);

    }

    public function exportarCSV(Request $request)
    {
        $filtros = $request->only(['tipo', 'estatus', 'fecha_ini', 'fecha_fin']);

        $query = TbReporteRegulatorioPLD::query();

        if (!empty($filtros['tipo']) && $filtros['tipo'] !== 'Todos') {
            $query->where('TipoReporte', $filtros['tipo']);
        }
        if (!empty($filtros['estatus']) && $filtros['estatus'] !== 'Todos') {
            $query->where('Estatus', $filtros['estatus']);
        }
        if (!empty($filtros['fecha_ini']) && !empty($filtros['fecha_fin'])) {
            $inicio = $filtros['fecha_ini'] . ' 00:00:00';
            $fin = $filtros['fecha_fin'] . ' 23:59:59';
            $query->whereBetween('created_at', [$inicio, $fin]);
        }

        $reportes = $query->orderBy('created_at', 'desc')->get();

        if ($reportes->isEmpty()) {
            return response()->json(['message' => 'No hay datos para exportar'], 404);
        }

        $fileName = 'reporte_operaciones_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($reportes) {
            $file = fopen('php://output', 'w');
            // Añadir BOM para que Excel reconozca UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, array_keys($reportes->first()->toArray()));

            foreach ($reportes as $reporte) {
                fputcsv($file, $reporte->toArray());
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
