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

class OperacionesController extends Controller
{
    // -------------
    // OPERACION
    public function insertarOperacion(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'IDCliente' => 'required|integer',
                'FolioPoliza' => 'required|string|max:40',
                'FolioEndoso' => 'required|string|max:40',
                'FechaEmision' => 'required|date',
                'PrimaTotal' => 'required|numeric',
                'IDMoneda' => 'required|string',
                'FechaInicioVigencia' => 'required|date',
                'FechaFinVigencia' => 'required|date',
                'GastosEmision' => 'required|numeric',
                'RFCAgente' => 'required|string|max:13',
                'CURPAgente' => 'required|string|max:18',
                'NombreAgente' => 'required|string|max:100',
                'APaternoAgente' => 'required|string|max:100',
                'AMaternoAgente' => 'required|string|max:100',
                'RazonSocialAgente' => 'required|string|max:300',
                'EsEndosoCancelacion' => 'required|boolean',
                'DetalleBeneficiarios' => 'required|array|min:1',
                'DetalleBeneficiarios.*.RFC' => 'required|string|max:13',
                'DetalleBeneficiarios.*.CURP' => 'required|string|max:18',
                'DetalleBeneficiarios.*.nombre' => 'required|string|max:100',
                'DetalleBeneficiarios.*.apellidoPaterno' => 'required|string|max:100',
                'DetalleBeneficiarios.*.apellidoMaterno' => 'required|string|max:100',
                'DetalleBeneficiarios.*.razonSocial' => 'nullable|string|max:300',
                'DetalleBeneficiarios.*.preferente' => 'required|boolean',
                'DetalleBeneficiarios.*.porcentajeParticipacion' => 'required|numeric|min:0|max:100',
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
            $operacion->FolioEndoso = $validatedData['FolioEndoso'];
            $operacion->FechaEmision = $validatedData['FechaEmision'];
            $operacion->PrimaTotal = $validatedData['PrimaTotal'];
            $operacion->GastosEmision = $validatedData['GastosEmision'];
            $operacion->RFCAgente = $validatedData['RFCAgente'];
            $operacion->CURPAgente = $validatedData['CURPAgente'];
            $operacion->NombreAgente = $validatedData['NombreAgente'];
            $operacion->APaternoAgente = $validatedData['APaternoAgente'];
            $operacion->AMaternoAgente = $validatedData['AMaternoAgente'];
            $operacion->RazonSocialAgente = $validatedData['RazonSocialAgente'];
            $operacion->IDMoneda = $validatedData['IDMoneda'];
            $operacion->FechaInicioVigencia = $validatedData['FechaInicioVigencia'];
            $operacion->FechaFinVigencia = $validatedData['FechaFinVigencia'];
            $operacion->tipoDocumento = $request->tipoDocumento ?? null;
            $operacion->save();

            $beneficiarios = $validatedData['DetalleBeneficiarios'];
            foreach ($beneficiarios as $beneficiario) {
                $beneficiarioModel = new TbOperacionesBeneficiarios;
                $beneficiarioModel->IDOperacion = $operacion->IDOperacion;
                $beneficiarioModel->RFCBeneficiario = $beneficiario['RFC'];
                $beneficiarioModel->CURPBeneficiario = $beneficiario['CURP'];
                $beneficiarioModel->NombreBeneficiario = $beneficiario['nombre'];
                $beneficiarioModel->APaternoBeneficiario = $beneficiario['apellidoPaterno'];
                $beneficiarioModel->AMaternoBeneficiario = $beneficiario['apellidoMaterno'];
                $beneficiarioModel->RazonSocialBeneficiario = $beneficiario['razonSocial'] ?? null;
                $beneficiarioModel->Preferente = $beneficiario['preferente'];
                $beneficiarioModel->PorcentajeParticipacion = $beneficiario['porcentajeParticipacion'];
                $beneficiarioModel->save();
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
            $jsonData = $request->all();

            // Validación personalizada para capturar los errores y regresar JSON
            try {
                $validated = $request->validate([
                    'IDCliente' => 'required|integer',
                    'montoPagado' => 'required|numeric',
                    'IDMoneda' => 'required|string',
                    'IDFormaPago' => 'required|string',
                    'TipoCambio' => 'required|numeric',
                    'FechaPago' => 'required|date',
                    'detalleOperaciones' => 'required|array|min:1',
                    'detalleOperaciones.*.folioPoliza' => 'nullable|string',
                    'detalleOperaciones.*.folioEndoso' => 'nullable|string',
                    'detalleOperaciones.*.detalleMontoPagado' => 'required|numeric',
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

            $cliente = TbClientes::where('IDCliente', $request->IDCliente)->first();

            // Validar si el cliente está activo
            if (! $cliente || ! $cliente->Activo) {
                return response()->json([
                    'codigoError' => 403,
                    'error' => 'El cliente no se encuentra activo por coincidencias en listas.',
                ], 403);
            }

            $nombreCliente = $cliente ? ($cliente->Nombre.' '.$cliente->ApellidoPaterno.' '.$cliente->ApellidoMaterno) : null;
            $horaActual = now()->format('H:i:s');
            $fechaActual = now()->format('Y-m-d');

            $sumaDetalles = collect($request->detalleOperaciones)->sum(function ($detalle) {
                return (float) $detalle['detalleMontoPagado'];
            });

            if (bccomp((string) $sumaDetalles, (string) $request->montoPagado, 2) !== 0) {
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'La suma de los campos detalleMontoPagado debe ser igual al campo montoPagado, aunque sean negativos o positivos.',
                ], 422);
            }

            $operacionesPagos = [];
            $operacion = null;
            $idOperacionResult = null; // Inicializar la variable para devolverla al response

            // Agrupar operaciones por poliza y endoso
            $detalleAGrupado = [];
            foreach ($request->detalleOperaciones as $detalleOperacion) {
                $key = ($detalleOperacion['folioPoliza'] ?? '').'||'.($detalleOperacion['folioEndoso'] ?? '');
                if (! isset($detalleAGrupado[$key])) {
                    $detalleAGrupado[$key] = [
                        'folioPoliza' => $detalleOperacion['folioPoliza'] ?? null,
                        'folioEndoso' => $detalleOperacion['folioEndoso'] ?? null,
                        'detalles' => [],
                    ];
                }
                $detalleAGrupado[$key]['detalles'][] = $detalleOperacion;
            }

            foreach ($detalleAGrupado as $grupo) {
                $folioPoliza = $grupo['folioPoliza'];
                $folioEndoso = $grupo['folioEndoso'];
                $operacionQuery = TbOperaciones::query();
                if ($folioPoliza && $folioEndoso) {
                    $operacionQuery->where('FolioPoliza', $folioPoliza)
                        ->where('FolioEndoso', $folioEndoso);
                } elseif ($folioPoliza) {
                    $operacionQuery->where('FolioPoliza', $folioPoliza);
                } elseif ($folioEndoso) {
                    $operacionQuery->where('FolioEndoso', $folioEndoso);
                } else {
                    return response()->json([
                        'codigoError' => 422,
                        'error' => 'Se requiere al menos folioPoliza o folioEndoso para identificar la operación.',
                    ], 422);
                }
                $op = $operacionQuery->first();
                $operacion = $op;

                if (! $operacion) {
                    return response()->json([
                        'codigoError' => 404,
                        'error' => 'No se encontró la operación correspondiente a los folios proporcionados.',
                    ], 404);
                }

                // Guardar IDOperacion de la primera encontrada relevante (o última iteración, si son varias)
                $idOperacionResult = $operacion->IDOperacion;

                // Sumar todos los pagos previos para la operación
                $montoTotalPagado = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion)->sum('Monto');
                // Sumar los pagos de esta petición para la operación
                $nuevoPagoTotal = array_sum(array_column($grupo['detalles'], 'detalleMontoPagado'));
                $primaTotalOperacion = $operacion->PrimaTotal;

                // Valida si la suma total (pagos anteriores + esta petición) ya cubre justo la prima (0), no sobrepasa, y admite montos negativos y positivos
                $saldoPendiente = bcadd((string) ($primaTotalOperacion - $montoTotalPagado), (string) (-$nuevoPagoTotal), 2); // saldo pendiente después del pago
                $totalPagadoTrasEstaPeticion = bcadd((string) $montoTotalPagado, (string) $nuevoPagoTotal, 2);
                $restante = bcsub((string) $primaTotalOperacion, (string) $totalPagadoTrasEstaPeticion, 2);

                // La póliza se considera pagada si el saldo es exactamente 0 después de este pago (admite positivo y negativo en pagos)
                if (bccomp((string) $restante, '0', 2) == 0) {
                    // Permite este pago (pagado exacto a la prima), no bloquea.
                } elseif (bccomp((string) $restante, '0', 2) < 0) {
                    // Demasiado pagado, no se permite. Permite llegar a 0 exacto, admite negativos para compensar.
                    return response()->json([
                        'codigoError' => 1,
                        'error' => 'No se permite exceder el pago total de la póliza / endoso',
                        'IDOperacion' => $idOperacionResult,
                    ], 200);
                }

                // Si ya estaba pagada antes de este pago (restante antes del pago <= 0), no permitir más pagos
                if (bccomp((string) ($primaTotalOperacion - $montoTotalPagado), '0', 2) <= 0) {
                    return response()->json([
                        'codigoError' => 1,
                        'error' => 'La póliza / endoso ya se encuentra pagada en su totalidad',
                        'IDOperacion' => $idOperacionResult,
                    ], 200);
                }

                // Guarda cada uno de los pagos del grupo
                foreach ($grupo['detalles'] as $detalleOperacion) {
                    $pago = new TbOperacionesPagos;
                    $pago->IDOperacion = $operacion->IDOperacion;
                    $pago->IDCliente = $request->IDCliente;
                    $pago->Monto = $detalleOperacion['detalleMontoPagado'];
                    $pago->IDMoneda = $request->IDMoneda;
                    $pago->IDFormaPago = $request->IDFormaPago;
                    $pago->TipoCambio = $request->TipoCambio;
                    $pago->FechaPago = $request->FechaPago;

                    try {
                        $pago->save();
                    } catch (\Exception $e) {
                        return response()->json([
                            'codigoError' => 500,
                            'error' => 'Error al guardar el pago.',
                            'detalles' => $e->getMessage(),
                            'IDOperacion' => $idOperacionResult,
                        ], 500);
                    }

                    $operacionesPagos[] = $pago;
                }
            }

            // ANÁLISIS DE PAGOS Y GENERACIÓN DE ALERTAS USANDO EL NUEVO SERVICIO
            $analisisService = new AnalisisPagosService;

            // Usa la última operación del grupo para análisis (aplica si solo hay 1 operación involucrada)
            $pagosOperacion = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion)->get();
            $monedaStr = $operacion->IDMoneda;
            $moneda = \App\Models\CatMonedas::where('IDMoneda', $monedaStr)->first();
            $conversionMoneda = [
                'MXN' => 1,
                'USD' => 2,
            ];
            $idMonedaInt = $conversionMoneda[$monedaStr] ?? null;

            $pagosOperacionArr = $pagosOperacion->map(function ($pago) use ($conversionMoneda) {
                $pagoArr = $pago->toArray();
                $monedaStr = $pagoArr['IDMoneda'];
                $pagoArr['IDMonedaInt'] = $conversionMoneda[$monedaStr] ?? null;

                return $pagoArr;
            })->toArray();

            $resultadoAnalisis = $analisisService->analizarPagos(
                $operacion,
                $pagosOperacionArr,
                $cliente
            );

            $evidencias = $analisisService->generarEvidencias($resultadoAnalisis, $pagosOperacionArr);

            foreach ($resultadoAnalisis->alertasGenerar as $alertaData) {
                $this->crearAlerta($operacion, $cliente, $alertaData, $evidencias, $pagosOperacion, $resultadoAnalisis);
            }

            foreach ($resultadoAnalisis->reportesRegulatorios as $reporte) {
                $this->generarReporteRegulatorio($operacion, $reporte);
            }

            // Código de éxito: 0
            $mensajeExito = 'Operación ingresada exitosamente';
            if ($cliente->CoincideEnListasNegras) {
                $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
            }

            return response()->json([
                'codigoError' => 0,
                'error' => $mensajeExito,
                'IDOperacion' => $idOperacionResult,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'codigoError' => 500,
                'error' => 'Error inesperado en el proceso de inserción de pagos',
                'detalles' => $e->getMessage(),
                'IDOperacion' => isset($idOperacionResult) ? $idOperacionResult : null,
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
            $formaPago = CatFormaPagos::find($pago->IDFormaPago);
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
     * Revierte los pagos de una operación. Si existen alertas con Estatus = "Generado",
     * también las elimina junto con sus tbPagosAlertas asociados.
     * Espera en la request el campo "IDOperacion".
     */
    public function rollbackPagos(Request $request)
    {
        try {
            $idOperacion = $request->input('IDOperacion');
            if (! $idOperacion) {
                return response()->json([
                    'codigoError' => 400,
                    'error' => 'IDOperacion es requerido para realizar el rollback de pagos.',
                ], 400);
            }

            $operacion = TbOperaciones::find($idOperacion);
            if (! $operacion) {
                return response()->json([
                    'codigoError' => 404,
                    'error' => 'No se encontró la operación con el ID proporcionado.',
                ], 404);
            }

            $pagos = TbOperacionesPagos::where('IDOperacion', $idOperacion)->get();
            if ($pagos->isEmpty()) {
                return response()->json([
                    'codigoError' => 404,
                    'error' => 'No se encontraron pagos asociados a la operación proporcionada.',
                    'IDOperacion' => $idOperacion,
                ], 404);
            }

            \DB::beginTransaction();

            // Verificar alertas relacionadas con Estatus = "Generado"
            $alertasGeneradas = TbAlertas::where('IDOperacion', $idOperacion)
                ->where('Estatus', 'Generado')
                ->get();

            foreach ($alertasGeneradas as $alerta) {
                // Eliminar registros en tbPagosAlertas antes de borrar la alerta
                TbPagosAlertas::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)->delete();
                $alerta->delete();
            }

            // Copiar pagos al log y eliminarlos
            foreach ($pagos as $pago) {
                $logPagoData = $pago->toArray();
                unset($logPagoData['IDOperacionPago']);
                $logPagoData['IDOperacion'] = $idOperacion;

                $logPago = new LogOperacionesPagos;
                $logPago->fill($logPagoData);
                $logPago->save();
            }

            TbOperacionesPagos::where('IDOperacion', $idOperacion)->delete();

            \DB::commit();

            return response()->json([
                'codigoError' => 0,
                'mensaje' => 'Los pagos han sido revertidos correctamente.'.($alertasGeneradas->isNotEmpty() ? ' Se eliminaron '.$alertasGeneradas->count().' alerta(s) con estatus "Generado" asociadas.' : ''),
                'IDOperacion' => $idOperacion,
            ]);

        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'codigoError' => 500,
                'error' => 'Ocurrió un error al intentar revertir los pagos.',
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
            \DB::beginTransaction();

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

            \DB::commit();

            return response()->json([
                'codigoError' => 0,
                'mensaje' => 'La operación ha sido revertida y movida correctamente a logOperaciones.'.
                             ($alertas->count() > 0 ? " Se eliminaron {$alertas->count()} alerta(s) con estatus 'Generado' asociadas." : ''),
                'IDOperacion' => $logOperacion->IDOperacion,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();

            return response()->json([
                'codigoError' => 500,
                'error' => 'Ocurrió un error al intentar revertir la operación.',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }
}
