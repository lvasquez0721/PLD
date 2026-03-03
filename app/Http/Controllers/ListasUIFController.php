<?php

namespace App\Http\Controllers;

use App\Models\ListasBloqueadas\TbListasNegrasUIF;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ListasUIFController extends Controller
{
    public function index()
    {
        $listasUIF = TbListasNegrasUIF::all();

        return Inertia::render('ListasUIF/Index', [
            'listasUIF' => $listasUIF,
            'toast' => session('toast'),
        ]);
    }



    public function altaListasUIF(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'RFCCURP' => 'required|string|max:20',
            'fechaNacimiento' => 'nullable|date_format:d-m-Y',
            'fechaPublicacionAcuerdo' => 'nullable|date_format:d-m-Y',
            'acuerdo' => 'nullable|string|max:255',
            'noOficioUIF' => 'required|string|max:255',
            'anioLista' => 'required|integer|min:1900|max:'.(now()->year + 1),
        ]);

        try {
            $listasUIF = new TbListasNegrasUIF();
            $listasUIF->Nombre = $validated['nombre'];
            $listasUIF->RFC = $validated['RFCCURP'];
            $listasUIF->CURP = $validated['RFCCURP'];

            // Convertir fechas al formato de la base de datos (Y-m-d) solo si vienen
            $listasUIF->FechaNacimiento = !empty($validated['fechaNacimiento'])
                ? \Carbon\Carbon::createFromFormat('d-m-Y', $validated['fechaNacimiento'])->format('Y-m-d')
                : null;

            $listasUIF->FechaPubAcuerdo = !empty($validated['fechaPublicacionAcuerdo'])
                ? \Carbon\Carbon::createFromFormat('d-m-Y', $validated['fechaPublicacionAcuerdo'])->format('Y-m-d')
                : null;

            $listasUIF->Acuerdo = $validated['acuerdo'] ?? null;
            $listasUIF->NoOficioUIF = $validated['noOficioUIF'];
            $listasUIF->AnioLista = $validated['anioLista'];
            $listasUIF->UsuarioAlta = auth()->user()->usuario ?? 'Sistema';
            $listasUIF->TimeStampAlta = now();

            $listasUIF->save();

            return redirect()->back()->with('toast', [
                'type' => 'success',
                'message' => 'Registro guardado exitosamente en la lista UIF.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al dar de alta en listas UIF: '.$e->getMessage());

            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'Ocurrió un error al intentar guardar el registro. Por favor, intente de nuevo.',
            ]);
        }
    }


    public function actualizaListas(Request $request)
    {
        $validated = $request->validate([
            'IDRegistroListaUIF' => 'required|integer',
            'nombre' => 'required|string|max:255',
            'RFCCURP' => 'required|string|max:20',
            'fechaNacimiento' => 'nullable|date_format:d-m-Y',
            'fechaPublicacionAcuerdo' => 'nullable|date_format:d-m-Y',
            'acuerdo' => 'nullable|string|max:255',
            'noOficioUIF' => 'required|string|max:255',
            'anioLista' => 'required|integer|min:1900|max:'.(now()->year + 1),
        ]);

        try {
            $listasUIF = TbListasNegrasUIF::findOrFail($validated['IDRegistroListaUIF']);
            $listasUIF->Nombre = $validated['nombre'];
            $listasUIF->RFC = $validated['RFCCURP'];
            $listasUIF->CURP = $validated['RFCCURP'];

            // Convertir fechas al formato de la base de datos (Y-m-d) solo si vienen
            $listasUIF->FechaNacimiento = !empty($validated['fechaNacimiento'])
                ? \Carbon\Carbon::createFromFormat('d-m-Y', $validated['fechaNacimiento'])->format('Y-m-d')
                : null;

            $listasUIF->FechaPubAcuerdo = !empty($validated['fechaPublicacionAcuerdo'])
                ? \Carbon\Carbon::createFromFormat('d-m-Y', $validated['fechaPublicacionAcuerdo'])->format('Y-m-d')
                : null;

            $listasUIF->Acuerdo = $validated['acuerdo'] ?? null;
            $listasUIF->NoOficioUIF = $validated['noOficioUIF'];
            $listasUIF->AnioLista = $validated['anioLista'];
            $listasUIF->UsuarioModif = auth()->user()->usuario ?? 'Sistema';
            $listasUIF->TimeStampModif = now();

            $listasUIF->save();

            return redirect()->back()->with('toast', [
                'type' => 'success',
                'message' => 'Registro actualizado exitosamente en la lista UIF.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al editar en listas UIF: '.$e->getMessage());

            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'Ocurrió un error al intentar actualizar el registro. Por favor, intente de nuevo.',
            ]);
        }
    }


    public function getConsultaListas(Request $request)
    {
        $buscar = $request->input('nombre'); // O RFCCURP, según lo que envíe el front

        $listasUIF = TbListasNegrasUIF::when($buscar, function ($query, $buscar) {
            $query->where('Nombre', 'LIKE', "%{$buscar}%")
                  ->orWhere('RFC', 'LIKE', "%{$buscar}%")
                  ->orWhere('CURP', 'LIKE', "%{$buscar}%");
        })->get();

        return Inertia::render('ListasUIF/Index', [
            'listasUIF' => $listasUIF,
            'toast' => session('toast'),
        ]);
    }


    public function bajaListas(Request $request)
    {
        $validated = $request->validate([
            'IDRegistroListaUIF' => 'required|integer',
        ]);

        try {
            $listasUIF = TbListasNegrasUIF::findOrFail($validated['IDRegistroListaUIF']);
            $listasUIF->delete();

            return redirect()->back()->with('toast', [
                'type' => 'success',
                'message' => 'Registro eliminado exitosamente de la lista UIF.',
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al eliminar en listas UIF: '.$e->getMessage());

            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'Ocurrió un error al intentar eliminar el registro. Por favor, intente de nuevo.',
            ]);
        }
    }

    public function exportarCSV(Request $request)
    {
        try {
            $buscar = $request->input('nombre');

            $listasUIF = TbListasNegrasUIF::when($buscar, function ($query, $buscar) {
                $query->where('Nombre', 'LIKE', "%{$buscar}%")
                      ->orWhere('RFC', 'LIKE', "%{$buscar}%")
                      ->orWhere('CURP', 'LIKE', "%{$buscar}%");
            })->get();

            if ($listasUIF->isEmpty()) {
                return redirect()->back()->with('toast', [
                    'type' => 'error',
                    'message' => 'No hay datos para exportar.',
                ]);
            }

            $fileName = 'listas_uif_' . date('Ymd_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ];

            $callback = function () use ($listasUIF) {
                $file = fopen('php://output', 'w');
                // Añadir BOM para que Excel reconozca UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

                // Definir encabezados personalizados
                fputcsv($file, [
                    'ID',
                    'Nombre',
                    'RFC',
                    'CURP',
                    'Fecha Nacimiento',
                    'Fecha Pub. Acuerdo',
                    'Acuerdo',
                    'No. Oficio UIF',
                    'Año Lista'
                ]);

                foreach ($listasUIF as $item) {
                    fputcsv($file, [
                        $item->IDRegistroListaUIF,
                        $item->Nombre,
                        $item->RFC,
                        $item->CURP,
                        $item->FechaNacimiento ? $item->FechaNacimiento->format('d-m-Y') : '',
                        $item->FechaPubAcuerdo ? $item->FechaPubAcuerdo->format('d-m-Y') : '',
                        $item->Acuerdo,
                        $item->NoOficioUIF,
                        $item->AnioLista
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            \Log::error('Error al exportar CSV en listas UIF: ' . $e->getMessage());
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'Ocurrió un error al exportar el CSV.',
            ]);
        }
    }




}
