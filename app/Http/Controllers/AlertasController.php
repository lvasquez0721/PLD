<?php

namespace App\Http\Controllers;

use App\Models\TbAlertas;
use App\Models\CatFormaPagos;
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

    public function emitirReporteAlerta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idAlerta' => 'required|string',
            'instrumento' => 'required|string',
            'patron' => 'required|string',
            'estatus' => 'required|string|in:Generado,Analizado,Cerrado,Reportado,Enviado',
            'nombre' => 'required|string',
            'noCliente' => 'required|string',
            'poliza' => 'required|string',
            'agente' => 'required|integer',
            'monto' => 'required|numeric',
            'descripcionOperacion' => 'required|string',
            'razones' => 'required|string',
            'evidencias' => 'required|array',
            'evidencias.*' => 'required|file',
            'IDMoneda' => 'nullable|string',
            'IDTipoOperacion' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'validación fallida',
                'errors' => $validator->errors(),
            ], 422);
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

            $folio = $request->input('idAlerta');

            $cliente = TbClientes::find($request->input('noCliente'));
            if (!$cliente) {
                return response()->json([
                    'message' => 'validación fallida',
                    'errors' => ['noCliente' => ['Cliente no encontrado']],
                ], 422);
            }

            $IDNacionalidad = $cliente->IDNacionalidad;
            $nacionalidad = $IDNacionalidad ? CatNacionalidad::find($IDNacionalidad) : null;

            $IDTipoPersona = $cliente->IDTipoPersona;
            $tipoPersona = $IDTipoPersona ? CatTipoPersona::find($IDTipoPersona) : null;

            $dom = $cliente->domicilios()->first();
            $domicilio = $dom
                ? trim(
                    ($dom->Calle ?? '') . ' ' .
                    ($dom->NoExterior ?? '') .
                    (empty($dom->NoInterior) ? '' : ' Int ' . $dom->NoInterior) . ', ' .
                    ($dom->Colonia ?? '') . ', ' .
                    ($dom->CP ?? '') . ', ' .
                    ($dom->Municipio ?? '') . ', ' .
                    ($dom->Localidad ?? '')
                )
                : '';

            $ocupacion = CatOcupacionesGiros::find($cliente->IDOcupacionGiro);

            $agenteCliente = TbClientes::find($request->input('agente'));
            if (!$agenteCliente) {
                return response()->json([
                    'message' => 'validación fallida',
                    'errors' => ['agente' => ['Agente no encontrado']],
                ], 422);
            }
            $agenteNombre = ($agenteCliente->RazonSocial && trim($agenteCliente->RazonSocial) !== '')
                ? $agenteCliente->RazonSocial
                : trim(($agenteCliente->Nombre ?? '') . ' ' . ($agenteCliente->ApellidoPaterno ?? '') . ' ' . ($agenteCliente->ApellidoMaterno ?? ''));

            $fechaActualYM = date('Y/m');
            $horaActual = date('H:i:s');
            $fechaActualYMD = date('Y-m-d');

            $alerta = TbAlertas::find($request->input('idAlerta'));
            if (!$alerta) {
                return response()->json([
                    'message' => 'No se encontró la alerta indicada',
                ], 404);
            }

            $alerta->update([
                'Folio' => $folio,
                'Patron' => 'Preocupante',
                'IDCliente' => $request->input('noCliente'),
                'Cliente' => $request->input('nombre'),
                'Poliza' => $request->input('poliza'),
                'MontoOperacion' => $request->input('monto'),
                'InstrumentoMonetario' => $request->input('instrumento'),
                'RFCAgente' => $agenteCliente->RFC ?? null,
                'Agente' => $agenteNombre,
                'Estatus' => $request->input('estatus'),
                'Descripcion' => $request->input('descripcionOperacion'),
                'Razones' => $request->input('razones'),
                'Evidencias' => json_encode($evidenciasData),
                'HoraDeteccion' => $horaActual,
                'FechaOperacion' => $fechaActualYMD,
                'HoraOperacion' => $horaActual,
            ]);

            $idMoneda = $request->input('IDMoneda');
            $idMoneda = is_numeric($idMoneda) ? (int) $idMoneda : null;
            $idTipoOperacion = $request->input('IDTipoOperacion');
            $idTipoOperacion = is_numeric($idTipoOperacion) ? (int) $idTipoOperacion : null;
            $montoVal = is_numeric($request->input('monto')) ? (float) $request->input('monto') : 0.0;

            $reporte = TbReporteRegulatorioPLD::create([
                'IDRegistroAlerta' => $alerta->IDRegistroAlerta,
                'TipoReporte' => $alerta->Patron,
                'PeriodoReporte' => $fechaActualYM,
                'Folio' => $folio,
                'OrganoSupervisor' => '001003',
                'CveSujetoObligado' => '022123',
                'Localidad' => '03342009',
                'Sucursal' => 0,
                'TipoOperacion' => '',
                'InstrumentoMonetario' => $alerta->InstrumentoMonetario,
                'NoPoliza' => $request->input('poliza'),
                'Monto' => $montoVal,
                'IDMoneda' => $idMoneda,
                'Nacionalidad' => $nacionalidad->Nacionalidad ?? null,
                'TipoPersona' => $tipoPersona->TipoPersona ?? null,
                'RazonSocial' => $cliente->RazonSocial,
                'Nombre' => $cliente->Nombre,
                'APaterno' => $cliente->ApellidoPaterno,
                'AMaterno' => $cliente->ApellidoMaterno,
                'RFC' => $cliente->RFC,
                'CURP' => $cliente->CURP,
                'FechaNacimiento' => $cliente->FechaNacimiento,
                'Domicilio' => $domicilio,
                'Colonia' => $dom->Colonia ?? null,
                'Ciudad' => $dom->Ciudad ?? null,
                'Telefono' => $dom->Telefono ?? null,
                'Ocupacion' => $ocupacion->OcupacionGiro ?? null,
                'NombreAgente'=> $agenteCliente->Nombre,
                'APaternoAgente'=> $agenteCliente->ApellidoPaterno,
                'AMaternoAgente'=> $agenteCliente->ApellidoMaterno,
                'RFCAgente' => $agenteCliente->RFCAgente ?? null,
                'CURPAgente' => $agenteCliente->CURPAgente ?? null,
                'Cuenta' => '',
                'NoPolizaCuenta' => '',
                'CveSujetoObl' => '',
                'NombreTitular' => '',
                'APaternoTitular' => '',
                'AMaternoTitular' => '',
                'Descripcion' => $request->descripcionOperacion,
                'Razon' => $request->razones,
                'Estatus' => $request->estatus,
                'IDTipoReporte' => 3,
                'IDTipoOperacion' => $idTipoOperacion,
            ]);

            return response()->json([
                'message' => 'Reporte emitido correctamente',
                'alerta' => $alerta,
            ], 201);
        } catch (\Throwable $e) {
            $errorId = (string) Str::uuid();
            Log::error('Error al emitir reporte de alerta', [
                'error_id' => $errorId,
                'exception' => $e,
            ]);

            $payload = [
                'message' => 'Ocurrió un error al emitir el reporte',
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
}
