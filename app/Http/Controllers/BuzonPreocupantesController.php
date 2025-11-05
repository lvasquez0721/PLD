<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\BuzonPreocupante; // el modelo de tu tabla
use Illuminate\Support\Facades\DB;

class BuzonPreocupantesController extends Controller
{
    public function index()
    {
        //obtiene con un filtro $buzon = BuzonPreocupante::where('IDReporteOP', 1)->get();
        
        // Obtiene todos los registros
        $buzon = BuzonPreocupante::all(); // todos los registros
        // Si tuvieras datos adicionales, los puedes enviar también
        $toast = session('toast'); 
        
        return Inertia::render('BuzonPreocupantes/Index', [
            'buzon' => $buzon,
            'toast' => $toast,
        ]);
    }

    public function pasarAlertas(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        try {
            // Convertir los IDs a enteros y construir la lista segura para la consulta
            $ids = array_map('intval', $request->ids);

            if (empty($ids)) {
                return response()->json(['message' => 'No se seleccionaron reportes.'], 400);
            }

            // Construir la lista de IDs para la cláusula IN
            $idsList = implode(',', $ids);

            // Ejecutar un solo INSERT SELECT evitando duplicados
            DB::statement("
                INSERT INTO tbAlertas (
                    Patron,
                    FechaDeteccion,
                    Hora,
                    FechaOperacion,
                    HoraOperacion,
                    Descripcion,
                    IDReporteOP,
                    Estatus
                )
                SELECT 
                    'Nuevo' AS Patron,
                    DATE(Fecha) AS FechaDeteccion,
                    TIME(Fecha) AS Hora,
                    DATE(Fecha) AS FechaOperacion,
                    TIME(Fecha) AS HoraOperacion,
                    Descripcion,
                    IDReporteOP,
                    'Generado' AS Estatus
                FROM tbReportesOP AS r
                WHERE r.StatusReporte = 'atender'
                  AND r.IDReporteOP IN ($idsList)
                  AND NOT EXISTS (
                      SELECT 1 
                      FROM tbAlertas AS a 
                      WHERE a.IDReporteOP = r.IDReporteOP
                  )
            ");

            return response()->json(['message' => 'Alertas generadas correctamente.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}