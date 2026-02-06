<?php

namespace App\Http\Controllers;

use App\Models\ListasBloqueadas\LogListaNegraCNSF;
use App\Models\ListasBloqueadas\TbControlOficios;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // error log
use Inertia\Inertia; // <-- Agregar esto

class ListaNegraController extends Controller
{
    /* Listar registros con filtros y paginación */
    public function index(Request $request)
    {
        // Puedes agregar filtros opcionales si los envías desde el frontend
        $buscar = $request->input('buscar');
        $perPage = $request->input('perPage', 10);

        // Obtener los registros
        $listas = TbListasNegraCNSF::select(
            'IDRegistroListaCNSF',
            'Nombre',
            'RFC',
            'CURP',
            'FechaNacimiento',
            'Pais'
        )
            ->when($buscar, function ($query, $buscar) {
                $query->where('Nombre', 'LIKE', "%{$buscar}%")
                    ->orWhere('RFC', 'LIKE', "%{$buscar}%");
            })
            ->orderBy('IDRegistroListaCNSF', 'DESC')
            ->get();

        // Enviar datos a Vue
        return Inertia::render('ListaNegra/Index', [
            'listas' => $listas,
            'filters' => [
                'buscar' => $buscar,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function insert(Request $request)
    {
        // dd($request->all()); // Muestra todos los datos del request
        try {
            $user = Auth::user(); // Obtener el usuario autenticado

            $request->validate([
                'nombre' => 'required|string|max:255',
                'rfc' => 'required|string|max:13',
                'curp' => 'required|string|max:18',
                'fecha_nacimiento' => 'required|date',
                'pais' => 'required|string|max:255',
                'archivo' => 'required|file|mimes:pdf',
            ]);

            DB::beginTransaction();

            $rutaArchivo = null;
            // Si viene archivo, obtenemos su nombre SIN guardarlo aún
            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $rutaArchivo = time().'_'.$file->getClientOriginalName();
            }

            // 1. Insertar registro principal
            $lista = TbListasNegraCNSF::create([
                'Nombre' => $request->nombre,
                'Direccion' => '',
                'RFC' => $request->rfc,
                'CURP' => $request->curp,
                'FechaNacimiento' => $request->fecha_nacimiento,
                'Pais' => $request->pais,
                'OficiosRelacionados' => $rutaArchivo,
                'UsuarioAlta' => $user->usuario ? $user->usuario : 'Sistema',
                'TimeStampAlta' => now(),
                'UsuarioModif' => null,
                'TimeStampModif' => now(),
            ]);

            $idLista = $lista->IDRegistroListaCNSF;

            // 2. Guardar oficio relacionado
            if ($request->hasFile('archivo')) {
                $okOficio = $this->oficiosCNSF($request, $idLista, 3); // 3 = insertar
                if (! $okOficio) {
                    DB::rollBack();

                    return redirect()->back()->with('error', 'No se pudo guardar el oficio.');
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Registro agregado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return redirect()->back()->with('error', 'Ocurrió un error'.$e->getMessage());

        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user(); // Obtener el usuario autenticado

            $request->validate([
                'nombre' => 'required|string|max:255',
                'rfc' => 'required|string|max:13',
                'curp' => 'required|string|max:18',
                'fecha_nacimiento' => 'required|date',
                'pais' => 'required|string|max:255',
                'archivo' => 'required|file|mimes:pdf',
            ]);

            DB::beginTransaction();

            $lista = TbListasNegraCNSF::findOrFail($id);

            if ($request->hasFile('archivo')) {
                $okOficio = $this->oficiosCNSF($request, $id, 1); // acción 1 = update

                if (! $okOficio) {
                    DB::rollBack();

                    return redirect()->back()->with('error', 'No se pudo guardar el oficio.');
                }
            }

            $okBitacora = $this->guardarBitacora($id, 1);

            if (! $okBitacora) {
                DB::rollBack();

                return redirect()->back()->with('error', 'No se pudo guardar la bitácora.');
            }

            $lista->update([
                'Nombre' => $request->nombre,
                'RFC' => $request->rfc,
                'CURP' => $request->curp,
                'FechaNacimiento' => $request->fecha_nacimiento,
                'Pais' => $request->pais,
                'UsuarioModif' => $user->usuario ? $user->usuario : 'Sistema',
                'TimeStampModif' => now(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Registro actualizado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Update: '.$e->getMessage());

            return redirect()->back()->with('error', 'Ocurrió un error al actualizar.');
        }

    }

    public function delete(Request $request, $id)
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'rfc' => 'required|string|max:13',
                'curp' => 'required|string|max:18',
                'fecha_nacimiento' => 'required|date',
                'pais' => 'required|string|max:255',
                'archivo' => 'required|file|mimes:pdf',
            ]);

            DB::beginTransaction();

            $lista = TbListasNegraCNSF::findOrFail($id);

            // 1) Guardar oficio primero (si mandan archivo)
            if ($request->hasFile('archivo')) {
                $okOficio = $this->oficiosCNSF($request, $id, 2); // 2 = delete

                if (! $okOficio) {
                    DB::rollBack();

                    return redirect()->back()->with('error', 'No se pudo guardar el oficio.');
                }
            }

            $okBitacora = $this->guardarBitacora($id, 2);

            if (! $okBitacora) {
                DB::rollBack();

                return redirect()->back()->with('error', 'No se pudo guardar la bitácora.');
            }

            // 3) Eliminar
            $lista->delete();

            DB::commit();

            return redirect()->back()->with('success', 'Registro eliminado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error Delete: '.$e->getMessage());

            return redirect()->back()->with('error', 'Ocurrió un error al eliminar.');
        }
    }

    /* Guarda bitácora del registro */
    public function guardarBitacora($id, $accion)
    {
        $user = Auth::user(); // Obtener el usuario autenticado
        $LN = TbListasNegraCNSF::where('IDRegistroListaCNSF', $id)->first();

        if (! $LN) {
            return false;
        }

        $insert = LogListaNegraCNSF::create([
            'IDRegistroListaCNSF' => $LN->IDRegistroListaCNSF,
            'IDAccion' => $accion,
            'Nombre' => $LN->Nombre,
            'Direccion' => $LN->Direccion,
            'RFC' => $LN->RFC,
            'CURP' => $LN->CURP,
            'Pais' => $LN->Pais,
            'FechaNacimiento' => $LN->FechaNacimiento,
            'OficiosRelacionados' => $LN->OficiosRelacionados,
            'UsuarioAlta' => $LN->UsuarioAlta,
            'TimeStampAlta' => $LN->TimeStampAlta,
            'UsuarioModif' => $user->usuario ? $user->usuario : 'Sistema',
            'TimeStampModif' => $LN->TimeStampModif,
        ]);

        return $insert ? true : false;
    }

    /* Guarda archivo/oficio en TbControlOficios */
    public function oficiosCNSF(Request $request, $id, $accion)
    {
        $archivo = $request->file('archivo');
        $nombreArchivo = time().'_'.$archivo->getClientOriginalName();
        $path = $archivo->storeAs('public/oficios', $nombreArchivo);

        if (! $path) {
            return false;
        }

        $registro = TbControlOficios::create([
            'IDListaN' => $id,
            'PathArchivo' => $path,
            'Archivo' => $nombreArchivo,
            'IDAccion' => $accion,
            'TimeStampArchivo' => now(),
        ]);

        return $registro ? true : false;
    }

    public function buscar(Request $request)
    {
        try {
            $id = $request->input('IDCliente');

            if (empty($id)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Debe enviar el campo IDRegistroListaCNSF.',
                ], 400);
            }

            // Buscar por la llave primaria correcta
            // $registro = TbListasNegraCNSF::find($id);
            $registro = TbListasNegraCNSF::select('nombre')
                ->where('tbListasNegraCNSF.IDRegistroListaCNSF', $id)
                ->first();

            if (! $registro) {
                return response()->json(['registrosEncontrados' => 0, 'detalleListaBloqueadas' => []], 200);
            }

            // Armado de estructura EXACTA
            $detalle = [
                [
                    'lista' => 'Listas Negra CNSF',
                    'nombreDetectado' => $registro->nombre ?? '',
                    'IDListaOrigen' => 1,
                    'cargo' => '',
                    'PPEActivo' => true,
                ],
            ];

            return response()->json([
                'registrosEncontrados' => count($detalle),
                'detalleListaBloqueadas' => $detalle,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'mensaje' => 'Error al buscar información: '.$e->getMessage(),
            ], 500);
        }
    }
}
