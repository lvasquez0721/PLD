<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;

class ListaNegraController extends Controller
{
    // public function index()
    // {
    //     return Inertia::render('ListaNegra/Index');

    // }
    
    public function index()
    {
        // Obtener todos los registros
        
        $listas = TbListasNegraCNSF::select('IDRegistroListaCNSF', 'Nombre', 'RFC')->get();
        
        // dd($listas->toArray()); //IMPRIMIR ANTES DE ENVIAR
        return Inertia::render('ListaNegra/Index', [
            'listas' => $listas
        ]);

    }

    public function insert(Request $request)
    {
        // dd($request->all()); // Muestra todos los datos del request

        $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'required|string|max:13',
            'curp' => 'required|string|max:18',
            'fecha_nacimiento' => 'required|date',
            'pais' => 'required|string|max:255',
            'archivo' => 'nullable|file|mimes:pdf|max:2048',
        ]);

        $lista = new TbListasNegraCNSF();
        $lista->Nombre = $request->nombre;
        $lista->RFC = $request->rfc;
        $lista->CURP = $request->curp;
        $lista->FechaNacimiento = $request->fecha_nacimiento;
        $lista->Pais = $request->pais;

        // Guardar archivo si existe
        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->storeAs('public/listas', $filename);
            $lista->OficiosRelacionados = $filename;
        }

        // Guardar timestamps automáticos
        $lista->UsuarioAlta = 'Sistema';
        $lista->TimeStampAlta = now();
        $lista->UsuarioModif = null;
        $lista->TimeStampModif = now();

        $lista->save(); // Eloquent maneja automáticamente las comillas y tipos


        return redirect()->back()->with('success', 'Registro agregado correctamente');
    }

    public function update(Request $request, $id)
    {
        $lista = TbListasNegraCNSF::findOrFail($id);
        $lista->Nombre = $request->nombre;
        $lista->RFC = $request->rfc;
        $lista->CURP = $request->curp;
        $lista->FechaNacimiento = $request->fecha_nacimiento;
        $lista->Pais = $request->pais;

        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $filename = time().'_'.$file->getClientOriginalName();
            $file->storeAs('public/listas', $filename);
            $lista->OficiosRelacionados = $filename;
        }

        $lista->UsuarioModif = 'Sistema';
        $lista->TimeStampModif = now();
        $lista->save();

        return redirect()->back()->with('success', 'Registro actualizado correctamente');
    }

    public function delete($id)
    {
        $lista = TbListasNegraCNSF::findOrFail($id);
        $lista->delete();

        return redirect()->back()->with('success', 'Registro eliminado correctamente');
    }



}