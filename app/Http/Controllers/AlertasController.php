<?php

namespace App\Http\Controllers;

use App\Models\TbAlertas;
use App\Models\CatFormaPagos;
use App\Models\TbOperacionesPagos;
use App\Models\TbReporteRegulatorioPLD;
use App\Models\CatMonedas;
use App\Models\Clientes\TbClientes;
use App\Models\TbOperaciones;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatTipoPersona;
use App\Models\Clientes\CatOcupacionesGiros;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AlertasController extends Controller
{
    public function index()
    {
        $clientes = TbClientes::where('Activo', 1)
            ->select('IDCliente', 'Nombre', 'ApellidoPaterno', 'ApellidoMaterno', 'RazonSocial')
            ->orderByRaw("
                CASE
                    WHEN Nombre IS NOT NULL AND Nombre <> ''
                        THEN CONCAT(Nombre, ' ', IFNULL(ApellidoPaterno, ''), ' ', IFNULL(ApellidoMaterno, ''))
                    ELSE RazonSocial
                END
            ")
            ->orderBy('RazonSocial')
            ->get();

        $agentes = TbClientes::where('ckAgente', 1)->get();

        $instrumentos = CatFormaPagos::all();

        $polizas = TbOperaciones::selectRaw('MIN(IDOperacion) as IDOperacion, FolioPoliza')
            ->whereNotNull('FolioPoliza')
            ->where('FolioPoliza', '!=', '');

        if (request()->has('IDCliente') && !empty(request('IDCliente'))) {
            $polizas->where('IDCliente', request('IDCliente'));
        }

        $polizas = $polizas
            ->groupBy('FolioPoliza')
            ->get();

        $monedas = CatMonedas::all();

        return Inertia::render('Alertas/Index', [
            'clientes' => $clientes,
            'agentes' => $agentes,
            'instrumentos' => $instrumentos,
            'monedas' => $monedas
        ]);
    }

    /**
     * Obtiene alertas con paginación y filtrado opcional por rango de fechas.
     */
    public function getAlertas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fechaInicio' => 'nullable|date',
            'fechaFin' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $fechaInicio = $request->input('fechaInicio');
        $fechaFin = $request->input('fechaFin') ?? null;

        // Normaliza formato de fechas y asegura que el rango es válido
        if ($fechaInicio && $fechaFin && $fechaFin < $fechaInicio) {
            return response()->json([
                'fechaFin' => ['La fecha fin no puede ser menor que la fecha inicio.'],
            ], 400);
        }

        $alertas = TbAlertas::query()
            ->when($fechaInicio && $fechaFin, function ($query) use ($fechaInicio, $fechaFin) {
                // Ambos: fecha de inicio y fecha fin
                return $query->whereBetween('FechaDeteccion', [$fechaInicio, $fechaFin]);
            })
            ->when($fechaInicio && !$fechaFin, function ($query) use ($fechaInicio) {
                // Solo fecha de inicio
                return $query->where('FechaDeteccion', '>=', $fechaInicio);
            })
            ->when(!$fechaInicio && $fechaFin, function ($query) use ($fechaFin) {
                // Solo fecha fin
                return $query->where('FechaDeteccion', '<=', $fechaFin);
            })
            ->when($request->filled('folio'), function ($query) use ($request) {
                $searchTerm = $request->input('folio');
                return $query->where(function ($q) use ($searchTerm) {
                    $q->where('Folio', 'like', "%{$searchTerm}%")
                      ->orWhere('Cliente', 'like', "%{$searchTerm}%")
                      ->orWhere('IDCliente', 'like', "%{$searchTerm}%")
                      ->orWhere('Poliza', 'like', "%{$searchTerm}%");
                });
            })
            ->orderBy('FechaDeteccion', 'desc')
            ->orderBy('HoraDeteccion', 'desc')
            ->paginate(20000000);


        return response()->json($alertas);
    }

    /**
     * Obtiene alertas por rango de fechas y las descarga en formato CSV.
     */
    public function downloadAlertasCsvByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fechaInicio' => 'nullable|date',
            'fechaFin' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $fechaInicio = $request->input('fechaInicio');
        $fechaFin = $request->input('fechaFin') ?? null;

        if ($fechaInicio && $fechaFin && $fechaFin < $fechaInicio) {
            return response()->json([
                'fechaFin' => ['La fecha fin no puede ser menor que la fecha inicio.'],
            ], 400);
        }

        $query = TbAlertas::query()
            ->when($fechaInicio, function ($query) use ($fechaInicio, $fechaFin) {
                if ($fechaFin) {
                    return $query->whereBetween('FechaDeteccion', [$fechaInicio, $fechaFin]);
                }
                return $query->where('FechaDeteccion', '>=', $fechaInicio);
            })
            ->when(!$fechaInicio && $fechaFin, function ($query) use ($fechaFin) {
                return $query->where('FechaDeteccion', '<=', $fechaFin);
            })
            ->when($request->filled('folio'), function ($query) use ($request) {
                $searchTerm = $request->input('folio');
                return $query->where(function ($q) use ($searchTerm) {
                    $q->where('Folio', 'like', "%{$searchTerm}%")
                      ->orWhere('Cliente', 'like', "%{$searchTerm}%")
                      ->orWhere('IDCliente', 'like', "%{$searchTerm}%")
                      ->orWhere('Poliza', 'like', "%{$searchTerm}%");
                });
            });

        $alertas = $query->orderBy('FechaDeteccion', 'desc')
                         ->orderBy('HoraDeteccion', 'desc')
                         ->get();

        $fileName = 'alertas_'.($fechaInicio ?? 'todas').'_a_'.($fechaFin ?? 'todas').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($alertas) {
            $file = fopen('php://output', 'w');
            if ($alertas->isNotEmpty()) {
                fputcsv($file, array_keys($alertas->first()->toArray()));
            }
            foreach ($alertas as $alerta) {
                fputcsv($file, $alerta->toArray());
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Actualiza los datos de una alerta existente.
     */
    public function actualizarAlerta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idAlerta' => 'required|integer',
            'instrumento' => 'required|string',
            'patron' => 'required|string', // Se sigue validando para cumplir requisitos de frontend/form, pero se ignorará abajo
            'estatus' => 'required|string|in:Generado,Analizado,Cerrado,Reportado,Enviado',
            'nombre' => 'required|string',
            'noCliente' => 'required|integer',
            'poliza' => 'required|string',
            'agente' => 'required|integer',
            'idMoneda' => 'required',
            'monto' => 'required|numeric',
            'descripcionOperacion' => 'required|string',
            'razones' => 'required|string',
            'evidencias' => 'sometimes|array',
            'evidencias.*' => 'sometimes|file',
        ]);

        $isInertia = $request->header('X-Inertia');

        if ($validator->fails()) {
            if ($isInertia) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->with('error', 'validación fallida');
            } else {
                return response()->json([
                    'message' => 'validación fallida',
                    'errors' => $validator->errors(),
                ], 422);
            }
        }

        try {
            $evidenciasData = [];
            if ($request->hasFile('evidencias')) {
                foreach ($request->file('evidencias') as $file) {
                    $storedPath = $file->store('alertas/evidencias', 'public');
                    $evidenciasData[] = [
                        'path' => $storedPath,
                        'original' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ];
                }
            }

            $alerta = TbAlertas::find($request->input('idAlerta'));
            if (!$alerta) {
                if ($isInertia) {
                    return redirect()->back()->with('error', 'No se encontró la alerta indicada');
                } else {
                    return response()->json([
                        'message' => 'No se encontró la alerta indicada',
                    ], 404);
                }
            }

            $agenteCliente = TbClientes::find($request->input('agente'));
            $agenteNombre = $agenteCliente
                ? (($agenteCliente->RazonSocial && trim($agenteCliente->RazonSocial) !== '')
                    ? $agenteCliente->RazonSocial
                    : trim(implode(' ', array_filter([
                        $agenteCliente->Nombre ?? '',
                        $agenteCliente->ApellidoPaterno ?? '',
                        $agenteCliente->ApellidoMaterno ?? ''
                    ]))))
                : null;

            $horaActual = date('H:i:s');
            $fechaActualYMD = date('Y-m-d');

            $estatus = $request->input('estatus');
            $generarReporte = in_array($estatus, ['Enviado', 'Reportado']);

            // El patrón siempre es "Preocupante" según la instrucción
            $alerta->Patron = "Preocupante";

            $alerta->Folio = $request->input('idAlerta');
            $alerta->IDCliente = $request->input('noCliente');
            $alerta->Cliente = $request->input('nombre');
            $alerta->Poliza = $request->input('poliza');
            $alerta->MontoOperacion = $request->input('monto');
            $alerta->InstrumentoMonetario = $request->input('instrumento');
            $alerta->RFCAgente = $agenteCliente ? ($agenteCliente->RFC ?? null) : null;
            $alerta->Agente = $agenteNombre;
            $alerta->IDMoneda = $request->input('idMoneda');
            $alerta->Estatus = $estatus;
            $alerta->Descripcion = $request->input('descripcionOperacion');
            $alerta->Razones = $request->input('razones');
            if (!empty($evidenciasData)) {
                $alerta->Evidencias = json_encode($evidenciasData);
            }
            $alerta->HoraDeteccion = $horaActual;
            $alerta->FechaOperacion = $fechaActualYMD;
            $alerta->HoraOperacion = $horaActual;

            $alerta->save();

            // Si se debe emitir un reporte al ser "Enviado" o "Reportado"
            if ($generarReporte) {
                // Verificar si ya existe el reporte para esta alerta (opcional: evitar duplicados)
                $existeReporte = \App\Models\TbReporteRegulatorioPLD::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)
                    ->whereIn('Estatus', ['Enviado', 'Reportado'])
                    ->first();

                if (!$existeReporte) {
                    $reporte = new \App\Models\TbReporteRegulatorioPLD();

                    $reporte->IDRegistroAlerta = $alerta->IDRegistroAlerta;
                    $reporte->TipoReporte = "Preocupante"; // Puede ajustarse según lógica
                    $reporte->PeriodoReporte = now()->format('Ym');
                    $reporte->Folio = $alerta->Folio;
                    // Considera llenar los demás campos requeridos en TbReporteRegulatorioPLD
                    $reporte->OrganoSupervisor = null;
                    $reporte->CveSujetoObligado = null;
                    $reporte->Localidad = null;
                    $reporte->Sucursal = null;
                    $reporte->TipoOperacion = "Preocupante"; // Siempre "Preocupante"
                    $reporte->InstrumentoMonetario = $alerta->InstrumentoMonetario;
                    $reporte->NoPoliza = $alerta->Poliza;
                    $reporte->Monto = $alerta->MontoOperacion;
                    $reporte->IDMoneda = $alerta->IDMoneda;
                    $reporte->FechaOperacion = $alerta->FechaOperacion;
                    $reporte->FechaDeteccion = $alerta->FechaDeteccion ?? $fechaActualYMD;
                    $reporte->Nacionalidad = null;
                    $reporte->TipoPersona = null;
                    $reporte->RazonSocial = null;
                    $reporte->Nombre = $alerta->Cliente;
                    $reporte->APaterno = null;
                    $reporte->AMaterno = null;
                    $reporte->RFC = $alerta->RFCAgente;
                    $reporte->CURP = null;
                    $reporte->FechaNacimiento = null;
                    $reporte->Domicilio = null;
                    $reporte->Colonia = null;
                    $reporte->Ciudad = null;
                    $reporte->Telefono = null;
                    $reporte->Ocupacion = null;
                    $reporte->NombreAgente = $alerta->Agente;
                    $reporte->APaternoAgente = null;
                    $reporte->AMaternoAgente = null;
                    $reporte->RFCAgente = $alerta->RFCAgente;
                    $reporte->CURPAgente = null;
                    $reporte->Cuenta = null;
                    $reporte->NoPolizaCuenta = null;
                    $reporte->CveSujetoObl = null;
                    $reporte->NombreTitular = null;
                    $reporte->APaternoTitular = null;
                    $reporte->AMaternoTitular = null;
                    $reporte->Descripcion = $alerta->Descripcion;
                    $reporte->Razon = $alerta->Razones;
                    $reporte->Estatus = $estatus;

                    $reporte->save();
                }
            }

            if ($isInertia) {
                return redirect()->back()->with('success', 'Alerta modificada correctamente');
            } else {
                return response()->json([
                    'message' => 'Alerta modificada correctamente',
                    'alerta' => $alerta,
                ], 200);
            }

        } catch (\Throwable $e) {
            $errorId = (string) \Str::uuid();
            \Log::error('Error al modificar alerta', [
                'error_id' => $errorId,
                'exception' => $e,
            ]);

            $payload = [
                'message' => 'Ocurrió un error al modificar la alerta',
                'error_id' => $errorId,
            ];

            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->map(function ($t) {
                        return [
                            'file' => $t['file'] ?? null,
                            'line' => $t['line'] ?? null,
                            'function' => $t['function'] ?? null,
                            'class' => $t['class'] ?? null,
                        ];
                    })->take(5)->all(),
                ];
            }

            if ($isInertia) {
                return redirect()->back()->with('error', 'Ocurrió un error al modificar la alerta. ID: ' . $errorId);
            } else {
                return response()->json($payload, 500);
            }
        }
    }

    public function editarAlertaDos(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'idAlerta'      => 'required|integer',
            'estatus'       => 'required|string|in:Generado,Analizado,Cerrado,Reportado,Enviado',
            'razones'       => 'required|string',
            'descripcion'   => 'required|string',
            'evidencias'    => 'sometimes|array',
            'evidencias.*'  => 'sometimes|file',
        ]);
        $isInertia = $request->header('X-Inertia');
        if ($validator->fails()) {
            if ($isInertia) {
                return redirect()->back()
                    ->withErrors($validator)
                    ->with('error', 'Validación fallida');
            } else {
                return response()->json([
                    'message' => 'Validación fallida',
                    'errors' => $validator->errors(),
                ], 422);
            }
        }

        try {
            $alerta = \App\Models\TbAlertas::find($request->input('idAlerta'));
            if (!$alerta) {
                if ($isInertia) {
                    return redirect()->back()->with('error', 'No se encontró la alerta indicada');
                } else {
                    return response()->json([
                        'message' => 'No se encontró la alerta indicada',
                    ], 404);
                }
            }

            // Manejo de evidencias (añade a las existentes)
            $evidenciasData = [];
            if ($request->hasFile('evidencias')) {
                // Decodifica las evidencias previas
                $evidenciasPrevias = [];
                if (!empty($alerta->Evidencias)) {
                    $decoded = json_decode($alerta->Evidencias, true);
                    if (is_array($decoded)) {
                        $evidenciasPrevias = $decoded;
                    }
                }
                foreach ($request->file('evidencias') as $file) {
                    $storedPath = $file->store('alertas/evidencias', 'public');
                    $evidenciasData[] = [
                        'path'      => $storedPath,
                        'original'  => $file->getClientOriginalName(),
                        'mime'      => $file->getClientMimeType(),
                        'size'      => $file->getSize(),
                    ];
                }
                $alerta->Evidencias = json_encode(array_merge($evidenciasPrevias, $evidenciasData));
            }

            $alerta->Estatus      = $request->input('estatus');
            $alerta->Razones      = $request->input('razones');
            $alerta->Descripcion  = $request->input('descripcion');
            $alerta->save();

            // Emitir reporte si estatus es "Enviado" o "Reportado"
            $estatus = $request->input('estatus');
            if (in_array($estatus, ['Enviado', 'Reportado'])) {
                // Busca si ya existe un reporte generado para esta alerta con los mismos estatus
                $yaExisteReporte = \App\Models\TbReporteRegulatorioPLD::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)
                    ->whereIn('Estatus', ['Enviado', 'Reportado'])
                    ->first();

                if (!$yaExisteReporte) {
                    $reporte = new \App\Models\TbReporteRegulatorioPLD();

                    $reporte->IDRegistroAlerta   = $alerta->IDRegistroAlerta;
                    $reporte->TipoReporte        = "Preocupante";
                    $reporte->PeriodoReporte     = now()->format('Ym');
                    $reporte->Folio              = $alerta->Folio;
                    $reporte->OrganoSupervisor   = null;
                    $reporte->CveSujetoObligado  = null;
                    $reporte->Localidad          = null;
                    $reporte->Sucursal           = null;
                    $reporte->TipoOperacion      = "Preocupante";
                    $reporte->InstrumentoMonetario = $alerta->InstrumentoMonetario ?? null;
                    $reporte->NoPoliza           = $alerta->Poliza ?? null;
                    $reporte->Monto              = $alerta->MontoOperacion ?? null;
                    $reporte->IDMoneda           = $alerta->IDMoneda ?? null;
                    $reporte->FechaOperacion     = $alerta->FechaOperacion ?? now()->format('Y-m-d');
                    $reporte->FechaDeteccion     = $alerta->FechaDeteccion ?? now()->format('Y-m-d');
                    $reporte->Nacionalidad       = null;
                    $reporte->TipoPersona        = null;
                    $reporte->RazonSocial        = null;
                    $reporte->Nombre             = $alerta->Cliente ?? null;
                    $reporte->APaterno           = null;
                    $reporte->AMaterno           = null;
                    $reporte->RFC                = $alerta->RFCAgente ?? null;
                    $reporte->CURP               = null;
                    $reporte->FechaNacimiento    = null;
                    $reporte->Domicilio          = null;
                    $reporte->Colonia            = null;
                    $reporte->Ciudad             = null;
                    $reporte->Telefono           = null;
                    $reporte->Ocupacion          = null;
                    $reporte->NombreAgente       = $alerta->Agente ?? null;
                    $reporte->APaternoAgente     = null;
                    $reporte->AMaternoAgente     = null;
                    $reporte->RFCAgente          = $alerta->RFCAgente ?? null;
                    $reporte->CURPAgente         = null;
                    $reporte->Cuenta             = null;
                    $reporte->NoPolizaCuenta     = null;
                    $reporte->CveSujetoObl       = null;
                    $reporte->NombreTitular      = null;
                    $reporte->APaternoTitular    = null;
                    $reporte->AMaternoTitular    = null;
                    $reporte->Descripcion        = $alerta->Descripcion;
                    $reporte->Razon              = $alerta->Razones;
                    $reporte->Estatus            = $estatus;

                    $reporte->save();
                }
            }

            if ($isInertia) {
                return redirect()->back()->with('success', 'Alerta modificada correctamente');
            } else {
                return response()->json([
                    'message' => 'Alerta modificada correctamente',
                    'alerta' => $alerta,
                ], 200);
            }
        } catch (\Throwable $e) {
            $errorId = (string) \Str::uuid();
            \Log::error('Error en editarAlertaDos', [
                'error_id' => $errorId,
                'exception' => $e,
            ]);

            $payload = [
                'message' => 'Ocurrió un error al editar la alerta',
                'error_id' => $errorId,
            ];

            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->map(function ($t) {
                        return [
                            'file' => $t['file'] ?? null,
                            'line' => $t['line'] ?? null,
                            'function' => $t['function'] ?? null,
                            'class' => $t['class'] ?? null,
                        ];
                    })->take(5)->all(),
                ];
            }

            if ($isInertia) {
                return redirect()->back()->with('error', 'Ocurrió un error al editar la alerta. ID: ' . $errorId);
            } else {
                return response()->json($payload, 500);
            }
        }
    }

    /**
     * Elimina una evidencia específica asociada a una alerta.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * Espera:
     *   - idAlerta: ID de la alerta
     *   - path: path de la evidencia a eliminar (unique dentro del array de evidencias)
     */
    public function eliminarEvidenciaDeAlerta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idAlerta' => 'required|integer',
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with('error', 'Validación fallida');
        }

        $alerta = TbAlertas::find($request->input('idAlerta'));
        if (!$alerta) {
            return back()
                ->with('error', 'No se encontró la alerta indicada.');
        }

        $pathToDelete = $request->input('path');
        $evidenciasRaw = $alerta->Evidencias;
        $evidencias = [];

        if ($evidenciasRaw) {
            $evidencias = json_decode($evidenciasRaw, true);
            if (!is_array($evidencias)) {
                $evidencias = [];
            }
        }

        // Buscar la evidencia y eliminarla del array
        $updatedEvidencias = [];
        $found = false;
        foreach ($evidencias as $ev) {
            if (isset($ev['path']) && $ev['path'] === $pathToDelete) {
                // Eliminar el archivo del storage si existe
                if (Storage::disk('public')->exists($ev['path'])) {
                    Storage::disk('public')->delete($ev['path']);
                }
                $found = true;
                continue;
            }
            $updatedEvidencias[] = $ev;
        }

        if (!$found) {
            return back()
                ->with('error', 'No se encontró la evidencia a eliminar en la alerta.');
        }

        // Actualizar en DB
        $alerta->Evidencias = !empty($updatedEvidencias) ? json_encode($updatedEvidencias) : null;
        $alerta->save();

        // Como la ruta donde se ejecuta es la misma a la que se redirige, se fuerza un refresco con el mensaje de éxito
        return back()->with('success', 'Evidencia eliminada correctamente');
    }

    public function getPolizasPorCliente($idCliente)
    {
        $polizas = TbOperaciones::query()
            ->where('IDCliente', $idCliente)
            ->whereNotNull('FolioPoliza')
            ->where('FolioPoliza', '!=', '')
            ->select('FolioPoliza')
            ->distinct()
            ->orderBy('FolioPoliza')
            ->get()
            ->map(function ($op) {
                return [
                    'value' => (string) $op->FolioPoliza,
                    'label' => (string) $op->FolioPoliza,
                ];
            });

        return response()->json([
            'polizas' => $polizas,
        ]);
    }

    public function detalleAlerta($idAlerta)
    {
        $alerta = TbAlertas::find($idAlerta);

        if (!$alerta) {
            $operacion = null;
            $reportes = null;
            $cliente = null;
            $tipoPersona = null;
        } else {
            $operacion = TbOperaciones::with('pagos')->find($alerta->IDOperacion);
            $reportes = TbReporteRegulatorioPLD::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)->get();
            if ($reportes->isEmpty()) {
                $reportes = null;
            }

            $cliente = null;
            $tipoPersona = null;

            if (!is_null($alerta->IDCliente)) {
                // Obtener cliente con sus domicilios y su tipo de persona
                $cliente = TbClientes::with(['domicilios', 'tipoPersona'])->find($alerta->IDCliente);
                if ($cliente && $cliente->tipoPersona) {
                    $tipoPersona = $cliente->tipoPersona; // Es una instancia de CatTipoPersona
                }
            }
        }

        // return json_encode([
        //     'alerta' => $alerta,
        //     'cliente' => $cliente,
        //     'operacion' => $operacion,
        //     'reportes' => $reportes,
        //     'tipoPersona' => $tipoPersona,
        // ]);

        return inertia('Alertas/Detalle', [
            'alerta' => $alerta,
            'cliente' => $cliente,
            'operacion' => $operacion,
            'reportes' => $reportes,
            'tipoPersona' => $tipoPersona,
        ]);
    }
}
