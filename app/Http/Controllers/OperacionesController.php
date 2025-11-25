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
        //
        $request->validate([
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
            'PPE' => 'required|boolean', // Agregar campo PPE

            //Detalle de Beneficiarios
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

        // operacion
        $operacion = new TbOperaciones();
        $operacion->IDCliente = $request->IDCliente;
        $operacion->FolioPoliza = $request->FolioPoliza;
        $operacion->FolioEndoso = $request->FolioEndoso;
        $operacion->FechaEmision = $request->FechaEmision;
        $operacion->PrimaTotal = $request->PrimaTotal;
        $operacion->GastosEmision = $request->GastosEmision;
        $operacion->RFCAgente = $request->RFCAgente;
        $operacion->CURPAgente = $request->CURPAgente;
        $operacion->NombreAgente = $request->NombreAgente;
        $operacion->APaternoAgente = $request->APaternoAgente;
        $operacion->AMaternoAgente = $request->AMaternoAgente;
        $operacion->tipoDocumento = $request->tipoDocumento;
        $operacion->save();

        // beneficiarios
        $beneficiarios = $request->DetalleBeneficiarios;
        foreach ($beneficiarios as $beneficiario) {
            $beneficiarioModel = new TbOperacionesBeneficiarios();
            $beneficiarioModel->IDOperacion = $operacion->IDOperacion;
            $beneficiarioModel->RFC = $beneficiario['RFC'];
            $beneficiarioModel->CURP = $beneficiario['CURP'];
            $beneficiarioModel->nombre = $beneficiario['nombre'];
            $beneficiarioModel->apellidoPaterno = $beneficiario['apellidoPaterno'];
            $beneficiarioModel->apellidoMaterno = $beneficiario['apellidoMaterno'];
            $beneficiarioModel->razonSocial = $beneficiario['razonSocial'];
            $beneficiarioModel->preferente = $beneficiario['preferente'];
            $beneficiarioModel->porcentajeParticipacion = $beneficiario['porcentajeParticipacion'];
            $beneficiarioModel->save();
        }

        return response()->json($operacion);
    }

    // -------------
    //PAGO
    public function insertarOperacionPago(Request $request)
    {
        $request->validate([
            'IDOperacion' => 'required|integer',
            'Monto' => 'required|numeric',
            'IDMoneda' => 'required|integer',
            'IDFormaPago' => 'required|integer',
            'TipoCambio' => 'required|numeric',
            'FechaPago' => 'required|date',
            'PPE' => 'required|boolean'
        ]);

        $operacion = TbOperaciones::find($request->IDOperacion);

        // Generar pago
        $operacionPago = new TbOperacionesPagos();

        $operacionPago->Monto = $request->Monto;
        $operacionPago->IDMoneda = $request->IDMoneda;
        $operacionPago->IDFormaPago = $request->IDFormaPago;
        $operacionPago->TipoCambio = $request->TipoCambio;
        $operacionPago->FechaPago = $request->FechaPago;

        $operacionPago->save();

        // Si el campo "PPE" = true, entonces ...
        if ($request->PPE === true) {
            // Aqui ubico al cliente
            $cliente = TbClientes::find($operacion->IDCliente);
            $nombreCli = $cliente->Nombre . ' ' . $cliente->ApellidoPaterno . ' ' . $cliente->ApellidoMaterno;

            // Agente
            $agente = $operacion->NombreAgente + ' ' + $operacion->APaternoAgente + ' ' + $operacion->AMaternoAgente;

            //Fecha y hora actual
            $fechaA = now()->format('Y-m-d');
            $horaA = now()->format('H:i:s');


            // ------------------------
            //Aquí se genera la alerta
            $alerta = new TbAlertas();
            $alerta->Folio = $operacion->FolioEndoso;
            // $alerta->Patron = ???
            $alerta->IDCliente = $operacion->IDCliente;
            $alerta->Cliente = $nombreCli;
            $alerta->Poliza = $operacion->FolioPoliza;
            $alerta->FechaDeteccion = $fechaA;
            $alerta->IDOperacionPago = $operacionPago->IDOperacionPago;
            $alerta->HoraDeteccion = $horaA;
            $alerta->FechaOperacion = $operacion->FechaEmision;
            // $alerta->HoraOperacion = ????
            $alerta->MontoOperacion = $operacion->PrimaTotal; // ????
            // $alerta->InstrumentoMonetario = ???
            $alerta->RFCAgente = $operacion->RFCAgente;
            $alerta->Agente = $agente;
            // $alerta->Estatus = ???
            // $alerta->Descripcion = ???
            // $alerta->Razones = ???
            // $alerta->Evidencias = ???
            // $alerta->IDReporteOP = ???
            $alerta->IDPago = $operacionPago->IDOperacionPago;
            $alerta->save();
        };

        return response()->json($operacionPago);
    }

    // Endpoint insertar monedas

    // Endpoint insertar forma pagos
}
