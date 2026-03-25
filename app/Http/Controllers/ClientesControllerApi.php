<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Helpers\ClienteHelper;
use App\Models\Clientes\CatIDClientesSistema;
use App\Models\Clientes\LogClientes;
use App\Models\Clientes\LogClientesDomicilio;
use App\Models\Clientes\TbClientes;
use App\Models\Clientes\TbClientesDomicilio;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use App\Models\ListasBloqueadas\TbListasNegrasUIF;
use App\Models\TbAlertas;
use App\Services\ListasNegras\BuscadorListasIntegral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // Agregar modelo de alertas

class ClientesControllerApi extends Controller
{
    public function guardarCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'RFC' => 'nullable|string|max:18',
            'nombre' => 'sometimes|nullable|string|max:255',
            'apellidoPaterno' => 'sometimes|nullable|string|max:255',
            'apellidoMaterno' => 'sometimes|nullable|string|max:255',
            'razonSocial' => 'nullable|string|max:255',
            'IDTipoPersona' => 'required|integer',
            'CURP' => 'sometimes|nullable|string|max:18',
            'IDOcupacionGiro' => 'nullable|integer',
            'fechaNacimiento' => 'nullable|date',
            'fechaConstitucion' => 'nullable|date',
            'folioMercantil' => 'nullable|string|max:255',
            'IDNacionalidad' => 'nullable|string',
            'IDEstadoNacimiento' => 'nullable|integer',
            'Preguntas' => 'nullable|string',
            'ingresosEstimados' => 'nullable|numeric',

            'domicilios' => 'required|array|min:1',
            'domicilios.*.calle' => 'required|string|max:255',
            'domicilios.*.noExterior' => 'nullable|string|max:255',
            'domicilios.*.noInterior' => 'nullable|string|max:255',
            'domicilios.*.colonia' => 'required|string|max:255',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required|integer',
            'domicilios.*.municipio' => 'required|string|max:255',
            'domicilios.*.localidad' => 'nullable|string|max:255',
            'domicilios.*.telefono' => 'nullable|string|max:20',

