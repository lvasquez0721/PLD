<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\CatParametriaPLD;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbOperacionesBeneficiarios;
use App\Models\TbOperacionesPagos;
use App\Models\TbPagosAlertas;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class OperacionesController extends Controller
{
    // -------------
    //OPERACION
    public function insertarOperacion(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'IDCliente' => 'required|integer',
                'FolioPoliza' => 'required|string|max:40',
                'FolioEndoso' => 'required|string|max:40',
                'FechaEmision' => 'required|date',
                'PrimaTotal' => 'required|numeric',
                'IDMoneda' => 'required|integer',
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
            $operacion = new TbOperaciones();
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
            // $operacion->PPE = $validatedData['PPE'];
            $operacion->IDMoneda = $validatedData['IDMoneda'];
            $operacion->FechaInicioVigencia = $validatedData['FechaInicioVigencia'];
            $operacion->FechaFinVigencia = $validatedData['FechaFinVigencia'];
            // Maneja el campo tipoDocumento si viene en el request
            $operacion->tipoDocumento = $request->tipoDocumento ?? null;
            $operacion->save();

            // beneficiarios
            $beneficiarios = $validatedData['DetalleBeneficiarios'];
            foreach ($beneficiarios as $beneficiario) {
                $beneficiarioModel = new TbOperacionesBeneficiarios();
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

            return response()->json($operacion, 201);
        } catch (\Exception $e) {
            return response()->json([
                'codigoError' => 500,
                'error' => 'Error al insertar la operación o beneficiarios',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------
    //PAGO
    public function insertarOperacionPago(Request $request)
    {
        try {
            $jsonData = $request->all();

            // Validación personalizada para capturar los errores y regresar JSON
            try {
                $validated = $request->validate([
                    'IDCliente' => 'required|integer',
                    'montoPagado' => 'required|numeric',
                    'IDMoneda' => 'required|integer',
                    'IDFormaPago' => 'required|integer',
                    'TipoCambio' => 'required|numeric',
                    'FechaPago' => 'required|date',
                    'detalleOperaciones' => 'required|array|min:1',
                    'detalleOperaciones.*.folioPoliza' => 'nullable|string',
                    'detalleOperaciones.*.folioEndoso' => 'nullable|string',
                    'detalleOperaciones.*.detalleMontoPagado' => 'required|numeric'
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
            $nombreCliente = $cliente ? ($cliente->Nombre . ' ' . $cliente->ApellidoPaterno . ' ' . $cliente->ApellidoMaterno) : null;
            $horaActual = now()->format('H:i:s');
            $fechaActual = now()->format('Y-m-d');

            $sumaDetalles = collect($request->detalleOperaciones)->sum(function ($detalle) {
                return (float) $detalle['detalleMontoPagado'];
            });

            if (bccomp((string)$sumaDetalles, (string)$request->montoPagado, 2) !== 0) { // precisión dos decimales
                return response()->json([
                    'codigoError' => 422,
                    'error' => 'La suma de los campos detalleMontoPagado debe ser igual al campo montoPagado, aunque sea negativa.'
                ], 422);
            }

            $operacionesPagos = [];

            foreach ($request->detalleOperaciones as $detalleOperacion) {
                // Buscar operación por ambos folios si ambos existen, si no por uno solo
                $folioPoliza = $detalleOperacion['folioPoliza'] ?? null;
                $folioEndoso = $detalleOperacion['folioEndoso'] ?? null;

                // Buscar la operación por ambos folios si ambos existen, si no por uno solo
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
                        'error' => 'Se requiere al menos folioPoliza o folioEndoso para identificar la operación.'
                    ], 422);
                }
                $operacion = $operacionQuery->first();

                if (!$operacion) {
                    return response()->json([
                        'codigoError' => 404,
                        'error' => 'No se encontró la operación correspondiente a los folios proporcionados.'
                    ], 404);
                }

                $pago = new TbOperacionesPagos();
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


            // ALERTA POR PAGO FRACCIONADOS
            $pagosOperacion = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion)->get();
            $primerPago = $pagosOperacion->first();
            $operacion = TbOperaciones::where('IDOperacion', $primerPago->IDOperacion)->first();
            $nombreAgente = $operacion->NombreAgente . ' ' . $operacion->APaternoAgente . ' ' . $operacion->AMaternoAgente;
            $montoOperacion = $operacion->PrimaTotal;
            $montoTotalPagado = $pagosOperacion->sum('Monto');
            $t = CatParametriaPLD::where('IDParametro', 15)->first();
            $tolerancia = $t->Valor;

            if ($pagosOperacion->count() > 1 && $montoTotalPagado >= $totalAPagar) {

                $pagosOp = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion);
                $montoTotal = $pagosOp->sum('Monto');

                $alerta = new TbAlertas();
                $alerta->Folio = $operacion->FolioEndoso;
                $alerta->Patron = 'Pagos múltiples en una operación';
                $alerta->IDCliente = $request->IDCliente;
                $alerta->Cliente = $nombreCliente;
                $alerta->Poliza = $operacion->FolioPoliza ?? null;
                $alerta->FechaDeteccion = now();
                // $alerta->IDOperacionPago = null;
                $alerta->IDOperacion = $operacion->IDOperacion;
                $alerta->HoraDeteccion = now()->format('H:i:s');
                $alerta->FechaOperacion = $operacion->created_at;
                $alerta->HoraOperacion = $horaActual;
                $alerta->MontoOperacion = $montoTotal;
                // $alerta->InstrumentoMonetario = null;
                $alerta->RFCAgente = $operacion->RFCAgente ?? null;
                $alerta->Agente = $nombreAgente ?? null;
                $alerta->Estatus = 'Pendiente';
                $alerta->Descripcion = 'Se detectaron múltiples pagos para la misma operación';
                $alerta->Razones = 'Una operación tiene más de un registro de pago asociado';
                $alerta->Evidencias = $operacion->tipoDocumento;
                $alerta->IDReporteOP = null;
                // $alerta->IDPago = $pago->IDOperacionPago;
                $alerta->save();

                foreach ($pagosOperacion as $pagoFrac) {
                    $insMonetario = CatFormaPagos::where('IDFormaPago', $pagoFrac->IDFormaPago)->first();

                    $pagoAlerta = new TbPagosAlertas();
                    $pagoAlerta->IDOperacionPago = $pagoFrac->IDOperacionPago;
                    $pagoAlerta->IDRegistroAlerta = $alerta->IDRegistroAlerta;
                    $pagoAlerta->InstrumentoMonetario = $insMonetario->FormaPago;
                    $pagoAlerta->save();
                }
            }


            // ALERTA POR PAGOS ACUMULADOS EN EFECTIVO
            $pagosEfectivo = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion)
                ->where('IDFormaPago', 1) // 1: Efectivo
                ->get();

            // Calcular el total pagado en efectivo
            $totalPagadoEfectivo = $pagosEfectivo->sum('Monto');
            $totalAPagar = $operacion->PrimaTotal;

            // Valida si el pago total fue completado
            if ($pagosEfectivo->count() > 1 && $totalPagadoEfectivo >= $totalAPagar) {



                $alertaEfectivo = new TbAlertas();
                $alertaEfectivo->Folio = $operacion->FolioEndoso;
                $alertaEfectivo->Patron = 'Pagos acumulados en efectivo para una operación';
                $alertaEfectivo->IDCliente = $pago->IDCliente;
                $alertaEfectivo->Cliente = $nombreCliente;
                $alertaEfectivo->Poliza = $operacion->FolioPoliza ?? null;
                $alertaEfectivo->FechaDeteccion = now();
                $alertaEfectivo->IDOperacion = $operacion->IDOperacion;
                $alertaEfectivo->HoraDeteccion = now()->format('H:i:s');
                $alertaEfectivo->FechaOperacion = $operacion->created_at;
                $alertaEfectivo->HoraOperacion = $horaActual;
                $alertaEfectivo->MontoOperacion = $montoTotal;
                // $alertaEfectivo->InstrumentoMonetario = 'Efectivo';
                $alertaEfectivo->RFCAgente = $operacion->RFCAgente ?? null;
                $alertaEfectivo->Agente = $nombreAgente ?? null;
                $alertaEfectivo->Estatus = 'Pagos acumulados en efectivo';
                $alertaEfectivo->Descripcion = 'Se detectaron múltiples pagos en efectivo para la misma operación';
                $alertaEfectivo->Razones = 'Una operación tiene más de un registro de pago en efectivo asociado';
                $alertaEfectivo->Evidencias = $operacion->tipoDocumento;
                $alertaEfectivo->IDReporteOP = null;
                // $alertaEfectivo->IDPago = $pago->IDOperacionPago;
                $alertaEfectivo->save();

                foreach ($pagosEfectivo as $pagoEfectivo) {
                    $insMonetario = CatFormaPagos::where('IDFormaPago', $pagoEfectivo->IDFormaPago)->first();

                    $pagoAlerta = new TbPagosAlertas();
                    $pagoAlerta->IDOperacionPago = $pagoEfectivo->IDOperacionPago;
                    $pagoAlerta->IDRegistroAlerta = $alertaEfectivo->IDRegistroAlerta;
                    $pagoAlerta->InstrumentoMonetario = $insMonetario->FormaPago;
                    $pagoAlerta->save();
                }
            }


            // ALERTAS DE OPERACION POR MONTO RELEVANTE
            $cliente = TbClientes::where('IDCliente', $operacion->IDCliente)->first();
            $IDTipoPersona = $cliente->IDTipoPersona;
            $operacion = TbOperaciones::where('IDOperacion', $request->IDOperacion)->first();
            $pagos = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion);
            $primaTotal = $operacion->PrimaTotal;

            //monto autorizado
            if ($IDTipoPersona == 1) { // física
                $montoAutorizadoEfectivoMxN = CatParametriaPLD::where('IDParametro', 16)->first();
            } elseif ($IDTipoPersona == 2) { // moral
                $montoAutorizadoEfectivoMxN = CatParametriaPLD::where('IDParametro', 17)->first();
            } else {
                $montoAutorizadoEfectivoMxN = null;
            }

            if ($operacion->IDMoneda == 1) {

                // pesos mexicanos
                foreach ($pagos as $pago) {
                    $monto = $pago->Monto;
                    // Validar si monto es igual o superior a montoAutorizadoEfectivoMxN
                    if (
                        $montoAutorizadoEfectivoMxN !== null
                        && isset($montoAutorizadoEfectivoMxN->Valor)
                        && $monto >= $montoAutorizadoEfectivoMxN->Valor
                    ) {


                        $alertaEfectivo = TbAlertas::new();
                        $alertaEfectivo->Folio = $operacion->FolioPoliza;
                        $alertaEfectivo->Patron = 'Operacion por monto relevante excedido';
                        $alertaEfectivo->IDCliente = $nombreCliente;
                        $alertaEfectivo->Poliza = $operacion->FolioPoliza;
                        $alertaEfectivo->FechaDeteccion = now();
                        $alertaEfectivo->IDOperacion = $operacion->IDCliente;
                        $alertaEfectivo->HoraDeteccion = now()->format('H:i:s');
                        $alertaEfectivo->FechaOperacion = $operacion->created_at;
                        $alertaEfectivo->HoraOperacion = $horaActual;
                        $alertaEfectivo->MontoOperacion = $monto;
                        $alertaEfectivo->RFCAgente = $operacion->RFCAgente ?? null;
                        $alertaEfectivo->Agente = $nombreAgente ?? null;
                        $alertaEfectivo->Estatus = 'Operacion por monto relevante';
                        $alertaEfectivo->Descripcion = 'Se detecto una operacion con un monto igual o superior al umbral';
                        $alertaEfectivo->Razones = 'El monto pagado en efectivo, es igual o mayor a lo permitido';
                        $alertaEfectivo->Evidencias = $operacion->tipoDocumento;
                        $alertaEfectivo->IDReporteOP = null;
                        $alertaEfectivo->save();

                        $insMonetario = CatFormaPagos::where('IDFormaPago', $pago->IDFormaPago)->first();

                        $pago = TbPagosAlertas::new();
                        $pago->IDOperacionPago = $pago->IDOperacionPago;
                        $pago->IDRegistroAlerta = $alertaEfectivo->IDRegistroAlerta;
                        $pago->InstrumentoMonetario = $insMonetario->FormaPago;
                        $pago->save();
                    }
                }


                if ($operacion->PrimaTotal) {
                }
            } elseif ($operacion->IDMoneda == 2) {
                // Dólares americanos

                $client = new Client();

                // Calcular fechas para hoy, ayer, antier y antiantier
                $hoy = now()->format('Y-m-d');
                $ayer = now()->subDay()->format('Y-m-d');
                $antier = now()->subDays(2)->format('Y-m-d');
                $antiantier = now()->subDays(3)->format('Y-m-d');

                $fechaInicial = $antiantier;
                $fechaFinal = $hoy;

                $url = "https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/{$fechaInicial}/{$fechaFinal}?token=dafb7f16f4ec83af4c269688bde5bab13903be80138f5d19773a7e83346c5aae";
                $res = $client->request('GET', $url);
                $body = (string) $res->getBody();
                $responseJson = json_decode($body, true);

                // Obtener el arreglo de datos
                $datos = $responseJson['bmx']['series'][0]['datos'] ?? [];

                // Buscar el dato con la fecha más reciente
                $datoMasReciente = null;
                if (!empty($datos)) {
                    usort($datos, function ($a, $b) {
                        // Suponiendo que las fechas vienen en formato 'd/m/Y'
                        $dateA = \DateTime::createFromFormat('d/m/Y', $a['fecha']);
                        $dateB = \DateTime::createFromFormat('d/m/Y', $b['fecha']);
                        return $dateB <=> $dateA;
                    });
                    $datoMasReciente = $datos[0];
                }

                // Ahora puedes usar $datoMasReciente['dato'] para el dato más reciente
                $precioDolarEnMx = $datoMasReciente ? $datoMasReciente['dato'] : null;

                foreach ($pagos as $pago) {
                    $montoEnDolar = (float) $pago->Monto;
                    $maem = (float) $montoAutorizadoEfectivoMxN->Valor;
                    $montoAutorizadoEnDolar = $maem * (float) $precioDolarEnMx;
                }

                if (
                    $montoAutorizadoEnDolar !== null
                    && isset($montoAutorizadoEnDolar)
                    && $montoEnDolar >= $montoAutorizadoEnDolar
                ) {

                    $alertaEfectivo = TbAlertas::new();
                    $alertaEfectivo->Folio = $operacion->FolioPoliza;
                    $alertaEfectivo->Patron = 'Operacion por monto relevante excedido';
                    $alertaEfectivo->IDCliente = $nombreCliente;
                    $alertaEfectivo->Poliza = $operacion->FolioPoliza;
                    $alertaEfectivo->FechaDeteccion = now();
                    $alertaEfectivo->IDOperacion = $operacion->IDCliente;
                    $alertaEfectivo->HoraDeteccion = now()->format('H:i:s');
                    $alertaEfectivo->FechaOperacion = $operacion->created_at;
                    $alertaEfectivo->HoraOperacion = $horaActual;
                    $alertaEfectivo->MontoOperacion = $montoEnDolar;
                    $alertaEfectivo->RFCAgente = $operacion->RFCAgente ?? null;
                    $alertaEfectivo->Agente = $nombreAgente ?? null;
                    $alertaEfectivo->Estatus = 'Operacion por monto relevante';
                    $alertaEfectivo->Descripcion = 'Se detecto una operacion con un monto igual o superior al umbral';
                    $alertaEfectivo->Razones = 'El monto pagado en efectivo, es igual o mayor a lo permitido';
                    $alertaEfectivo->Evidencias = $operacion->tipoDocumento;
                    $alertaEfectivo->IDReporteOP = null;
                    $alertaEfectivo->save();

                    $insMonetario = CatFormaPagos::where('IDFormaPago', $pago->IDFormaPago)->first();

                    $pagoAlerta = TbPagosAlertas::new();
                    $pagoAlerta->IDOperacionPago = $pago->IDOperacionPago;
                    $pagoAlerta->IDRegistroAlerta = $alertaEfectivo->IDRegistroAlerta;
                    $pagoAlerta->InstrumentoMonetario = $insMonetario->FormaPago;
                    $pagoAlerta->save();
                }
            }

            // ALERTA POR PERSONA POLITICAMENTE EXPUESTA (PPE)
            $esPPE = $cliente->EsPPEActivo;
            if ($esPPE) {
                $alerta = TbAlertas::new();
                $alerta->Folio = $operacion->FolioPoliza;
                $alerta->Patron = 'Operacion por monto relevante excedido';
                $alerta->IDCliente = $nombreCliente;
                $alerta->Poliza = $operacion->FolioPoliza;
                $alerta->FechaDeteccion = now();
                $alerta->IDOperacion = $operacion->IDCliente;
                $alertaEfectivo->HoraDeteccion = now()->format('H:i:s');
                $alertaEfectivo->FechaOperacion = $operacion->created_at;
                $alertaEfectivo->HoraOperacion = $horaActual;
                $alertaEfectivo->MontoOperacion = $monto;
                $alertaEfectivo->RFCAgente = $operacion->RFCAgente ?? null;
                $alertaEfectivo->Agente = $nombreAgente ?? null;
                $alertaEfectivo->Estatus = 'Operacion por monto relevante';
                $alertaEfectivo->Descripcion = 'Se detecto una operacion con un monto igual o superior al umbral';
                $alertaEfectivo->Razones = 'El monto pagado en efectivo, es igual o mayor a lo permitido';
                $alertaEfectivo->Evidencias = $operacion->tipoDocumento;
                $alertaEfectivo->IDReporteOP = null;
                $alertaEfectivo->save();

                $insMonetario = CatFormaPagos::where('IDFormaPago', $pago->IDFormaPago)->first();

                $pago = TbPagosAlertas::new();
                $pago->IDOperacionPago = $pago->IDOperacionPago;
                $pago->IDRegistroAlerta = $alertaEfectivo->IDRegistroAlerta;
                $pago->InstrumentoMonetario = $insMonetario->FormaPago;
                $pago->save();
            }

            // ALERTA POR MONTO DE PRIMA INUSUAL
            $pagosEfectivo = TbOperacionesPagos::where('IDOperacion', $operacion->IDOperacion)
                ->where('IDFormaPago', 1) // 1: Efectivo
                ->get();

            // Calcular el total pagado en efectivo
            $totalPagadoEfectivo = $pagosEfectivo->sum('Monto');
            $totalAPagar = $operacion->PrimaTotal;


            if (
                $totalPagadoEfectivo >= $totalAPagar
            ) {
                $monto = $operacion->PrimaTotal
            }











            return response()->json($operacionesPagos, 201);
        } catch (\Exception $e) {
            return response()->json([
                'codigoError' => 500,
                'error' => 'Error inesperado en el proceso de inserción de pagos',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }

    // Endpoint insertar monedas

    // Endpoint insertar forma pagos
}
