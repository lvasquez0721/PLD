<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbOperacionesBeneficiarios;
use App\Models\TbOperacionesPagos;
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
