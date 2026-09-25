<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\CatParametriaPLD;
use App\Models\Clientes\TbClientes;
use App\Models\LogOperacionesPagos;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbOperacionesBeneficiarios;
use App\Models\TbOperacionesPagos;
use App\Models\TbPagosAlertas;
use App\Services\NotificacionCumplimientoService;
use App\Services\PLD\AnalisisPagosService;
use App\Services\PLD\ReportesRegulatorios;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperacionesController extends Controller
{
    // -------------
    // OPERACION
    public function insertarOperacion(Request $request)
    {
        try {
            // Reglas de validación base
            $rules = [
                'IDCliente' => 'required|integer',
                'FolioPoliza' => 'required|string|max:40',
                'FolioEndoso' => 'nullable|string|max:40',
                'FechaEmision' => 'required|date',
                'PrimaTotal' => 'required|numeric',
                'IDMoneda' => 'required|string',
                'FechaInicioVigencia' => 'required|date',
                'FechaFinVigencia' => 'required|date',
                'GastosEmision' => 'required|numeric',
                // Datos del agente ahora completamente opcionales:
                'RFCAgente' => 'nullable|string|max:13',
                'CURPAgente' => 'nullable|string|max:18',
                'NombreAgente' => 'nullable|string|max:100',
                'APaternoAgente' => 'nullable|string|max:100',
                'AMaternoAgente' => 'nullable|string|max:100',
                'RazonSocialAgente' => 'nullable|string|max:300',
                'IDFormaPago' => 'nullable|string',
                'EsEndosoCancelacion' => 'required|boolean',
                'PagaTercero' => 'required|boolean',
                'EsquemaDePago' => 'nullable|string',
                'DetalleBeneficiarios' => 'nullable|array',
                'DetalleBeneficiarios.*.RFC' => 'nullable|string|max:13',
                'DetalleBeneficiarios.*.CURP' => 'nullable|string|max:18',
                'DetalleBeneficiarios.*.nombre' => 'nullable|string|max:100',
                'DetalleBeneficiarios.*.apellidoPaterno' => 'nullable|string|max:100',
                'DetalleBeneficiarios.*.apellidoMaterno' => 'nullable|string|max:100',
                'DetalleBeneficiarios.*.razonSocial' => 'nullable|string|max:300',
                'DetalleBeneficiarios.*.preferente' => 'nullable|boolean',
                'DetalleBeneficiarios.*.porcentajeParticipacion' => 'nullable|numeric|min:0|max:100',
            ];

            // IMPORTANTE: No agregamos ninguna required_without ni required_if para los datos de agente.
            // Los datos de agente pueden omitirse completamente en el request.

            try {
                $validatedData = $request->validate($rules);
            } catch (\Illuminate\Validation\ValidationException $e) {
                // Respondemos normalmente el error de validación si ocurre por otras reglas
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'Error de validación',
                    'detalles' => $e->errors(),
                ], 422);
            }
        } catch (\Exception $e) {
            return response()->json([
                'codigoError' => 500,
                'error' => 'Error inesperado durante la validación',
                'detalles' => $e->getMessage(),
            ], 500);
        }

        // Validar si el cliente está activo
        $cliente = TbClientes::find($validatedData['IDCliente']);
        if (! $cliente || ! $cliente->Activo) {
            return response()->json([
                'codigoError' => 403,
                'error' => 'El cliente no se encuentra activo por coincidencias en listas.',
            ], 403);
        }

        // Validación unicidad FolioPoliza / FolioEndoso (aditiva, sin romper formato)
        // - FolioEndoso vacío (null/'') = emisión: solo 1 emisión por FolioPoliza
        // - Con FolioEndoso = endoso: par (FolioPoliza, FolioEndoso) único dentro de la póliza
        $folioPolizaNorm = trim((string) $validatedData['FolioPoliza']);
        $folioEndosoTmp = $validatedData['FolioEndoso'] ?? null;
        $folioEndosoNorm = is_string($folioEndosoTmp) ? trim($folioEndosoTmp) : $folioEndosoTmp;
        if ($folioEndosoNorm === '') {
            $folioEndosoNorm = null;
        }
        $validatedData['FolioPoliza'] = $folioPolizaNorm;
        $validatedData['FolioEndoso'] = $folioEndosoNorm;
        $esEmision = empty($folioEndosoNorm);

        if ($esEmision) {
            $existeEmision = TbOperaciones::where('FolioPoliza', $folioPolizaNorm)
                ->where(function ($q) {
                    $q->whereNull('FolioEndoso')->orWhere('FolioEndoso', '');
                })->exists();
            if ($existeEmision) {
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'Ya existe una emisión con FolioPoliza "'.$folioPolizaNorm.'". No se permite duplicar FolioPoliza sin FolioEndoso.',
                    'detalles' => ['FolioPoliza' => $folioPolizaNorm],
                ], 422);
            }
        } else {
            $existePar = TbOperaciones::where('FolioPoliza', $folioPolizaNorm)
                ->where('FolioEndoso', $folioEndosoNorm)->exists();
            if ($existePar) {
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'Ya existe un endoso con FolioPoliza "'.$folioPolizaNorm.'" y FolioEndoso "'.$folioEndosoNorm.'". La combinación debe ser única dentro de la póliza.',
                    'detalles' => ['FolioPoliza' => $folioPolizaNorm, 'FolioEndoso' => $folioEndosoNorm],
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            $operacion = new TbOperaciones;
            $operacion->IDCliente = $validatedData['IDCliente'];
            $operacion->FolioPoliza = $validatedData['FolioPoliza'];
            $operacion->FolioEndoso = $validatedData['FolioEndoso'] ?? null;
            $operacion->FechaEmision = $validatedData['FechaEmision'];
            $operacion->PrimaTotal = $validatedData['PrimaTotal'];
            $operacion->GastosEmision = $validatedData['GastosEmision'];
            $operacion->RFCAgente = $validatedData['RFCAgente'] ?? null;
            $operacion->CURPAgente = $validatedData['CURPAgente'] ?? null;

            // Asignar datos del agente si vienen; si no, dejar en null
            $operacion->NombreAgente = $validatedData['NombreAgente'] ?? null;
            $operacion->APaternoAgente = $validatedData['APaternoAgente'] ?? null;
            $operacion->AMaternoAgente = $validatedData['AMaternoAgente'] ?? null;
            $operacion->RazonSocialAgente = $validatedData['RazonSocialAgente'] ?? null;

            $operacion->IDMoneda = $validatedData['IDMoneda'];
            $operacion->FechaInicioVigencia = $validatedData['FechaInicioVigencia'];
            $operacion->FechaFinVigencia = $validatedData['FechaFinVigencia'];
            $operacion->tipoDocumento = $request->tipoDocumento ?? null;
            $operacion->IDFormaPago = $request->IDFormaPago ?? null;
            $operacion->PagaTercero = $validatedData['PagaTercero'] ?? null;
            $operacion->EsquemaDePago = $validatedData['EsquemaDePago'] ?? null;
            $operacion->EsEndosoCancelacion = $validatedData['EsEndosoCancelacion'] ?? null;
            $operacion->save();

            $alertasNuevas = [];
            $alertaGenerada = false;
            $pagosAnulados = 0;
            $motivoCancelacion = null;

            if ($operacion->EsEndosoCancelacion) {
                $evaluacion = $this->evaluarReglasCancelacion($operacion);

                $motivoCancelacion = $evaluacion['motivo'] ?? null;

                if ($evaluacion['generarAlerta']) {
                    $alertaData = [
                        'patron' => AnalisisPagosService::PATRON_CANCELACION,
                        'descripcion' => 'Operación de endoso de cancelación detectada',
                        'razones' => $evaluacion['razones'] ?? 'La operación corresponde a un endoso de cancelación de póliza',
                    ];
                    $evidencias = [
                        'tipo' => 'EndosoCancelacion',
                        'operacion_id' => $operacion->IDOperacion,
                        'folio_poliza' => $operacion->FolioPoliza,
                        'folio_endoso' => $operacion->FolioEndoso,
                        'operacion_base_id' => $evaluacion['base']->IDOperacion ?? null,
                        'ultimo_pago_fecha' => $evaluacion['ultimoPago']->FechaPago ?? null,
                        'dias_desde_ultimo_pago' => $evaluacion['dias'] ?? null,
                    ];
                    $alertaN = $this->crearAlerta($operacion, $cliente, $alertaData, $evidencias, [], null, $request->IDFormaPago);
                    $alertasNuevas[] = $alertaN;
                    $alertaGenerada = true;
                } else {
                    \Log::info('[Cancelacion] Alerta omitida', [
                        'folio_poliza' => $operacion->FolioPoliza,
                        'operacion_id' => $operacion->IDOperacion,
                        'motivo' => $evaluacion['motivo'] ?? 'Sin motivo',
                    ]);
                }

                // 3.5 Siempre anula pagos de la base si existe y tiene pagos
                if (! empty($evaluacion['base'])) {
                    $pagosAnulados = $this->anularPagosBase($evaluacion['base']);
                    \Log::info('[Cancelacion] Pagos anulados', [
                        'folio_poliza' => $operacion->FolioPoliza,
                        'operacion_endoso_id' => $operacion->IDOperacion,
                        'operacion_base_id' => $evaluacion['base']->IDOperacion,
                        'pagos_anulados' => $pagosAnulados,
                        'alerta_generada' => $alertaGenerada,
                    ]);
                }
            }

            // Alerta PPE: 1 por operación, incluso en endosos de cancelación (3.5)
            $existePPEOperacion = TbAlertas::where('Patron', AnalisisPagosService::PATRON_PPE)
                ->where('IDOperacion', $operacion->IDOperacion)
                ->exists();
            $alertaPPEGenerada = false;
            if (! $existePPEOperacion && $cliente && $cliente->EsPPEActivo) {
                $alertaDataPPE = [
                    'patron' => AnalisisPagosService::PATRON_PPE,
                    'descripcion' => 'Persona Políticamente Expuesta',
                    'razones' => 'Operación realizada por cliente PPE (IDCliente '.$cliente->IDCliente.')',
                ];
                $evidenciasPPE = [
                    'tipo' => 'PPE_Operacion',
                    'operacion_id' => $operacion->IDOperacion,
                    'cliente_id' => $cliente->IDCliente,
                    'es_ppe' => true,
                ];
                $alertaPPE = $this->crearAlerta($operacion, $cliente, $alertaDataPPE, $evidenciasPPE, [], null, $request->IDFormaPago);
                $alertasNuevas[] = $alertaPPE;
                $alertaPPEGenerada = true;
                \Log::info('[PPE] Alerta PPE generada por operación', [
                    'operacion_id' => $operacion->IDOperacion,
                    'cliente_id' => $cliente->IDCliente,
                ]);
            }

            $beneficiarios = $validatedData['DetalleBeneficiarios'] ?? [];
            if (! empty($beneficiarios) && is_array($beneficiarios)) {
                foreach ($beneficiarios as $beneficiario) {
                    $beneficiarioModel = new TbOperacionesBeneficiarios;
                    $beneficiarioModel->IDOperacion = $operacion->IDOperacion;
                    $beneficiarioModel->RFCBeneficiario = $beneficiario['RFC'] ?? null;
                    $beneficiarioModel->CURPBeneficiario = $beneficiario['CURP'] ?? null;
                    $beneficiarioModel->NombreBeneficiario = $beneficiario['nombre'] ?? null;
                    $beneficiarioModel->APaternoBeneficiario = $beneficiario['apellidoPaterno'] ?? null;
                    $beneficiarioModel->AMaternoBeneficiario = $beneficiario['apellidoMaterno'] ?? null;
                    $beneficiarioModel->RazonSocialBeneficiario = $beneficiario['razonSocial'] ?? null;
                    $beneficiarioModel->Preferente = $beneficiario['preferente'] ?? null;
                    $beneficiarioModel->PorcentajeParticipacion = $beneficiario['porcentajeParticipacion'] ?? null;
                    $beneficiarioModel->save();
                }
            }

            DB::commit();

            // Envío consolidado post-commit de alertas generadas en esta operación
            if (! empty($alertasNuevas)) {
                try {
                    NotificacionCumplimientoService::enviarAlertaConsolidada($alertasNuevas, $operacion, $cliente);
                } catch (\Throwable $e) {
                    \Log::error('[Notificacion] Error enviando correo consolidado operación: '.$e->getMessage());
                }
            }

            $mensajeExito = 'Operación ingresada exitosamente';
            if ($operacion->EsEndosoCancelacion) {
                if ($alertaGenerada) {
                    $mensajeExito .= ' - Alerta de cancelación generada';
                } else {
                    $mensajeExito .= ' - Alerta de cancelación omitida: '.$motivoCancelacion;
                }
                if ($pagosAnulados > 0) {
                    $mensajeExito .= " - {$pagosAnulados} pago(s) anulado(s) de la operación base";
                }
            }
            if ($alertaPPEGenerada) {
                $mensajeExito .= ' - Alerta PPE generada';
            }
            if ($cliente->CoincideEnListasNegras) {
                $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
            }

            return response()->json([
                'codigoError' => 0,
                'error' => $mensajeExito,
                'IDOperacion' => $operacion->IDOperacion,
                'alertaCancelacionGenerada' => $alertaGenerada,
                'pagosAnulados' => $pagosAnulados,
                'alertaPPEGenerada' => $alertaPPEGenerada,
            ], 201);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            // Violación de índice único (race condition) -> mapear a 422 mismo formato, sin introducir 409
            $msg = $e->getMessage();
            if (str_contains($msg, 'uniq_poliza_endoso_scoped') || $e->getCode() === '23000') {
                // Determinar si fue emisión o endoso por datos normalizados
                $isEmisionDup = empty($validatedData['FolioEndoso'] ?? null);
                if ($isEmisionDup) {
                    return response()->json([
                        'codigoError' => 422,
                        'error' => 'Ya existe una emisión con FolioPoliza "'.$validatedData['FolioPoliza'].'". No se permite duplicar FolioPoliza sin FolioEndoso.',
                        'detalles' => ['FolioPoliza' => $validatedData['FolioPoliza']],
                    ], 422);
                }

                return response()->json([
                    'codigoError' => 422,
                    'error' => 'Ya existe un endoso con FolioPoliza "'.$validatedData['FolioPoliza'].'" y FolioEndoso "'.$validatedData['FolioEndoso'].'". La combinación debe ser única dentro de la póliza.',
                    'detalles' => ['FolioPoliza' => $validatedData['FolioPoliza'], 'FolioEndoso' => $validatedData['FolioEndoso']],
                ], 422);
            }

            return response()->json([
                'codigoError' => 500,
                'error' => 'Error al insertar la operación o beneficiarios',
                'detalles' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'codigoError' => 500,
                'error' => 'Error al insertar la operación o beneficiarios',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------
    // PAGO
    public function insertarOperacionPago(Request $request)
    {
        try {
            try {
                $request->validate([
                    'montoPagado' => 'required|numeric',
                    'IDFormaPago' => 'required|string',
                    'FechaPago' => 'required|date',
                    'detallePagos' => 'required|array|min:1',
                    'detallePagos.*.IDMoneda' => 'required|string',
                    'detallePagos.*.TipoCambio' => 'required|numeric',
                    'detallePagos.*.IDCliente' => 'required|integer',
                    'detallePagos.*.PagaTercero' => 'required|boolean',
                    'detallePagos.*.AvisoDeCobro' => 'nullable|string',
                    'detallePagos.*.IDOperacion' => 'required|integer',
                    'detallePagos.*.folioPoliza' => 'nullable|string|max:50',
                    'detallePagos.*.folioEndoso' => 'nullable|string|max:50',
                    'detallePagos.*.detalleMontoPagado' => 'required|numeric',
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'Error de validación',
                    'detalles' => $e->errors(),
                ], 422);
            } catch (\Exception $e) {
                return response()->json([
                    'codigoError' => 500,
                    'error' => 'Error inesperado durante la validación',
                    'detalles' => $e->getMessage(),
                ], 500);
            }

            if (! empty($request->IDFormaPago)) {
                $formaPago = \App\Models\CatFormaPagos::where('IDFormaPago', $request->IDFormaPago)->first();
                if (! $formaPago) {
                    return response()->json([
                        'codigoError' => 422,
                        'error' => 'El IDFormaPago proporcionado no existe en el catálogo de formas de pago.',
                        'detalles' => ['IDFormaPago' => ['El valor proporcionado no es válido.']],
                    ], 422);
                }
            }

            $idsCliente = array_unique(collect($request->detallePagos)->pluck('IDCliente')->toArray());
            $clientes = TbClientes::whereIn('IDCliente', $idsCliente)->get()->keyBy('IDCliente');

            foreach ($idsCliente as $idCliente) {
                $clienteItem = $clientes->get($idCliente);
                if (! $clienteItem || ! $clienteItem->Activo) {
                    return response()->json([
                        'codigoError' => 403,
                        'error' => "El cliente ID {$idCliente} no se encuentra activo por coincidencias en listas.",
                    ], 403);
                }
            }

            $sumaDetalles = collect($request->detallePagos)->sum(fn ($d) => (float) $d['detalleMontoPagado']);
            if (bccomp((string) $sumaDetalles, (string) $request->montoPagado, 2) !== 0) {
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'La suma de los campos detalleMontoPagado debe ser igual al campo montoPagado.',
                ], 422);
            }

            // Agrupar detalles por IDOperacion
            $detalleAgrupado = [];
            foreach ($request->detallePagos as $detalle) {
                $detalleAgrupado[$detalle['IDOperacion']][] = $detalle;
            }

            $conversionMoneda = ['MXN' => 1, 'USD' => 2];
            $analisisService = new AnalisisPagosService;
            $pagosResultado = [];
            $alertasConsolidadas = [];

            DB::beginTransaction();

            foreach ($detalleAgrupado as $idOperacion => $detalles) {
                $operacion = TbOperaciones::find($idOperacion);
                if (! $operacion) {
                    DB::rollBack();

                    return response()->json([
                        'codigoError' => 404,
                        'error' => "No se encontró la operación con IDOperacion: {$idOperacion}.",
                    ], 404);
                }

                $clienteAnalisis = $clientes->get($detalles[0]['IDCliente']);

                $montoTotalPagado = TbOperacionesPagos::where('IDOperacion', $idOperacion)->sum('Monto');
                $nuevoPagoTotal = array_sum(array_column($detalles, 'detalleMontoPagado'));
                $primaTotalOperacion = $operacion->PrimaTotal;
                $totalTrasEstaPeticion = bcadd((string) $montoTotalPagado, (string) $nuevoPagoTotal, 2);

                if (bccomp((string) $primaTotalOperacion, '0', 2) != 0) {
                    $primaAbs = (string) abs($primaTotalOperacion);

                    if (bccomp((string) abs($montoTotalPagado), $primaAbs, 2) >= 0) {
                        DB::rollBack();

                        return response()->json([
                            'codigoError' => 1,
                            'error' => 'La póliza / endoso ya se encuentra pagada en su totalidad',
                            'IDOperacion' => $idOperacion,
                        ], 200);
                    }

                    if (bccomp((string) abs($totalTrasEstaPeticion), $primaAbs, 2) > 0) {
                        DB::rollBack();

                        return response()->json([
                            'codigoError' => 1,
                            'error' => 'No se permite rebasar el pago total de la póliza / endoso',
                            'IDOperacion' => $idOperacion,
                        ], 200);
                    }
                }

                foreach ($detalles as $detalle) {
                    $pago = new TbOperacionesPagos;
                    $pago->IDOperacion = $idOperacion;
                    $pago->IDCliente = $detalle['IDCliente'];
                    $pago->Monto = $detalle['detalleMontoPagado'];
                    $pago->IDMoneda = $detalle['IDMoneda'];
                    $pago->IDFormaPago = $request->IDFormaPago ?? null;
                    $pago->TipoCambio = $detalle['TipoCambio'];
                    $pago->FechaPago = $request->FechaPago;
                    $pago->PagaTercero = $detalle['PagaTercero'];
                    $pago->AvisoDeCobro = $detalle['AvisoDeCobro'] ?? null;
                    $pago->folioPoliza = $detalle['folioPoliza'] ?? null;
                    $pago->folioEndoso = $detalle['folioEndoso'] ?? null;

                    try {
                        $pago->save();
                    } catch (\Exception $e) {
                        DB::rollBack();

                        return response()->json([
                            'codigoError' => 500,
                            'error' => 'Error al guardar el pago.',
                            'detalles' => $e->getMessage(),
                        ], 500);
                    }

                    $pagosResultado[] = [
                        'IDOperacion' => $idOperacion,
                        'IDPago' => $pago->IDOperacionPago,
                    ];
                }

                // Análisis individual por operación
                $pagosOperacion = TbOperacionesPagos::where('IDOperacion', $idOperacion)->get();
                $pagosOperacionArr = $pagosOperacion->map(function ($p) use ($conversionMoneda) {
                    $arr = $p->toArray();
                    $arr['IDMonedaInt'] = $conversionMoneda[$arr['IDMoneda']] ?? null;

                    return $arr;
                })->toArray();

                $resultadoAnalisis = $analisisService->analizarPagos($operacion, $pagosOperacionArr, $clienteAnalisis);
                $evidencias = $analisisService->generarEvidencias($resultadoAnalisis, $pagosOperacionArr);

                foreach ($resultadoAnalisis->alertasGenerar as $alertaData) {
                    $alertaCreada = $this->crearAlerta($operacion, $clienteAnalisis, $alertaData, $evidencias, $pagosOperacion, $resultadoAnalisis, $request->IDFormaPago);
                    $alertasConsolidadas[] = $alertaCreada;
                }

                foreach ($resultadoAnalisis->reportesRegulatorios as $reporte) {
                    $this->generarReporteRegulatorio($operacion, $reporte);
                }

                // PPE: 1 alerta por operación, revisa todos los IDCliente involucrados (3.1, 3.3, 3.4)
                // Incluye titular de la operación + todos los pagadores del grupo
                $existePPE = TbAlertas::where('Patron', AnalisisPagosService::PATRON_PPE)
                    ->where('IDOperacion', $idOperacion)
                    ->exists();
                if (! $existePPE) {
                    $idsARevisar = collect($detalles)->pluck('IDCliente')->push($operacion->IDCliente)->unique()->filter();
                    $clientePPE = null;
                    foreach ($idsARevisar as $idCli) {
                        $cli = $clientes->get($idCli);
                        // Si el titular no está en $clientes (no es pagador), buscarlo
                        if (! $cli && $idCli == $operacion->IDCliente) {
                            $cli = TbClientes::find($idCli);
                        }
                        if ($cli && $cli->EsPPEActivo) {
                            $clientePPE = $cli;
                            break;
                        }
                    }
                    // Fallback: también revisar via Análisis ya generado por servicio (si el servicio detectó PPE pero no se filtró por idempotencia)
                    // Si no se encontró via IDs pero el servicio ya marcó esPPE, usar clienteAnalisis
                    if (! $clientePPE && $resultadoAnalisis->esPPE && $clienteAnalisis && $clienteAnalisis->EsPPEActivo) {
                        $clientePPE = $clienteAnalisis;
                    }
                    if ($clientePPE) {
                        $alertaDataPPE = [
                            'patron' => AnalisisPagosService::PATRON_PPE,
                            'descripcion' => 'Persona Políticamente Expuesta',
                            'razones' => 'Pago realizado por cliente PPE (IDCliente '.$clientePPE->IDCliente.') en operación '.$idOperacion,
                        ];
                        $evidenciasPPE = array_merge($evidencias, [
                            'tipo' => 'PPE_Pago',
                            'cliente_ppe_id' => $clientePPE->IDCliente,
                            'operacion_id' => $idOperacion,
                        ]);
                        // Si ya se generó PPE vía servicio, no duplicar (existe check arriba ya previene)
                        $yaGeneradoPorServicio = collect($resultadoAnalisis->alertasGenerar)->contains(fn($a) => ($a['patron'] ?? '') === AnalisisPagosService::PATRON_PPE);
                        if (! $yaGeneradoPorServicio) {
                            $alertaPPECreada = $this->crearAlerta($operacion, $clientePPE, $alertaDataPPE, $evidenciasPPE, $pagosOperacion, null, $request->IDFormaPago);
                            $alertasConsolidadas[] = $alertaPPECreada;
                            \Log::info('[PPE] Alerta PPE generada por pago', [
                                'operacion_id' => $idOperacion,
                                'cliente_ppe_id' => $clientePPE->IDCliente,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Envío consolidado post-commit: un solo correo con todas las alertas generadas en este request
            if (! empty($alertasConsolidadas)) {
                try {
                    NotificacionCumplimientoService::enviarAlertaConsolidada($alertasConsolidadas, null, null);
                } catch (\Throwable $e) {
                    \Log::error('[Notificacion] Error enviando correo consolidado pagos: '.$e->getMessage());
                }
            }

            $mensajeExito = 'Pagos ingresados exitosamente';
            $algunaCoincidencia = $clientes->contains(fn ($c) => $c->CoincideEnListasNegras);
            if ($algunaCoincidencia) {
                $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
            }

            return response()->json([
                'codigoError' => 0,
                'error' => $mensajeExito,
                'Pagos' => $pagosResultado,
                'alertasGeneradas' => count($alertasConsolidadas),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'codigoError' => 500,
                'error' => 'Error inesperado en el proceso de inserción de pagos',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    private function crearAlerta($operacion, $cliente, $alertaData, $evidencias, $pagosOperacion = [], $resultadoAnalisis = null, $idFormaPago = null): TbAlertas
    {
        $nombreCliente = $cliente ? ($cliente->Nombre.' '.$cliente->ApellidoPaterno.' '.$cliente->ApellidoMaterno) : null;
        $nombreAgente = $operacion->NombreAgente.' '.$operacion->APaternoAgente.' '.$operacion->AMaternoAgente;

        $alerta = new TbAlertas;
        //
        $alerta->Folio = null;
        $alerta->Patron = $alertaData['patron'];
        $alerta->IDCliente = $operacion->IDCliente;
        $alerta->Cliente = $nombreCliente;
        $alerta->Poliza = $operacion->FolioPoliza ?? null;
        $alerta->FechaDeteccion = now();
        $alerta->IDOperacion = $operacion->IDOperacion;
        $alerta->HoraDeteccion = now()->format('H:i:s');
        $alerta->FechaOperacion = $operacion->created_at;
        $alerta->HoraOperacion = now()->format('H:i:s');
        $alerta->MontoOperacion = $operacion->PrimaTotal;
        $alerta->RFCAgente = $operacion->RFCAgente ?? null;
        $alerta->Agente = $nombreAgente ?? null;
        $alerta->Estatus = $this->determinarEstatusAlerta($alertaData, $operacion);
        $alerta->Descripcion = $alertaData['descripcion'];
        $alerta->Razones = $alertaData['razones'];
        $alerta->Evidencias = '';
        $alerta->IDReporteOP = null;
        $formaPagoAlerta = CatFormaPagos::where('IDFormaPago', $idFormaPago ?? $operacion->IDFormaPago)->first();
        $alerta->InstrumentoMonetario = $formaPagoAlerta->FormaPago ?? null;

        $alerta->save();

        // CORREGIR: El servicio App\Services\PLD\ReportesRegulatorios no existe/carga.
        // Implementación "dummy": Registrar a Log en vez de llamar al servicio.
        // Si se requiere lógica adicional "real", puede ser implementada aquí.
        \Log::info('[crearAlerta] Reporte regulatorio (dummy) invocado.', [
            'operacion_id' => $operacion->IDOperacion,
            'cliente_id' => $operacion->IDCliente,
            'patron' => $alertaData['patron'],
            'evidencias' => $evidencias,
            'pagos_operacion_count' => count($pagosOperacion),
        ]);
        if ($resultadoAnalisis !== null) {
            $reportesRegulatoriosService = new ReportesRegulatorios;
            $reportesRegulatoriosService->insertarReporte($operacion, $cliente, $alerta, $evidencias, $pagosOperacion, $resultadoAnalisis);
        }

        foreach ($pagosOperacion as $pago) {
            $formaPagoPago = CatFormaPagos::where('IDFormaPago', $pago->IDFormaPago ?? $idFormaPago ?? $operacion->IDFormaPago)->first();
            $pagoAlerta = new TbPagosAlertas;
            $pagoAlerta->IDOperacionPago = $pago->IDOperacionPago;
            $pagoAlerta->IDRegistroAlerta = $alerta->IDRegistroAlerta;
            $pagoAlerta->InstrumentoMonetario = $formaPagoPago->FormaPago ?? null;
            $pagoAlerta->save();
        }

        return $alerta;
    }

    private function generarReporteRegulatorio($operacion, $reporte): void
    {
        \Log::info('Reporte regulatorio generado', [
            'operacion_id' => $operacion->IDOperacion,
            'patron' => $reporte['patron'],
            'monto_usd' => $reporte['monto_usd'],
            'fecha' => now(),
        ]);
    }

    private function determinarEstatusAlerta($alertaData, $operacion = null): string
    {
        $patron = $alertaData['patron'] ?? '';

        if ($patron === AnalisisPagosService::PATRON_MONTO_RELEVANTE) {
            return 'Por reportar';
        }

        if ($patron === AnalisisPagosService::PATRON_CANCELACION) {
            $valorReferenciaUSD = CatParametriaPLD::getOperacionesRelevantes();
            $primaTotalUSD = (new AnalisisPagosService)->convertirAUSD((float) $operacion->PrimaTotal, $operacion->IDMoneda);

            if ($primaTotalUSD < $valorReferenciaUSD) {
                return AnalisisPagosService::ESTATUS_CERRADO;
            }

            return AnalisisPagosService::ESTATUS_GENERADO;
        }

        if ($patron === AnalisisPagosService::PATRON_PPE) {
            return AnalisisPagosService::ESTATUS_GENERADO;
        }

        if ($patron === AnalisisPagosService::PATRON_MONTO_INUSUAL) {
            $montoPagoUSD = $alertaData['monto_usd'] ?? null;

            if ($montoPagoUSD !== null && $montoPagoUSD < CatParametriaPLD::getMontoMinimoAlerta()) {
                return AnalisisPagosService::ESTATUS_CERRADO;
            }

            return AnalisisPagosService::ESTATUS_GENERADO;
        }

        if ($operacion) {
            $montoMinimoUSD = CatParametriaPLD::getMontoMinimoAlerta();
            $primaTotalUSD = (new AnalisisPagosService)->convertirAUSD((float) $operacion->PrimaTotal, $operacion->IDMoneda);

            if ($primaTotalUSD < $montoMinimoUSD) {
                return AnalisisPagosService::ESTATUS_CERRADO;
            }
        }

        return AnalisisPagosService::ESTATUS_GENERADO;
    }

    /**
     * Revierte un pago individual por su IDPago (IDOperacionPago).
     * Si existen alertas con Estatus = "Generado" vinculadas a ese pago,
     * también las limpia junto con sus tbPagosAlertas asociados.
     */
    /**
     * Realiza rollback de uno o varios pagos.
     * Se puede enviar "IDPago" (int, único) o "IDsPagos" (array de IDs).
     */
    public function rollbackPagos(Request $request)
    {
        // Siempre espera el payload como: { "IDsPagos": [123, 124, ...] }
        $idsPagos = $request->input('IDsPagos');

        // Valida que IDsPagos exista y sea array no vacío
        if (! is_array($idsPagos) || empty($idsPagos)) {
            return response()->json([
                'codigoError' => 400,
                'error' => 'Debe proporcionar el campo "IDsPagos" como un arreglo de uno o más IDs para el rollback.',
            ], 400);
        }

        // Verifica que TODOS los pagos existen antes de hacer cualquier operación
        $pagos = TbOperacionesPagos::whereIn('IDOperacionPago', $idsPagos)->get()->keyBy('IDOperacionPago');
        $pagosFaltantes = array_diff($idsPagos, $pagos->keys()->toArray());
        if (! empty($pagosFaltantes)) {
            return response()->json([
                'codigoError' => 404,
                'error' => 'Uno o más pagos no fueron encontrados.',
                'idsNoEncontrados' => array_values($pagosFaltantes),
            ], 404);
        }

        $resultados = [];
        DB::beginTransaction();
        try {
            foreach ($idsPagos as $idPagoIter) {
                $pago = $pagos[$idPagoIter];

                $idOperacion = $pago->IDOperacion;

                $alertasGeneradas = TbAlertas::where('IDOperacion', $idOperacion)
                    ->where('Estatus', 'Generado')
                    ->whereHas('pagosAlertas', function ($query) use ($idPagoIter) {
                        $query->where('IDOperacionPago', $idPagoIter);
                    })
                    ->get();

                $alertasBorradas = 0;

                foreach ($alertasGeneradas as $alerta) {
                    TbPagosAlertas::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)
                        ->where('IDOperacionPago', $idPagoIter)
                        ->delete();

                    if (! TbPagosAlertas::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)->exists()) {
                        $alerta->delete();
                        $alertasBorradas++;
                    }
                }

                $logPagoData = $pago->toArray();
                unset($logPagoData['IDOperacionPago']);

                $logPago = new LogOperacionesPagos;
                $logPago->fill($logPagoData);
                $logPago->save();

                $pago->delete();

                $resultados[] = [
                    'IDPago' => $idPagoIter,
                    'codigoError' => 0,
                    'mensaje' => 'La operación ha sido revertida y movida correctamente a logOperaciones/logOperacionesPagos.'.($alertasBorradas > 0 ? ' Se eliminaron '.$alertasBorradas.' alerta(s) con estatus "Generado" asociadas a este pago.' : ''),
                ];
            }
            DB::commit();

            // Devuelve siempre el arreglo de resultados, aunque sea solo uno por convención de API
            return response()->json([
                'codigoError' => 0,
                'resultados' => $resultados,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'codigoError' => 500,
                'error' => 'Ocurrió un error al intentar revertir uno o más pagos.',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revierte un solo pago (por su IDOperacionPago de forma individual) y registra el log correspondiente.
     * Espera en la request el campo "IDOperacionPago".
     */
    public function rollbackPagoIndividual(Request $request)
    {
        try {
            $idOperacionPago = $request->input('IDOperacionPago');
            if (! $idOperacionPago) {
                return response()->json([
                    'codigoError' => 400,
                    'error' => 'IDOperacionPago es requerido para realizar el rollback individual de pago.',
                ], 400);
            }

            $pago = TbOperacionesPagos::find($idOperacionPago);
            if (! $pago) {
                return response()->json([
                    'codigoError' => 404,
                    'error' => 'No se encontró el pago con el ID proporcionado.',
                    'IDOperacionPago' => $idOperacionPago,
                ], 404);
            }

            $idOperacion = $pago->IDOperacion;

            DB::beginTransaction();

            // Verificar alertas relacionadas con Estatus = "Generado" y que tengan referencia a este pago específico en tbPagosAlertas
            $alertasGeneradas = TbAlertas::where('IDOperacion', $idOperacion)
                ->where('Estatus', 'Generado')
                ->whereHas('pagosAlertas', function ($query) use ($idOperacionPago) {
                    $query->where('IDOperacionPago', $idOperacionPago);
                })
                ->get();

            foreach ($alertasGeneradas as $alerta) {
                // Eliminar registros relacionados en tbPagosAlertas para este pago
                TbPagosAlertas::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)
                    ->where('IDOperacionPago', $idOperacionPago)
                    ->delete();

                // Si ya no quedan pagos asociados a la alerta, eliminar la alerta
                $existenPagosRelacionados = TbPagosAlertas::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)->exists();
                if (! $existenPagosRelacionados) {
                    $alerta->delete();
                }
            }

            // Copiar pago al log y eliminarlo
            $logPagoData = $pago->toArray();
            unset($logPagoData['IDOperacionPago']);
            $logPagoData['IDOperacion'] = $idOperacion;

            $logPago = new LogOperacionesPagos;
            $logPago->fill($logPagoData);
            $logPago->save();

            $pago->delete();

            DB::commit();

            return response()->json([
                'codigoError' => 0,
                'mensaje' => 'El pago ha sido revertido correctamente.'.($alertasGeneradas->isNotEmpty() ? ' Se eliminaron '.$alertasGeneradas->count().' alerta(s) con estatus "Generado" asociadas a este pago.' : ''),
                'IDOperacionPago' => $idOperacionPago,
                'IDOperacion' => $idOperacion,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'codigoError' => 500,
                'error' => 'Ocurrió un error al intentar revertir el pago individual.',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Revierte una operación a partir de la ID recibida en la request.
     * Espera en la request el campo "IDOperacion".
     */
    public function rollbackOperacion(Request $request)
    {
        try {
            $idOperacion = $request->input('IDOperacion');
            if (! $idOperacion) {
                return response()->json([
                    'codigoError' => 400,
                    'error' => 'IDOperacion es requerido para realizar el rollback.',
                ], 400);
            }

            // Buscar la operación original en tbOperaciones
            $operacion = TbOperaciones::find($idOperacion);
            if (! $operacion) {
                return response()->json([
                    'codigoError' => 404,
                    'error' => 'No se encontró la operación con el ID proporcionado.',
                ], 404);
            }

            // Validar si ya está cancelada
            if ($operacion->cancelaPoliza) {
                return response()->json([
                    'codigoError' => 409,
                    'error' => 'La operación ya ha sido cancelada previamente.',
                    'IDOperacion' => $operacion->IDOperacion,
                ], 409);
            }

            // Verificar existencia de alertas relacionadas
            $alertas = TbAlertas::where('IDOperacion', $idOperacion)->get();

            if ($alertas->count() > 0) {
                // Existen alertas relacionadas, revisar los estatus
                $alertasEstatusNoGenerado = $alertas->where(fn ($a) => $a->Estatus !== 'Generado');
                if ($alertasEstatusNoGenerado->count() > 0) {
                    // Hay alguna en estatus diferente de "Generado", NO proceder
                    return response()->json([
                        'codigoError' => 409,
                        'error' => 'No es posible revertir la operación porque tiene alertas relacionadas en un estatus diferente a "Generado".',
                        'IDOperacion' => $operacion->IDOperacion,
                    ], 409);
                }
            }

            // Verificar si la operación tiene pagos asociados
            $pagosCount = TbOperacionesPagos::where('IDOperacion', $idOperacion)->count();
            if ($pagosCount > 0) {
                return response()->json([
                    'codigoError' => 409,
                    'error' => 'No se pudo realizar el rollback porque la operación cuenta con pagos asociados.',
                    'IDOperacion' => $operacion->IDOperacion,
                ], 409);
            }

            // Iniciar transacción DB
            DB::beginTransaction();

            // Si existen alertas con estatus "Generado", eliminarlas (y sus pagos-alertas relacionados)
            if ($alertas->count() > 0) {
                foreach ($alertas as $alerta) {
                    // Eliminar registros en tbPagosAlertas antes de borrar la alerta
                    TbPagosAlertas::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)->delete();
                    $alerta->delete();
                }
            }

            // Copiar la operación a logOperaciones
            $logOperacionData = $operacion->toArray();
            $logOperacionData['cancelaPoliza'] = true; // Marcarla como cancelada en el log

            // Si el modelo LogOperaciones no permite autoincrementar el PK, asegúrate de pasarlo (No autoincrement)
            $logOperacion = new \App\Models\LogOperaciones;
            $logOperacion->fill($logOperacionData);
            $logOperacion->save();

            // Eliminar la operación original en tbOperaciones
            $operacion->delete();

            DB::commit();

            return response()->json([
                'codigoError' => 0,
                'mensaje' => 'La operación ha sido revertida y movida correctamente a logOperaciones.'.
                             ($alertas->count() > 0 ? " Se eliminaron {$alertas->count()} alerta(s) con estatus 'Generado' asociadas." : ''),
                'IDOperacion' => $logOperacion->IDOperacion,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'codigoError' => 500,
                'error' => 'Ocurrió un error al intentar revertir la operación.',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelarOperacion(Request $request)
    {
        try {
            $idOperacion = $request->input('IDOperacion');
            if (! $idOperacion) {
                return response()->json([
                    'codigoError' => 400,
                    'error' => 'IDOperacion es requerido para cancelar la operación.',
                ], 400);
            }

            $operacion = TbOperaciones::find($idOperacion);

            if (! $operacion) {
                return response()->json([
                    'codigoError' => 404,
                    'error' => 'No se encontró la operación especificada.',
                ], 404);
            }

            $operacion->operacionCancelada = true;
            $operacion->save();

            return response()->json([
                'codigoError' => 0,
                'mensaje' => 'La operación fue cancelada exitosamente.',
                'IDOperacion' => $operacion->IDOperacion,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'codigoError' => 500,
                'error' => 'Ocurrió un error al intentar cancelar la operación.',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Evalúa si debe generarse alerta de cancelación para un endoso.
     * Reglas: 1) No duplicar alerta para mismo FolioPoliza en tbAlertas
     *         2) Debe existir operación base (primer registro con mismo FolioPoliza)
     *         3) Base debe tener al menos un pago emitido
     *         4) Diferencia entre FechaEmision del endoso y FechaPago del último pago <=31 días (solo fechas)
     */
    private function evaluarReglasCancelacion(TbOperaciones $endoso): array
    {
        // 1) Idempotencia: solo TbAlertas, Patrón Cancelacion + misma póliza
        // Usar lockForUpdate si estamos dentro de transacción para evitar race condition
        $existeAlerta = TbAlertas::where('Patron', AnalisisPagosService::PATRON_CANCELACION)
            ->where('Poliza', $endoso->FolioPoliza)
            ->exists();

        if ($existeAlerta) {
            // Aún así necesitamos base para anular pagos (3.5 siempre anula)
            $baseParaAnular = TbOperaciones::where('FolioPoliza', $endoso->FolioPoliza)
                ->where('IDOperacion', '!=', $endoso->IDOperacion)
                ->orderBy('IDOperacion', 'asc')
                ->first();

            return [
                'generarAlerta' => false,
                'motivo' => 'DUPLICADO: Ya existe alerta de Cancelación para la póliza '.$endoso->FolioPoliza,
                'razones' => 'No se genera alerta: ya existe una alerta de Cancelación para esta póliza (idempotencia)',
                'base' => $baseParaAnular,
                'ultimoPago' => null,
                'dias' => null,
            ];
        }

        // 2) Localizar operación base: primer registro con mismo FolioPoliza (la póliza original)
        $base = TbOperaciones::where('FolioPoliza', $endoso->FolioPoliza)
            ->where('IDOperacion', '!=', $endoso->IDOperacion)
            ->orderBy('IDOperacion', 'asc')
            ->first();

        if (! $base) {
            return [
                'generarAlerta' => false,
                'motivo' => 'SIN_BASE: No se encontró operación base para FolioPoliza '.$endoso->FolioPoliza,
                'razones' => 'No se genera alerta: no existe operación base',
                'base' => null,
                'ultimoPago' => null,
                'dias' => null,
            ];
        }

        // 3) Pagos emitidos en la base (aunque parcial)
        $ultimoPago = TbOperacionesPagos::where('IDOperacion', $base->IDOperacion)
            ->orderByDesc('FechaPago')
            ->orderByDesc('IDOperacionPago')
            ->first();

        if (! $ultimoPago) {
            return [
                'generarAlerta' => false,
                'motivo' => 'SIN_PAGOS: La operación base no tiene pagos emitidos',
                'razones' => 'No se genera alerta: la operación base no tiene pagos emitidos',
                'base' => $base,
                'ultimoPago' => null,
                'dias' => null,
            ];
        }

        // 4) Ventana 31 días: FechaEmision endoso vs FechaPago último pago (solo fechas)
        try {
            $fEmision = Carbon::parse($endoso->FechaEmision)->startOfDay();
            $fPago = Carbon::parse($ultimoPago->FechaPago)->startOfDay();
        } catch (\Exception $e) {
            return [
                'generarAlerta' => false,
                'motivo' => 'FECHA_INVALIDA: '.$e->getMessage(),
                'razones' => 'No se genera alerta: fecha inválida',
                'base' => $base,
                'ultimoPago' => $ultimoPago,
                'dias' => null,
            ];
        }

        $dias = $fPago->diffInDays($fEmision, false);

        if ($dias < 0) {
            return [
                'generarAlerta' => false,
                'motivo' => "FECHA_ANTERIOR: Endoso {$fEmision->toDateString()} es anterior al último pago {$fPago->toDateString()} ({$dias} días)",
                'razones' => "No se genera alerta: la fecha de emisión del endoso es anterior a la del último pago",
                'base' => $base,
                'ultimoPago' => $ultimoPago,
                'dias' => $dias,
            ];
        }

        if ($dias > 31) {
            return [
                'generarAlerta' => false,
                'motivo' => "VENTANA_EXCEDIDA: {$dias} días >31 desde último pago {$fPago->toDateString()} hasta emisión {$fEmision->toDateString()}",
                'razones' => "No se genera alerta: el endoso supera 31 días desde el último pago ({$dias} días)",
                'base' => $base,
                'ultimoPago' => $ultimoPago,
                'dias' => $dias,
            ];
        }

        return [
            'generarAlerta' => true,
            'motivo' => 'OK',
            'razones' => "Endoso de cancelación válido: último pago hace {$dias} días (≤31) y base con pagos emitidos",
            'base' => $base,
            'ultimoPago' => $ultimoPago,
            'dias' => $dias,
        ];
    }

    /**
     * Anula todos los pagos de la operación base: copia a logOperacionesPagos y borra de tbOperacionesPagos.
     * Siempre se ejecuta cuando hay endoso de cancelación con base válida (3.5).
     */
    private function anularPagosBase(TbOperaciones $base): int
    {
        $pagos = TbOperacionesPagos::where('IDOperacion', $base->IDOperacion)->get();

        if ($pagos->isEmpty()) {
            return 0;
        }

        $count = 0;
        foreach ($pagos as $pago) {
            $data = $pago->toArray();
            unset($data['IDOperacionPago']);

            $log = new LogOperacionesPagos;
            $log->fill($data);
            $log->save();

            $pago->delete();
            $count++;
        }

        return $count;
    }
}
