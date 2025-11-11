<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\BuzonPreocupante; // el modelo de tu tabla
use Illuminate\Support\Facades\DB;
use App\Models\tbAlertas; // el modelo de tu tabla


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
            'ids.*' => 'integer'
        ]);

        // Obtener el siguiente ID autoincremental
        $validated['IDRegistroAlerta'] = (tbalertas::max('IDRegistroAlerta') ?? 0) + 1;

        // Convertir los IDs a enteros
        $ids = array_map('intval', $validated['ids']);

        if (empty($ids)) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'No se seleccionaron reportes.'
            ]);
        }

        $idsList = implode(',', $ids);

        // Insertar registros en tbalertas
        DB::statement("
            INSERT INTO tbalertas (
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
            FROM tbbuzonpreocupantes AS r
            WHERE r.Estatus = 'atender'
              AND r.IDReporteOP IN ($idsList)
              AND NOT EXISTS (
                  SELECT 1 
                  FROM tbalertas AS a 
                  WHERE a.IDReporteOP = r.IDReporteOP
              )
        ");

        // Actualizar estatus en tbbuzonpreocupantes
        DB::statement("
            UPDATE tbbuzonpreocupantes
            SET Estatus = 'atender'
            WHERE IDReporteOP IN ($idsList)
        ");

        return response()->json(['message' => 'Alertas generadas correctamente.'], 200);

    } catch (\Exception $e) {
        return redirect()->back()->with('toast', [
            'type' => 'error',
            'message' => 'Ocurrió un error: ' . $e->getMessage()
        ]);
    }
}



public function store(Request $request)
{
    // Validar los datos recibidos del formulario
    $validated = $request->validate([
        'Descripcion' => 'required|string|max:50',
    ]);

    // Variables
    $Descripcion = $validated['Descripcion'];
    $usuario = auth()->user()->nombre ?? 'Sistema';
    $ultimoID = DB::table('tbbuzonpreocupantes')->max('IDReporteOP') ?? 0;
    $nuevoID = $ultimoID + 1;

    try {
        
        $BuzonPreocupante = new BuzonPreocupante();
        $BuzonPreocupante->IDReporteOP = $nuevoID ;
        $BuzonPreocupante->Fecha = NOW();
        $BuzonPreocupante->Descripcion = $Descripcion;
        $BuzonPreocupante->Usuario = $usuario;
        $BuzonPreocupante->Estatus = NULL;
        $BuzonPreocupante->save();

        return response()->json([
            'success' => true,
            'message' => 'Registro guardado correctamente.'
            
        ]);


    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al guardar el registro: ' . $e->getMessage()
        ], 500);
    }
}



}