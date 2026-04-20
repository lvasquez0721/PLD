<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;
use App\Models\ListasBloqueadas\LogListaNegraCNSF;
use App\Models\ListasBloqueadas\TbControlOficios;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // error log
use Illuminate\Support\Facades\Log; // <-- Agregar esto
use Inertia\Inertia;

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

    public function exportCsv(Request $request)
    {
        try {
            $buscar = $request->input('buscar');

            $query = TbListasNegraCNSF::select(
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
                ->orderBy('IDRegistroListaCNSF', 'DESC');

            $listas = $query->get();

            if ($listas->isEmpty()) {
                return redirect()->back()->with('error', 'No hay datos para exportar.');
            }

            $fileName = 'lista_negra_cnsf_'.date('Ymd_His').'.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            ];

            $callback = function () use ($listas) {
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
                    'País',
                ]);

                foreach ($listas as $item) {
                    fputcsv($file, [
                        $item->IDRegistroListaCNSF,
                        $item->Nombre,
                        $item->RFC,
                        $item->CURP,
                        $item->FechaNacimiento,
                        $item->Pais,
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);

        } catch (\Exception $e) {
            Log::error('Error Export CSV: '.$e->getMessage());

            return redirect()->back()->with('error', 'Ocurrió un error al exportar el CSV.');
        }
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
                'fecha_nacimiento' => 'nullable|date',
                'pais' => 'nullable|string|max:255',
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

            // Log detailed exception info
            Log::error('Error Delete: '.$e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all(),
                'record_id' => $id,
            ]);

            return redirect()->back()->with('error', 'Ocurrió un error al eliminar: '.$e->getMessage());
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

            // 1. Obtener datos del cliente (sin leftJoin)
            $cliente = TbClientes::select(
                'RFC',
                'CURP',
                'Nombre',
                'ApellidoPaterno',
                'ApellidoMaterno',
                'Activo',
                'CoincideEnListasNegras',
                'EsPPEActivo'
            )
                ->where('IDCliente', $id)
                ->first();

            if (! $cliente) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'El cliente no existe.',
                ], 404);
            }

            $nombreCompleto = trim("{$cliente->Nombre} {$cliente->ApellidoPaterno} {$cliente->ApellidoMaterno}");

            $rfcGenericos = ['XAXX010101000'];
            $rfcValido = ! empty($cliente->RFC) && ! in_array(strtoupper(trim($cliente->RFC)), $rfcGenericos);
            $curpValido = ! empty($cliente->CURP);
            $nombreValido = ! empty($nombreCompleto);

            if ($rfcValido && $curpValido) {
                $registros = TbListasNegraCNSF::where('RFC', 'LIKE', '%'.$cliente->RFC.'%')
                    ->where('CURP', 'LIKE', '%'.$cliente->CURP.'%')
                    ->get();
            } elseif ($rfcValido && ! $curpValido) {
                $registros = TbListasNegraCNSF::where('RFC', 'LIKE', '%'.$cliente->RFC.'%')
                    ->get();
            } elseif (! $rfcValido && $curpValido) {
                $registros = TbListasNegraCNSF::where('CURP', 'LIKE', '%'.$cliente->CURP.'%')
                    ->get();
            } elseif ($nombreValido) {
                $registros = TbListasNegraCNSF::whereRaw('LOWER(TRIM(Nombre)) = ?', [strtolower($nombreCompleto)])
                    ->get();
            } else {
                return response()->json([
                    'registrosEncontrados' => 0,
                    'detalleListaBloqueadas' => [],
                ], 200);
            }

            if ($registros->isEmpty()) {
                return response()->json([
                    'registrosEncontrados' => 0,
                    'detalleListaBloqueadas' => [],
                ], 200);
            }

            $registro = $registros->sortByDesc(function ($item) use ($nombreCompleto) {
                $porcentaje = 0;
                similar_text(
                    strtolower(trim($nombreCompleto)),
                    strtolower(trim($item->Nombre ?? '')),
                    $porcentaje
                );

                return $porcentaje;
            })->first();

            $detalle = [
                [
                    'lista' => 'Listas Negra CNSF',
                    'nombreDetectado' => $registro->Nombre ?? '',
                    'IDListaOrigen' => $cliente->CoincideEnListasNegras,
                    'cargo' => $cliente->Cargo ?? '',
                    'PPEActivo' => (bool) $cliente->EsPPEActivo,
                    'clienteAutorizado' => (bool) $cliente->Activo,
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
