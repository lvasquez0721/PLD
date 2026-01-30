<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Helpers\ClienteHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Clientes\TbClientes;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use App\Models\ListasBloqueadas\TbListasNegrasUIF;
use App\Models\TbAlertas; // Agregar modelo de alertas

class ClientesControllerApi extends Controller
{
    public function darAltaCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'RFC' => 'required|string|max:13',
            'nombre' => 'required|string|max:100',
            'apellidoPaterno' => 'nullable|string|max:100',
            'apellidoMaterno' => 'nullable|string|max:100',
            'razonSocial' => 'nullable|string|max:255',
            'IDTipoPersona' => 'required|integer',
            'CURP' => 'nullable|string|max:18',
            'IDOcupacionGiro' => 'nullable|integer',
            'fechaNacimiento' => 'nullable|date',
            'fechaConstitucion' => 'nullable|date',
            'folioMercantil' => 'nullable|string|max:50',
            'IDNacionalidad' => 'nullable|integer',
            'IDEstadoNacimiento' => 'nullable|integer',
            'Preguntas' => 'nullable|string',
            'domicilios' => 'required|array|min:1',
            'domicilios.*.calle' => 'required_without:domicilios.*.Calle|string|max:255',
            'domicilios.*.Calle' => 'required_without:domicilios.*.calle|string|max:255',
            'domicilios.*.noExterior' => 'required|string|max:20',
            'domicilios.*.noInterior' => 'nullable|string|max:20',
            'domicilios.*.colonia' => 'required|string|max:100',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required|integer',
            'domicilios.*.municipio' => 'required|string|max:100',
            'domicilios.*.localidad' => 'nullable|string|max:100',
            'domicilios.*.telefono' => 'nullable|string|max:20',
            'domicilios.*.principal' => 'nullable|boolean',
            'IDSistemaOrigen' => 'nullable|string|max:100',
            'NoClienteSistema' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $nombreC = $validated['nombre']
                 . ' ' . ($validated['apellidoPaterno'] ?? '')
                 . ' ' . ($validated['apellidoMaterno'] ?? '');

        $tokenPPE = ClienteHelper::getTokenPPE();
        $dataPPE = ClienteHelper::getPPE($tokenPPE, $nombreC);

        $esPPE = false;
        if (
            isset($dataPPE['data']) &&
            is_array($dataPPE['data']) &&
            count($dataPPE['data']) > 0
        ) {
            $esPPE = true;
        }

        $cliente = TbClientes::create([
            "RFC" => $validated['RFC'] ?? null,
            "Nombre" => $validated['nombre'] ?? null,
            "ApellidoPaterno" => $validated['apellidoPaterno'] ?? null,
            "ApellidoMaterno" => $validated['apellidoMaterno'] ?? null,
            "RazonSocial" => $validated['razonSocial'] ?? null,
            "IDTipoPersona" => $validated['IDTipoPersona'] ?? null,
            "CURP" => $validated['CURP'] ?? null,
            "IDOcupacionGiro" => $validated['IDOcupacionGiro'] ?? null,
            "FechaNacimiento" => $validated['fechaNacimiento'] ?? null,
            "FechaConstitucion" => $validated['fechaConstitucion'] ?? null,
            "FolioMercantil" => $validated['folioMercantil'] ?? null,
            "IDNacionalidad" => $validated['IDNacionalidad'] ?? null,
            "IDEstadoNacimiento" => $validated['IDEstadoNacimiento'] ?? null,
            "Activo" => 1,
            "Preguntas" => $validated['Preguntas'] ?? null,
            "EsPPEActivo" => $esPPE,
        ]);

        // Buscar en las listas bloqueadas por RFC
        $rfc = $validated['RFC'] ?? null;
        $coincideEnListasNegras = false;
        $detalleListaBloqueadas = [];

        $listasDetectadas = []; // Guardar los nombres de listas para la alerta

        if ($rfc) {
            $CNSFrfc = TbListasNegraCNSF::where('RFC', $rfc)->first();
            if ($CNSFrfc) {
                $coincideEnListasNegras = true;
                $detalleListaBloqueadas[] = [
                    "lista" => "CNSF",
                    "nombreDetectado" => $CNSFrfc->Nombre ?? '',
                    "IDListaOrigen" => $CNSFrfc->IDRegistroListaCNSF ?? null,
                    "cargo" => "",
                    "PPEActivo" => $esPPE,
                ];
                $listasDetectadas[] = 'CNSF';
            }
            $UIFrfc = TbListasNegrasUIF::where('RFC', $rfc)->first();
            if ($UIFrfc) {
                $coincideEnListasNegras = true;
                $detalleListaBloqueadas[] = [
                    "lista" => "UIF",
                    "nombreDetectado" => $UIFrfc->Nombre ?? '',
                    "IDListaOrigen" => $UIFrfc->IDRegistroListaUIF ?? null,
                    "cargo" => "",
                    "PPEActivo" => $esPPE,
                ];
                $listasDetectadas[] = 'UIF';
            }
        }

