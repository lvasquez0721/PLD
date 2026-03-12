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

        // Obtener solo las pólizas que correspondan al cliente (si aplica filtro)
        // Se asume que el cliente se puede identificar por un parámetro request('IDCliente')
        $queryPolizas = TbOperaciones::selectRaw('MIN(IDOperacion) as IDOperacion, FolioPoliza')
            ->whereNotNull('FolioPoliza')
            ->where('FolioPoliza', '!=', '');

        if (request()->has('IDCliente') && !empty(request('IDCliente'))) {
            $queryPolizas->where('IDCliente', request('IDCliente'));
        }

        $polizas = $queryPolizas
            ->groupBy('FolioPoliza')
            ->get();

        // dd($polizas);
        $monedas = CatMonedas::all();


        return Inertia::render('Alertas/Index', [
            'clientes' => $clientes,
            'agentes' => $agentes,
            'instrumentos' => $instrumentos,
            'monedas' => $monedas,
        ]);
    }


    /**
     * Obtiene alertas por rango de fechas.
     */
    public function getAlertasByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date|after_or_equal:fechaInicio',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $fechaInicio = $request->input('fechaInicio');
        $fechaFin = $request->input('fechaFin');

        $alertas = TbAlertas::whereBetween('FechaDeteccion', [$fechaInicio, $fechaFin])
            ->get();

        return response()->json($alertas);
    }

    /**
     * Obtiene alertas por rango de fechas y las descarga en formato CSV.
     */
    public function downloadAlertasCsvByDateRange(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fechaInicio' => 'required|date',
            'fechaFin' => 'required|date|after_or_equal:fechaInicio',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $fechaInicio = $request->input('fechaInicio');
        $fechaFin = $request->input('fechaFin');

        $alertas = TbAlertas::whereBetween('FechaDeteccion', [$fechaInicio, $fechaFin])
            ->get();

        $fileName = 'alertas_'.$fechaInicio.'_a_'.$fechaFin.'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($alertas) {
            $file = fopen('php://output', 'w');
            fputcsv($file, array_keys($alertas->first()->toArray())); // Add CSV headers

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
            'patron' => 'required|string',
            'estatus' => 'required|string|in:Generado,Analizado,Cerrado,Reportado,Enviado',
            'nombre' => 'required|string',
            'noCliente' => 'required|integer',
            'poliza' => 'required|string',
            'agente' => 'required|integer',
            'idMoneda' => 'required', // Puede ser string, integer o nullable, depende de la migración
            'monto' => 'required|numeric',
            'descripcionOperacion' => 'required|string',
            'razones' => 'required|string',
            'evidencias' => 'sometimes|array',
            'evidencias.*' => 'sometimes|file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'validación fallida',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Procesar evidencias si existen:
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
                return response()->json([
                    'message' => 'No se encontró la alerta indicada',
                ], 404);
            }

            // Buscar agente
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

            // Definir la hora y fecha actuales para los campos correspondientes
            $horaActual = date('H:i:s');
            $fechaActualYMD = date('Y-m-d');

            // Actualizar datos
            $alerta->Folio = $request->input('idAlerta');
            $alerta->Patron = $request->input('patron');
            $alerta->IDCliente = $request->input('noCliente');
            $alerta->Cliente = $request->input('nombre');
            $alerta->Poliza = $request->input('poliza');
            $alerta->MontoOperacion = $request->input('monto');
            $alerta->InstrumentoMonetario = $request->input('instrumento');
            $alerta->RFCAgente = $agenteCliente ? ($agenteCliente->RFC ?? null) : null;
            $alerta->Agente = $agenteNombre;
            $alerta->IDMoneda = $request->input('idMoneda'); // <-- Incluir IDMoneda
            $alerta->Estatus = $request->input('estatus');
            $alerta->Descripcion = $request->input('descripcionOperacion');
            $alerta->Razones = $request->input('razones');
            if (!empty($evidenciasData)) {
                $alerta->Evidencias = json_encode($evidenciasData);
            }
            $alerta->HoraDeteccion = $horaActual;
            $alerta->FechaOperacion = $fechaActualYMD;
            $alerta->HoraOperacion = $horaActual;

            $alerta->save();

            return response()->json([
                'message' => 'Alerta modificada correctamente',
                'alerta' => $alerta,
            ], 200);

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

            return response()->json($payload, 500);
        }
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
        } else {
            // Eager load pagos en la operación
            $operacion = TbOperaciones::with('pagos')->find($alerta->IDOperacion);

            // Revisar si tiene reporte(s) regulatorio(s) asociado(s)
            $reportes = TbReporteRegulatorioPLD::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)->get();
            if ($reportes->isEmpty()) {
                $reportes = null;
            }

            // Obtener el cliente
            $cliente = null;
            if (!is_null($alerta->IDCliente)) {
                $cliente = TbClientes::find($alerta->IDCliente);
            }
        }

        // return json_encode([
        //     'alerta' => $alerta,
        //     'cliente' => $cliente,
        //     'operacion' => $operacion,
        //     'reportes' => $reportes,
        // ]);

        return inertia('Alertas/Detalle', [
            'alerta' => $alerta,
            'cliente' => $cliente,
            'operacion' => $operacion,
            'reportes' => $reportes,
        ]);
    }
}
