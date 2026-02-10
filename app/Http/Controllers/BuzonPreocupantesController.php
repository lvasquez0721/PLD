<?php

namespace App\Http\Controllers;

use App\Models\BuzonPreocupante;
use App\Models\TbAlertas;
use Illuminate\Http\Request; // el modelo de tu tabla
use Illuminate\Support\Facades\DB;
use Inertia\Inertia; // el modelo de tu tabla

class BuzonPreocupantesController extends Controller
{
    public function index()
    {
        // Obtiene los registros cuyo Estatus sea NULL
        $buzon = BuzonPreocupante::whereNull('Estatus')->get();

        // Si tuvieras datos adicionales, los puedes enviar también
        $toast = session('toast');

        return Inertia::render('BuzonPreocupantes/Index', [
            'buzon' => $buzon,
            'toast' => $toast,
        ]);
    }

    public function pasarAlertas(Request $request)
    {
        try {
            // Validar los datos del request
            $validated = $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer',
            ]);

            // Obtener el siguiente ID autoincremental
            $validated['IDRegistroAlerta'] = (TbAlertas::max('IDRegistroAlerta') ?? 0) + 1;

            // Convertir los IDs a enteros
            $ids = array_map('intval', $validated['ids']);

            if (empty($ids)) {
                return redirect()->back()->with('toast', [
                    'type' => 'error',
                    'message' => 'No se seleccionaron reportes.',
                ]);
            }

            $idsList = implode(',', $ids);

            // Insertar registros en tbalertas
            DB::statement("
            INSERT INTO tbAlertas (
                IDRegistroAlerta,
                Patron,
                FechaDeteccion,
                HoraDeteccion,
                FechaOperacion,
                HoraOperacion,
                Descripcion,
                IDReporteOP,
                Estatus
            )
            SELECT 
                {$validated['IDRegistroAlerta']} AS IDRegistroAlerta,
                'Nuevo' AS Patron,
                DATE(Fecha) AS FechaDeteccion,
                TIME(Fecha) AS HoraDeteccion,
                DATE(Fecha) AS FechaOperacion,
                TIME(Fecha) AS HoraOperacion,
                Descripcion,
                IDReporteOP,
                'Generado' AS Estatus
            FROM tbBuzonPreocupantes AS r
            WHERE r.Estatus IS NULL
              AND r.IDReporteOP IN ($idsList)
              AND NOT EXISTS (
                  SELECT 1 
                  FROM tbAlertas AS a 
                  WHERE a.IDReporteOP = r.IDReporteOP
              )
        ");

            // Actualizar estatus en tbbuzonpreocupantes
            DB::statement("
            UPDATE tbBuzonPreocupantes
            SET Estatus = 'atender'
            WHERE IDReporteOP IN ($idsList)
        ");

            return response()->json(['message' => 'Alertas generadas correctamente.'], 200);

        } catch (\Exception $e) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'Ocurrió un error: '.$e->getMessage(),
            ]);
        }
    }

    public function store(Request $request)
    {
        try {
            \Log::info('Store method called', ['request' => $request->all()]);

            // Validar los datos recibidos del formulario
            $validated = $request->validate([
                'Descripcion' => 'required|string|max:255',
            ]);

            \Log::info('Validation passed', ['validated' => $validated]);

            // Variables
            $Descripcion = $validated['Descripcion'];
            $usuario = auth()->check() ? auth()->user()->nombre : 'Sistema';

            \Log::info('User info', ['usuario' => $usuario]);

            $ultimoID = DB::table('tbBuzonPreocupantes')->max('IDReporteOP');
            $nuevoID = ($ultimoID !== null) ? $ultimoID + 1 : 1;

            \Log::info('Calculated ID', ['nuevoID' => $nuevoID]);

            $BuzonPreocupante = new BuzonPreocupante;
            $BuzonPreocupante->IDReporteOP = $nuevoID;
            $BuzonPreocupante->Fecha = now();  // Corregido
            $BuzonPreocupante->Descripcion = $Descripcion;
            $BuzonPreocupante->Usuario = $usuario;
            $BuzonPreocupante->Estatus = null;

            $BuzonPreocupante->save();

            \Log::info('Record saved successfully', ['id' => $nuevoID]);

            return response()->json([
                'success' => true,
                'message' => 'Registro guardado correctamente.',
                'id' => $nuevoID,
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in store method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el registro: '.$e->getMessage(),
            ], 500);
        }
    }
}