            'IDSistemaOrigen' => 'nullable|integer',
            'NoClienteSistema' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'codigoError' => 1,
                'message' => 'Faltan datos obligatorios',
                'errors' => $validator->errors(),
            ], 200);
        }

        $data = $validator->validated();
        $rfc = isset($data['RFC']) ? strtoupper(trim($data['RFC'])) : null;

        // Validación de RFC duplicado
        if (! empty($rfc)) {
            if ($rfc !== 'XAXX010101000') {
                $existeRFC = TbClientes::whereRaw('UPPER(RFC) = ?', [$rfc])->exists();
                if ($existeRFC) {
                    return response()->json([
                        'codigoError' => 2,
                        'message' => 'El RFC ya se encuentra registrado.',
                        'error_code' => 'RFC_DUPLICADO',
                    ], 200);
                }
            } else {
                $nombre = isset($data['nombre']) ? strtoupper(trim($data['nombre'])) : '';
                $apellidoP = isset($data['apellidoPaterno']) ? strtoupper(trim($data['apellidoPaterno'])) : '';
                $apellidoM = isset($data['apellidoMaterno']) ? strtoupper(trim($data['apellidoMaterno'])) : '';

                $existeGenerico = TbClientes::whereRaw('UPPER(RFC) = ?', ['XAXX010101000'])
                    ->whereRaw('UPPER(Nombre) = ?', [$nombre])
                    ->whereRaw('UPPER(ApellidoPaterno) = ?', [$apellidoP])
                    ->whereRaw('UPPER(ApellidoMaterno) = ?', [$apellidoM])
                    ->exists();

                if ($existeGenerico) {
                    return response()->json([
                        'codigoError' => 2,
                        'message' => 'Ya existe un cliente con el mismo nombre y RFC genérico.',
                        'error_code' => 'RFC_GENERICO_DUPLICADO',
                    ], 200);
                }
            }
            $data['RFC'] = $rfc;
        } else {
            $data['RFC'] = null;
        }

        // Validar si el RFC existe en listas negras (UIF y CNSF)
        $personaBloqueada = false;
        $detalleListaBloqueadas = [];
        $listasDetectadas = [];

        $curp = isset($data['CURP']) ? strtoupper(trim($data['CURP'])) : null;

        if (!empty($rfc) || !empty($curp)) {
            // Buscar en UIF
            $registroUIF = TbListasNegrasUIF::where(function($q) use ($rfc, $curp) {
                if (!empty($rfc)) $q->orWhereRaw('UPPER(RFC) = ?', [$rfc]);
                if (!empty($curp)) $q->orWhereRaw('UPPER(CURP) = ?', [$curp]);
            })->first();

            if ($registroUIF) {
                $personaBloqueada = true;
                $detalleListaBloqueadas[] = [
                    'fuente' => 'UIF',
                    'IDRegistroListaUIF' => $registroUIF->IDRegistroListaUIF,
                    'Nombre' => $registroUIF->Nombre,
                    'RFC' => $registroUIF->RFC,
                    'CURP' => $registroUIF->CURP,
                ];
                $listasDetectadas[] = 'UIF';
            }

            // Buscar en CNSF
            $registroCNSF = TbListasNegraCNSF::where(function($q) use ($rfc, $curp) {
                if (!empty($rfc)) $q->orWhereRaw('UPPER(RFC) = ?', [$rfc]);
                if (!empty($curp)) $q->orWhereRaw('UPPER(CURP) = ?', [$curp]);
            })->first();

            if ($registroCNSF) {
                $personaBloqueada = true;
                $detalleListaBloqueadas[] = [
                    'fuente' => 'CNSF',
                    'IDRegistroListaCNSF' => $registroCNSF->IDRegistroListaCNSF,
                    'Nombre' => $registroCNSF->Nombre,
                    'RFC' => $registroCNSF->RFC,
                    'CURP' => $registroCNSF->CURP,
                ];
                $listasDetectadas[] = 'CNSF';
            }
        }

        $esPPE = false; // No se evalúa aquí, dejar en false

        // Si el cliente coincide en listas negras, se establece Activo = false; en caso contrario, Activo = true
        $activo = !$personaBloqueada;

        DB::beginTransaction();
        try {
            $cliente = TbClientes::create([
                'RFC' => $data['RFC'],
                'Nombre' => $data['nombre'] ?? null,
                'ApellidoPaterno' => $data['apellidoPaterno'] ?? null,
                'ApellidoMaterno' => $data['apellidoMaterno'] ?? null,
                'RazonSocial' => $data['razonSocial'] ?? null,
                'IDTipoPersona' => $data['IDTipoPersona'],
                'CURP' => $data['CURP'] ?? null,
                'IDOcupacionGiro' => $data['IDOcupacionGiro'] ?? null,
                'FechaNacimiento' => $data['fechaNacimiento'] ?? null,
                'FechaConstitucion' => $data['fechaConstitucion'] ?? null,
                'FolioMercantil' => $data['folioMercantil'] ?? null,
                'CoincideEnListasNegras' => $personaBloqueada,
                'EsPPEActivo' => $esPPE,
                'IDNacionalidad' => $data['IDNacionalidad'] ?? null,
                'IDEstadoNacimiento' => $data['IDEstadoNacimiento'] ?? null,
                'Activo' => $activo,
                'Preguntas' => $data['Preguntas'] ?? null,
                'IngresosEstimados' => $data['ingresosEstimados'] ?? null,
            ]);

            $domiciliosInsertados = [];
            foreach ($data['domicilios'] as $dom) {
                $domObj = $cliente->domicilios()->create([
                    'Calle' => $dom['calle'],
                    'NoExterior' => $dom['noExterior'] ?? null,
                    'NoInterior' => $dom['noInterior'] ?? null,
                    'Colonia' => $dom['colonia'],
                    'CP' => $dom['CP'],
                    'IDEstado' => $dom['IDEstado'],
                    'IDMunicipio' => $dom['municipio'],
                    'IDLocalidad' => $dom['localidad'] ?? null,
                    'Telefono' => $dom['telefono'] ?? null,
                ]);
                $domiciliosInsertados[] = $domObj;
            }

            if (!empty($data['IDSistemaOrigen']) && !empty($data['NoClienteSistema'])) {
                $nuevoIDOrigen = (CatIdClientesSistema::max('IDOrigenSistema') ?? 0) + 1;
                CatIdClientesSistema::create([
                    'IDOrigenSistema' => $nuevoIDOrigen,
                    'IDCliente' => $cliente->IDCliente,
                    'IDSistema' => $data['IDSistemaOrigen'],
                    'NCliente' => $data['NoClienteSistema'],
                ]);
            }

            // Emitir alerta si coincide en listas negras
            if ($personaBloqueada) {
                $nombreCompleto = trim(
                    ($cliente->Nombre ?? '') .
                    ' ' .
                    ($cliente->ApellidoPaterno ?? '') .
                    ' ' .
                    ($cliente->ApellidoMaterno ?? '')
                );

                $motivo = 'Detectado en listas negras: ' . implode(', ', $listasDetectadas);
                $razones = "El cliente con RFC {$cliente->RFC} coincide en: " . implode(', ', $listasDetectadas);

                TbAlertas::create([
                    'Folio' => null,
                    'Patron' => 'Personas Bloqueadas',
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
                    'Estatus' => 'Generado',
                    'Descripcion' => $motivo,
                    'Razones' => $razones,
                    'Evidencias' => '',
                    'IDReporteOP' => null,
                    'IDPago' => null,
                ]);
            }

            DB::commit();

            $mensajeExito = 'Cliente ingresado exitosamente';
            if ($personaBloqueada) {
                $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
            }

            return response()->json([
                'codigoError' => 0,
                'message' => $mensajeExito,
                'IDCliente' => $cliente->IDCliente,
                'esPPE' => $cliente->EsPPEActivo,
                'personaBloqueada' => $cliente->CoincideEnListasNegras,
                'detalleListaBloqueadas' => $detalleListaBloqueadas,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al guardar cliente: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'codigoError' => 1,
                'message' => 'Error al guardar los datos en BD: '.$e->getMessage(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 200);
        }
    }

    public function actualizarCliente(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'RFC' => 'nullable|string|max:18',
            'nombre' => 'nullable|string|max:255',
            'apellidoPaterno' => 'nullable|string|max:255',
            'apellidoMaterno' => 'nullable|string|max:255',
            'razonSocial' => 'nullable|string|max:255',
            'IDTipoPersona' => 'required|integer',
            'CURP' => 'nullable|string|max:18',
            'IDOcupacionGiro' => 'nullable|integer',
            'fechaNacimiento' => 'nullable|date',
            'fechaConstitucion' => 'nullable|date',
            'folioMercantil' => 'nullable|string|max:255',
            'IDNacionalidad' => 'nullable|string',
            'IDEstadoNacimiento' => 'nullable|integer',
            'Preguntas' => 'nullable|string',
            'ingresosEstimados' => 'nullable|numeric',

            'domicilios' => 'required|array|min:1',
            'domicilios.*.calle' => 'required|string|max:255',
            'domicilios.*.noExterior' => 'nullable|string|max:255',
            'domicilios.*.noInterior' => 'nullable|string|max:255',
            'domicilios.*.colonia' => 'required|string|max:255',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required|integer',
            'domicilios.*.municipio' => 'required|string|max:255',
            'domicilios.*.localidad' => 'nullable|string|max:255',
            'domicilios.*.telefono' => 'nullable|string|max:20',
            // 'domicilios.*.principal' => 'nullable|boolean',

            'IDSistemaOrigen' => 'nullable|integer',
            'NoClienteSistema' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'codigoError' => 1,
                'message' => 'Faltan datos obligatorios',
                'errors' => $validator->errors(),
            ], 200);
        }

        $validated = $validator->validated();

        $cliente = TbClientes::find($id);

        if (! $cliente) {
            return response()->json([
                'codigoError' => 1,
                'message' => 'Cliente no encontrado',
            ], 200);
        }

        // Guardar el estado actual del cliente en el log antes de la actualización
        LogClientes::create([
            'IDCliente' => $cliente->IDCliente,
            'RfcAnterior' => $cliente->RFC,
            'Nombre' => $cliente->Nombre,
            'ApellidoPaterno' => $cliente->ApellidoPaterno,
            'ApellidoMaterno' => $cliente->ApellidoMaterno,
            'RazonSocial' => $cliente->RazonSocial,
            'IDTipoPersona' => $cliente->IDTipoPersona,
            'CURP' => $cliente->CURP,
            'IDOcupacionGiro' => $cliente->IDOcupacionGiro,
            'FechaNacimiento' => $cliente->FechaNacimiento,
            'FechaConstitucion' => $cliente->FechaConstitucion,
            'FolioMercantil' => $cliente->FolioMercantil,
            'CoincideEnListasNegras' => $cliente->CoincideEnListasNegras,
            'EsPPEActivo' => $cliente->EsPPEActivo,
            'IDNacionalidad' => $cliente->IDNacionalidad,
            'IDEstadoNacimiento' => $cliente->IDEstadoNacimiento,
            'Activo' => $cliente->Activo,
            'TimeStampLog' => now(),
        ]);

        // Guardar el estado actual de los domicilios del cliente en el log antes de la actualización
        $domiciliosOriginales = TbClientesDomicilio::where('IDCliente', $cliente->IDCliente)->get();
        foreach ($domiciliosOriginales as $domicilio) {
            // Obtener el siguiente valor IDLogDomicilio manualmente
            $maxIdLog = LogClientesDomicilio::max('IDLogDomicilio');
            $nextIdLog = $maxIdLog ? $maxIdLog + 1 : 1;

            LogClientesDomicilio::create([
                'IDLogDomicilio' => $nextIdLog,
                'IDDomicilio' => $domicilio->IDDomicilio,
                'IDCliente' => $domicilio->IDCliente,
                'Calle' => $domicilio->Calle,
                'NoExterior' => $domicilio->NoExterior,
                'NoInterior' => $domicilio->NoInterior,
                'Colonia' => $domicilio->Colonia,
                'CP' => $domicilio->CP,
                'IDEstado' => $domicilio->IDEstado,
                'Municipio' => $domicilio->Municipio,
                'Localidad' => $domicilio->Localidad,
                'Telefono' => $domicilio->Telefono,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Eliminar domicilios existentes para el cliente
        TbClientesDomicilio::where('IDCliente', $cliente->IDCliente)->delete();

        // Insertar los nuevos domicilios
        if (isset($validated['domicilios'])) {
            foreach ($validated['domicilios'] as $domicilioData) {
                $calle = $domicilioData['calle'] ?? $domicilioData['Calle'] ?? null;
                if ($calle) {
                    TbClientesDomicilio::create([
                        'IDCliente' => $cliente->IDCliente,
                        'Calle' => $calle,
                        'NoExterior' => $domicilioData['noExterior'] ?? null,
                        'NoInterior' => $domicilioData['noInterior'] ?? null,
                        'Colonia' => $domicilioData['colonia'] ?? null,
                        'CP' => $domicilioData['CP'] ?? null,
                        'IDEstado' => $domicilioData['IDEstado'] ?? null,
                        'Municipio' => $domicilioData['municipio'] ?? null,
                        'Localidad' => $domicilioData['localidad'] ?? null,
                        'Telefono' => $domicilioData['telefono'] ?? null,
                        'Principal' => $domicilioData['principal'] ?? false,
                    ]);
                }
            }
        }

        // Si el json body no incluye 'nombre', 'apellidoPaterno', 'apellidoMaterno', o 'CURP',
        // usamos los valores actuales del cliente de la BD en vez de null
        $nombre = array_key_exists('nombre', $validated) ? $validated['nombre'] : $cliente->Nombre;
        $apellidoPaterno = array_key_exists('apellidoPaterno', $validated) ? $validated['apellidoPaterno'] : $cliente->ApellidoPaterno;
        $apellidoMaterno = array_key_exists('apellidoMaterno', $validated) ? $validated['apellidoMaterno'] : $cliente->ApellidoMaterno;
        $curp = array_key_exists('CURP', $validated) ? $validated['CURP'] : $cliente->CURP;

        // Para PPE, armar nombre completo lo mejor posible
        $nombreC = trim(
            ($nombre ? $nombre : '')
            .' '.($apellidoPaterno ? $apellidoPaterno : '')
            .' '.($apellidoMaterno ? $apellidoMaterno : '')
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

        // Actualizar datos principales, si llegaron campos nuevos, si no dejar lo original
        $cliente->RFC = array_key_exists('RFC', $validated) ? $validated['RFC'] : $cliente->RFC;
        $cliente->Nombre = array_key_exists('nombre', $validated) ? $validated['nombre'] : $cliente->Nombre;
        $cliente->ApellidoPaterno = array_key_exists('apellidoPaterno', $validated) ? $validated['apellidoPaterno'] : $cliente->ApellidoPaterno;
        $cliente->ApellidoMaterno = array_key_exists('apellidoMaterno', $validated) ? $validated['apellidoMaterno'] : $cliente->ApellidoMaterno;
        $cliente->RazonSocial = array_key_exists('razonSocial', $validated) ? $validated['razonSocial'] : $cliente->RazonSocial;
        $cliente->IDTipoPersona = $validated['IDTipoPersona'];
        $cliente->CURP = array_key_exists('CURP', $validated) ? $validated['CURP'] : $cliente->CURP;
        $cliente->IDOcupacionGiro = array_key_exists('IDOcupacionGiro', $validated) ? $validated['IDOcupacionGiro'] : $cliente->IDOcupacionGiro;
        $cliente->FechaNacimiento = array_key_exists('fechaNacimiento', $validated) ? $validated['fechaNacimiento'] : $cliente->FechaNacimiento;
        $cliente->FechaConstitucion = array_key_exists('fechaConstitucion', $validated) ? $validated['fechaConstitucion'] : $cliente->FechaConstitucion;
        $cliente->FolioMercantil = array_key_exists('folioMercantil', $validated) ? $validated['folioMercantil'] : $cliente->FolioMercantil;
        $cliente->IDNacionalidad = array_key_exists('IDNacionalidad', $validated) ? $validated['IDNacionalidad'] : $cliente->IDNacionalidad;
        $cliente->IDEstadoNacimiento = array_key_exists('IDEstadoNacimiento', $validated) ? $validated['IDEstadoNacimiento'] : $cliente->IDEstadoNacimiento;
        $cliente->Preguntas = array_key_exists('Preguntas', $validated) ? $validated['Preguntas'] : $cliente->Preguntas;
        $cliente->EsPPEActivo = $esPPE;

        // CoincideEnListasNegras y Activo se actualizan más abajo según lógica

        // Actualizar/crear CatIdClientesSistema relacionado si vienen los datos de sistema origen
        if (! empty($validated['IDSistemaOrigen']) && ! empty($validated['NoClienteSistema'])) {
            $catIdSistema = CatIdClientesSistema::where('IDCliente', $cliente->IDCliente)
                ->where('IDSistema', $validated['IDSistemaOrigen'])
                ->first();

            if ($catIdSistema) {
                $catIdSistema->NCliente = $validated['NoClienteSistema'];
                $catIdSistema->save();
            } else {
                $nuevoIDOrigen = (CatIdClientesSistema::max('IDOrigenSistema') ?? 0) + 1;
                CatIdClientesSistema::create([
                    'IDOrigenSistema' => $nuevoIDOrigen,
                    'IDCliente' => $cliente->IDCliente,
                    'IDSistema' => $validated['IDSistemaOrigen'],
                    'NCliente' => $validated['NoClienteSistema'],
                ]);
            }
        }

        // Buscar en listas bloqueadas por RFC o CURP actualizados
        $rfc = array_key_exists('RFC', $validated) ? $validated['RFC'] : $cliente->RFC;
        $curp = array_key_exists('CURP', $validated) ? $validated['CURP'] : $cliente->CURP;
        $coincideEnListasNegras = false;
        $detalleListaBloqueadas = [];
        $listasDetectadas = [];

        if ($rfc || $curp) {
            $CNSFrfc = TbListasNegraCNSF::where(function($q) use ($rfc, $curp) {
                if ($rfc) $q->orWhere('RFC', $rfc);
                if ($curp) $q->orWhere('CURP', $curp);
            })->first();

            if ($CNSFrfc) {
                $coincideEnListasNegras = true;
                $detalleListaBloqueadas[] = [
                    'lista' => 'CNSF',
                    'nombreDetectado' => $CNSFrfc->Nombre ?? '',
                    'IDListaOrigen' => $CNSFrfc->IDRegistroListaCNSF ?? null,
                    'cargo' => '',
                    'PPEActivo' => $esPPE,
                ];
                $listasDetectadas[] = 'CNSF';
            }

            $UIFrfc = TbListasNegrasUIF::where(function($q) use ($rfc, $curp) {
                if ($rfc) $q->orWhere('RFC', $rfc);
                if ($curp) $q->orWhere('CURP', $curp);
            })->first();

            if ($UIFrfc) {
                $coincideEnListasNegras = true;
                $detalleListaBloqueadas[] = [
                    'lista' => 'UIF',
                    'nombreDetectado' => $UIFrfc->Nombre ?? '',
                    'IDListaOrigen' => $UIFrfc->IDRegistroListaUIF ?? null,
                    'cargo' => '',
                    'PPEActivo' => $esPPE,
                ];
                $listasDetectadas[] = 'UIF';
            }
        }

        $cliente->CoincideEnListasNegras = $coincideEnListasNegras;
        if ($coincideEnListasNegras) {
            $cliente->Activo = 0;
        }
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

            $motivo = 'Detectado en listas negras: ' . implode(', ', $listasDetectadas);
            $razones = "El cliente con RFC {$cliente->RFC} coincide en: " . implode(', ', $listasDetectadas);

            TbAlertas::create([
                'Folio' => null,
                'Patron' => 'Personas Bloqueadas',
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
                'Evidencias' => '',
                'IDReporteOP' => null,
                'IDPago' => null,
            ]);
        }

        $mensajeExito = 'Cliente actualizado exitosamente';
        if ($coincideEnListasNegras) {
            $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
        }

        return response()->json([
            'codigoError' => 0,
            'message' => $mensajeExito,
            'IDCliente' => $cliente->IDCliente,
            'esPPE' => $esPPE,
            'personaBloqueada' => $coincideEnListasNegras,
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
        ], 200);
    }
}
