<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\Clientes\TbClientes;
use App\Models\LogOperacionesPagos;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbOperacionesBeneficiarios;
use App\Models\TbOperacionesPagos;
use App\Models\TbPagosAlertas;
use App\Services\PLD\AnalisisPagosService;
use App\Services\PLD\ReportesRegulatorios;
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

        try {
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

            $mensajeExito = 'Operación ingresada exitosamente';
            if ($cliente->CoincideEnListasNegras) {
                $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
            }

            return response()->json([
                'codigoError' => 0,
                'error' => $mensajeExito,
                'IDOperacion' => $operacion->IDOperacion,
            ], 201);
        } catch (\Exception $e) {
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
                    'IDFormaPago' => 'nullable|string',
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
                $restante = bcsub((string) $primaTotalOperacion, (string) $totalTrasEstaPeticion, 2);

                if (bccomp((string) ($primaTotalOperacion - $montoTotalPagado), '0', 2) <= 0) {
                    DB::rollBack();

                    return response()->json([
                        'codigoError' => 1,
                        'error' => 'La póliza / endoso ya se encuentra pagada en su totalidad',
                        'IDOperacion' => $idOperacion,
                    ], 200);
                }

                if (bccomp((string) $restante, '0', 2) < 0) {
                    DB::rollBack();

                    return response()->json([
                        'codigoError' => 1,
                        'error' => 'No se permite exceder el pago total de la póliza / endoso',
                        'IDOperacion' => $idOperacion,
                    ], 200);
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
                    $this->crearAlerta($operacion, $clienteAnalisis, $alertaData, $evidencias, $pagosOperacion, $resultadoAnalisis);
                }

                foreach ($resultadoAnalisis->reportesRegulatorios as $reporte) {
                    $this->generarReporteRegulatorio($operacion, $reporte);
                }
            }

            DB::commit();

            $mensajeExito = 'Pagos ingresados exitosamente';
            $algunaCoincidencia = $clientes->contains(fn ($c) => $c->CoincideEnListasNegras);
            if ($algunaCoincidencia) {
                $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
            }

            return response()->json([
                'codigoError' => 0,
                'error' => $mensajeExito,
                'Pagos' => $pagosResultado,
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

    private function crearAlerta($operacion, $cliente, $alertaData, $evidencias, $pagosOperacion, $resultadoAnalisis): void
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
        $alerta->Estatus = $this->determinarEstatusAlerta($alertaData);
        $alerta->Descripcion = $alertaData['descripcion'];
        $alerta->Razones = $alertaData['razones'];
        $alerta->Evidencias = '';
        $alerta->IDReporteOP = null;

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
        // Si en el futuro se restaura la clase, puedes descomentar y usar la siguiente línea:
        $reportesRegulatoriosService = new ReportesRegulatorios;
        $reportesRegulatoriosService->insertarReporte($operacion, $cliente, $alerta, $evidencias, $pagosOperacion, $resultadoAnalisis);

        foreach ($pagosOperacion as $pago) {
            $formaPago = CatFormaPagos::find($operacion->IDFormaPago);
            $pagoAlerta = new TbPagosAlertas;
            $pagoAlerta->IDOperacionPago = $pago->IDOperacionPago;
            $pagoAlerta->IDRegistroAlerta = $alerta->IDRegistroAlerta;
            $pagoAlerta->InstrumentoMonetario = $formaPago->FormaPago ?? 'Desconocido';
            $pagoAlerta->save();
        }
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

    private function determinarEstatusAlerta($alertaData): string
    {
        if (($alertaData['patron'] ?? '') === AnalisisPagosService::PATRON_MONTO_RELEVANTE) {
            return 'Por reportar';
        }

        return 'Generado';
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
}