        if ($coincideEnListasNegras) {
            $cliente->update(['CoincideEnListasNegras' => true]);

            // Crear alerta en tbAlertas
            $nombreCompleto = trim(
                ($cliente->Nombre ?? '') .
                ' ' .
                ($cliente->ApellidoPaterno ?? '') .
                ' ' .
                ($cliente->ApellidoMaterno ?? '')
            );

            $motivo = "Detectado en listas negras: " . implode(", ", $listasDetectadas);
            $razones = "El cliente con RFC {$cliente->RFC} coincide en: " . implode(", ", $listasDetectadas);

            TbAlertas::create([
                'Folio' => null,
                'Patron' => null,
                'IDCliente' => $cliente->IDCliente,
                'Cliente' => $nombreCompleto,
                'Poliza' => null,
                'FechaDeteccion' => now()->toDateString(),
                'IDOperacionPago' => null,
                'HoraDeteccion' => now()->toTimeString(),
                'FechaOperacion' => null,
                'HoraOperacion' => null,
                'MontoOperacion' => null,
                'InstrumentoMonetario' => null,
                'RFCAgente' => null,
                'Agente' => null,
                'Estatus' => 'Detectado',
                'Descripcion' => $motivo,
                'Razones' => $razones,
                'Evidencias' => json_encode($detalleListaBloqueadas),
                'IDReporteOP' => null,
                'IDPago' => null,
            ]);
        }

