<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbOperacionesBeneficiarios;
use App\Models\TbOperacionesPagos;
use App\Models\TbPagosAlertas;
use App\Services\PLD\AnalisisPagosService;
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

            return response()->json([
                "codigoError" => 0,
                "error" => "Operación ingresada exitosamente"
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
            $nombreCliente = $cliente ? ($cliente->Nombre.' '.$cliente->ApellidoPaterno.' '.$cliente->ApellidoMaterno) : null;
            $horaActual = now()->format('H:i:s');
            $fechaActual = now()->format('Y-m-d');

            $sumaDetalles = collect($request->detalleOperaciones)->sum(function ($detalle) {
                return (float) $detalle['detalleMontoPagado'];
            });

            if (bccomp((string) $sumaDetalles, (string) $request->montoPagado, 2) !== 0) {
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'La suma de los campos detalleMontoPagado debe ser igual al campo montoPagado, aunque sea negativa.',
                ], 422);
            }

            $operacionesPagos = [];
            $operacion = null;

            foreach ($request->detalleOperaciones as $detalleOperacion) {
                $folioPoliza = $detalleOperacion['folioPoliza'] ?? null;
                $folioEndoso = $detalleOperacion['folioEndoso'] ?? null;

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

                // --- NUEVA VALIDACIÓN POLIZA PAGADA ---
                // Verificar si la operación ya se encuentra pagada en su totalidad
                // (Se asume que TbOperacionesPagos guarda todos los pagos previos para la operación)
                $montoTotalPagado = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion)->sum('Monto');
                // Se suma lo que entre en esta petición
                $nuevoPago = $detalleOperacion['detalleMontoPagado'];
                $primaTotalOperacion = $operacion->PrimaTotal;

                // Si el monto total pagado + nuevo pago > prima total,
                // o si ya está pagada,
                // devolvemos el error 1 según catálogo solicitado:
                if (bccomp((string)($montoTotalPagado), (string)$primaTotalOperacion, 2) >= 0) {
                    // Ya está pagada: NO permitir
                    return response()->json([
                        "codigoError" => 1,
                        "error" => "La póliza / endoso ya se encuentra pagada en su totalidad"
                    ], 200);
                }
                // Opcional si deseas bloquear el EXCESO de pago, descomenta...
                /*
                if (bccomp((string)($montoTotalPagado + $nuevoPago), (string)$primaTotalOperacion, 2) > 0) {
                    return response()->json([
                        "codigoError" => 1,
                        "error" => "La póliza / endoso ya se encuentra pagada en su totalidad"
                    ], 200);
                }
                */

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
                    ], 500);
                }

                $operacionesPagos[] = $pago;
            }

            // ANÁLISIS DE PAGOS Y GENERACIÓN DE ALERTAS USANDO EL NUEVO SERVICIO
            $analisisService = new AnalisisPagosService;

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
                $this->crearAlerta($operacion, $cliente, $alertaData, $evidencias, $pagosOperacion);
            }

            foreach ($resultadoAnalisis->reportesRegulatorios as $reporte) {
                $this->generarReporteRegulatorio($operacion, $reporte);
            }

            // Código de éxito: 0
            return response()->json([
                "codigoError" => 0,
                "error" => "Operación ingresada exitosamente"
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'codigoError' => 500,
                'error' => 'Error inesperado en el proceso de inserción de pagos',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    private function crearAlerta($operacion, $cliente, $alertaData, $evidencias, $pagosOperacion): void
    {
        $nombreCliente = $cliente ? ($cliente->Nombre.' '.$cliente->ApellidoPaterno.' '.$cliente->ApellidoMaterno) : null;
        $nombreAgente = $operacion->NombreAgente.' '.$operacion->APaternoAgente.' '.$operacion->AMaternoAgente;

        $alerta = new TbAlertas;
        $alerta->Folio = $operacion->FolioEndoso;
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
        $alerta->Evidencias = json_encode($evidencias);
        $alerta->IDReporteOP = null;

        $alerta->save();

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
        if (isset($alertaData['genera_reporte']) && $alertaData['genera_reporte']) {
            return 'Reportado';
        }

        if (isset($alertaData['monto_usd']) && $alertaData['monto_usd'] < 10000) {
            return 'Cerrado';
        }

        return 'Generado';
    }

    // Endpoint insertar monedas

    // Endpoint insertar forma pagos
}