        return response()->json([
            'codigoError' => 0,
            'error' => 'Sin mensaje',
            'IDCliente' => $cliente->IDCliente,
            'esPPE' => $esPPE,
            'personaBloqueada' => $coincideEnListasNegras,
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
        ]);
    }


    public function actualizarCliente(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'RFC' => 'required|string|max:13',
            'nombre' => 'required|string|max:100',
            'apellidoPaterno' => 'nullable|string|max:100',
            'apellidoMaterno' => 'nullable|string|max:100',
            'razonSocial' => 'nullable|string|max:255',
            'IDTipoPersona' => 'required|integer',
            'CURP' => 'nullable|string|max:18',
            'IDOcupacionGiro' => 'nullable|integer',
            'fechaNacimiento' => 'nullable|date',
            'fechaConstitucion' => 'nullable|date',
            'folioMercantil' => 'nullable|string|max:50',
            'IDNacionalidad' => 'nullable|integer',
            'IDEstadoNacimiento' => 'nullable|integer',
            'Preguntas' => 'nullable|string',
            'domicilios' => 'sometimes|array|min:1',
            'domicilios.*.calle' => 'required_without:domicilios.*.Calle|string|max:255',
            'domicilios.*.Calle' => 'required_without:domicilios.*.calle|string|max:255',
            'domicilios.*.noExterior' => 'required|string|max:20',
            'domicilios.*.noInterior' => 'nullable|string|max:20',
            'domicilios.*.colonia' => 'required|string|max:100',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required|integer',
            'domicilios.*.municipio' => 'required|string|max:100',
            'domicilios.*.localidad' => 'nullable|string|max:100',
            'domicilios.*.telefono' => 'nullable|string|max:20',
            'domicilios.*.principal' => 'nullable|boolean',
            'IDSistemaOrigen' => 'nullable|string|max:100',
            'NoClienteSistema' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $cliente = TbClientes::find($id);

        if (!$cliente) {
            return response()->json([
                'codigoError' => 1,
                'error' => 'Cliente no encontrado'
            ], 404);
        }

        $nombreC = trim(
            $validated['nombre']
            . ' ' . ($validated['apellidoPaterno'] ?? '')
            . ' ' . ($validated['apellidoMaterno'] ?? '')
        );

        $tokenPPE = ClienteHelper::getTokenPPE();
        $dataPPE = ClienteHelper::getPPE($tokenPPE, $nombreC);

        $esPPE = false;
        if (
            isset($dataPPE['data']) &&
            is_array($dataPPE['data']) &&
            count($dataPPE['data']) > 0
        ) {
            $esPPE = true;
        }

        // Actualizar datos principales
        $cliente->RFC = $validated['RFC'];
        $cliente->Nombre = $validated['nombre'];
        $cliente->ApellidoPaterno = $validated['apellidoPaterno'] ?? null;
        $cliente->ApellidoMaterno = $validated['apellidoMaterno'] ?? null;
        $cliente->RazonSocial = $validated['razonSocial'] ?? null;
        $cliente->IDTipoPersona = $validated['IDTipoPersona'];
        $cliente->CURP = $validated['CURP'] ?? null;
        $cliente->IDOcupacionGiro = $validated['IDOcupacionGiro'] ?? null;
        $cliente->FechaNacimiento = $validated['fechaNacimiento'] ?? null;
        $cliente->FechaConstitucion = $validated['fechaConstitucion'] ?? null;
        $cliente->FolioMercantil = $validated['folioMercantil'] ?? null;
        $cliente->IDNacionalidad = $validated['IDNacionalidad'] ?? null;
        $cliente->IDEstadoNacimiento = $validated['IDEstadoNacimiento'] ?? null;
        $cliente->Preguntas = $validated['Preguntas'] ?? null;
        $cliente->EsPPEActivo = $esPPE;
        // CoincideEnListasNegras y Activo se actualizan más abajo según lógica

        // Buscar en listas bloqueadas por RFC actualizado
        $rfc = $validated['RFC'] ?? null;
        $coincideEnListasNegras = false;
        $detalleListaBloqueadas = [];
        $listasDetectadas = [];

        // Por nombre en lugar de rfc
        if ($rfc) {
            $CNSFrfc = TbListasNegraCNSF::where('RFC', $rfc)->first();
            if ($CNSFrfc) {
                $coincideEnListasNegras = true;
                $detalleListaBloqueadas[] = [
                    "lista" => "CNSF",
                    "nombreDetectado" => $CNSFrfc->Nombre ?? '',
                    "IDListaOrigen" => $CNSFrfc->IDRegistroListaCNSF ?? null,
                    "cargo" => "",
                    "PPEActivo" => $esPPE,
                ];
                $listasDetectadas[] = "CNSF";
            }
            $UIFrfc = TbListasNegrasUIF::where('RFC', $rfc)->first();
            if ($UIFrfc) {
                $coincideEnListasNegras = true;
                $detalleListaBloqueadas[] = [
                    "lista" => "UIF",
                    "nombreDetectado" => $UIFrfc->Nombre ?? '',
                    "IDListaOrigen" => $UIFrfc->IDRegistroListaUIF ?? null,
                    "cargo" => "",
                    "PPEActivo" => $esPPE,
                ];
                $listasDetectadas[] = "UIF";
            }
        }

        $cliente->CoincideEnListasNegras = $coincideEnListasNegras;
        $cliente->save();

        // Si coincide en listas negras, registrar alerta
        if ($coincideEnListasNegras) {
            $nombreCompleto = trim(
                ($cliente->Nombre ?? '') .
                ' ' .
                ($cliente->ApellidoPaterno ?? '') .
                ' ' .
                ($cliente->ApellidoMaterno ?? '')
            );

            $motivo = "Detectado en listas negras: " . implode(", ", $listasDetectadas);
            $razones = "El cliente con RFC {$cliente->RFC} coincide en: " . implode(", ", $listasDetectadas);

            TbAlertas::create([
                'Folio' => null,
                'Patron' => null,
                'IDCliente' => $cliente->IDCliente,
                'Cliente' => $nombreCompleto,
                'Poliza' => null,
                'FechaDeteccion' => now()->toDateString(),
                'IDOperacionPago' => null,
                'HoraDeteccion' => now()->toTimeString(),
                'FechaOperacion' => null,
                'HoraOperacion' => null,
                'MontoOperacion' => null,
                'InstrumentoMonetario' => null,
                'RFCAgente' => null,
                'Agente' => null,
                'Estatus' => 'Detectado',
                'Descripcion' => $motivo,
                'Razones' => $razones,
                'Evidencias' => json_encode($detalleListaBloqueadas),
                'IDReporteOP' => null,
                'IDPago' => null,
            ]);
        }

        return response()->json([
            'codigoError' => 0,
            'error' => 'Sin mensaje',
            'IDCliente' => $cliente->IDCliente,
            'esPPE' => $esPPE,
            'personaBloqueada' => $coincideEnListasNegras,
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
        ]);
    }
}
