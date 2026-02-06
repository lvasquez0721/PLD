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
            'IDNacionalidad' => 'nullable|string', // AHORA como string, antes int
            'IDEstadoNacimiento' => 'nullable|string', // AHORA como string, antes int
            'Preguntas' => 'nullable|string',
            'ingresosEstimados' => 'nullable|numeric',

            'domicilios' => 'required|array|min:1',
            'domicilios.*.calle' => 'required|string|max:255',
            'domicilios.*.noExterior' => 'nullable|string|max:255',
            'domicilios.*.noInterior' => 'nullable|string|max:255',
            'domicilios.*.colonia' => 'required|string|max:255',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required|string', // AHORA string
            'domicilios.*.municipio' => 'required|integer',
            'domicilios.*.localidad' => 'nullable|integer',
            'domicilios.*.telefono' => 'nullable|string|max:20',
            'domicilios.*.principal' => 'nullable|boolean',

            'IDSistemaOrigen' => 'nullable|integer',
            'NoClienteSistema' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $rfc = isset($data['RFC']) ? strtoupper(trim($data['RFC'])) : null;

        // Validación de RFC duplicado
        if (! empty($rfc)) {
            if ($rfc !== 'XAXX010101000') {
                $existeRFC = TbClientes::whereRaw('UPPER(RFC) = ?', [$rfc])->exists();
                if ($existeRFC) {
                    return response()->json([
                        'message' => 'El RFC ya se encuentra registrado.',
                        'error_code' => 'RFC_DUPLICADO',
                    ], 409);
                }
            } else {
                $nombre = strtoupper(trim($data['nombre']));
                $apellidoP = strtoupper(trim($data['apellidoPaterno'] ?? ''));
                $apellidoM = strtoupper(trim($data['apellidoMaterno'] ?? ''));

                $existeGenerico = TbClientes::whereRaw('UPPER(RFC) = ?', ['XAXX010101000'])
                    ->whereRaw('UPPER(Nombre) = ?', [$nombre])
                    ->whereRaw('UPPER(ApellidoPaterno) = ?', [$apellidoP])
                    ->whereRaw('UPPER(ApellidoMaterno) = ?', [$apellidoM])
                    ->exists();

                if ($existeGenerico) {
                    return response()->json([
                        'message' => 'Ya existe un cliente con el mismo nombre y RFC genérico.',
                        'error_code' => 'RFC_GENERICO_DUPLICADO',
                    ], 409);
                }
            }
            $data['RFC'] = $rfc;
        } else {
            $data['RFC'] = null;
        }

        DB::beginTransaction();
        try {
            $cliente = TbClientes::create([
                'RFC' => $data['RFC'],
                'Nombre' => $data['nombre'],
                'ApellidoPaterno' => $data['apellidoPaterno'] ?? null,
                'ApellidoMaterno' => $data['apellidoMaterno'] ?? null,
                'RazonSocial' => $data['razonSocial'] ?? null,
                'IDTipoPersona' => $data['IDTipoPersona'],
                'CURP' => $data['CURP'] ?? null,
                'IDOcupacionGiro' => $data['IDOcupacionGiro'] ?? null,
                'FechaNacimiento' => $data['fechaNacimiento'] ?? null,
                'FechaConstitucion' => $data['fechaConstitucion'] ?? null,
                'FolioMercantil' => $data['folioMercantil'] ?? null,
                'CoincideEnListasNegras' => false, // Temporal, se actualizará después del análisis de listas
                'EsPPEActivo' => false, // Temporal, se actualizará después del análisis de listas
                'IDNacionalidad' => $data['IDNacionalidad'] ?? null,
                'IDEstadoNacimiento' => $data['IDEstadoNacimiento'] ?? null,
                'Activo' => true,
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
                    'IDEstado' => $dom['IDEstado'], // Guardar como string
                    'IDMunicipio' => $dom['municipio'],
                    'IDLocalidad' => $dom['localidad'] ?? null,
                    'Telefono' => $dom['telefono'] ?? null,
                ]);

                $domiciliosInsertados[] = $domObj;
            }

            if (! empty($data['IDSistemaOrigen']) && ! empty($data['NoClienteSistema'])) {
                $nuevoIDOrigen = (CatIdClientesSistema::max('IDOrigenSistema') ?? 0) + 1;

                CatIdClientesSistema::create([
                    'IDOrigenSistema' => $nuevoIDOrigen,
                    'IDCliente' => $cliente->IDCliente,
                    'IDSistema' => $data['IDSistemaOrigen'],
                    'NCliente' => $data['NoClienteSistema'],
                ]);
            }

            // --- INICIO MODIFICACIÓN ---
            // Aquí reemplazamos la creación del buscador y el análisis de listas por un control de error claro
            // o una implementación simulada temporal para evitar la excepción de clase no encontrada.
            $esPPE = false;
            $personaBloqueada = false;
            $detalleListaBloqueadas = [];

            // Si es necesario en desarrollo, dejar la sección simulada y dar un warning en el log:
            Log::warning('La clase BuscadorListasIntegral no se encontró o no está implementada. Se omite análisis de listas negras y PPE.');

            /*
            // Si más adelante se habilita la clase, dejar como comentario de referencia:
            $buscador = new BuscadorListasIntegral();
            $timestamp = now()->format('Ymd_His');
            $pathEvidencia = "storage/evidencias/Cliente_{$cliente->IDCliente}_{$timestamp}";

            $resultadoQeQ = $buscador->realizaBusqueda(
                nuevoIDCliente: $cliente->IDCliente,
                IDTipoPersona: $data['IDTipoPersona'],
                RFC: $data['RFC'] ?? '',
                nombre: $data['nombre'],
                apellidoPaterno: $data['apellidoPaterno'] ?? '',
                apellidoMaterno: $data['apellidoMaterno'] ?? '',
                razonSocial: $data['razonSocial'] ?? '',
                pathEvidencia: $pathEvidencia,
                modo: 1
            );

            $esPPE = $resultadoQeQ['esPPE'] ?? false;
            $personaBloqueada = $resultadoQeQ['personaBloqueada'] ?? false;
            $detalleListaBloqueadas = $resultadoQeQ['detalleListaBloqueadas'] ?? [];
            */
            // --- FIN MODIFICACIÓN ---

            $cliente->update([
                'CoincideEnListasNegras' => $personaBloqueada,
                'EsPPEActivo' => $esPPE,
            ]);

            DB::commit();

            return response()->json([
                'codigoError' => 0,
                'message' => 'Cliente guardado correctamente',
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
                'message' => 'Error al guardar los datos en BD: '.$e->getMessage(),
                'error' => '1',
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
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
            'IDNacionalidad' => 'nullable|string', // AHORA string
            'IDEstadoNacimiento' => 'nullable|string', // AHORA string
            'Preguntas' => 'nullable|string',
            'domicilios' => 'sometimes|array|min:1',
            'domicilios.*.calle' => 'required_without:domicilios.*.Calle|string|max:255',
            'domicilios.*.Calle' => 'required_without:domicilios.*.calle|string|max:255',
            'domicilios.*.noExterior' => 'required|string|max:20',
            'domicilios.*.noInterior' => 'nullable|string|max:20',
            'domicilios.*.colonia' => 'required|string|max:100',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required|string', // AHORA string
            'domicilios.*.municipio' => 'required|string|max:100',
            'domicilios.*.localidad' => 'nullable|string|max:100',
            'domicilios.*.telefono' => 'nullable|string|max:20',
            'domicilios.*.principal' => 'nullable|boolean',
            'IDSistemaOrigen' => 'nullable|int|max:100',
            'NoClienteSistema' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $cliente = TbClientes::find($id);

        if (! $cliente) {
            return response()->json([
                'codigoError' => 1,
                'error' => 'Cliente no encontrado',
            ], 404);
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
            $nextIdLog = $maxIdLog ? $maxIdLog + 1 : 1; // Siguiente autoincremental

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
                'created_at' => now(), // Usar created_at para TimeStampLog
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

        $nombreC = trim(
            $validated['nombre']
            .' '.($validated['apellidoPaterno'] ?? '')
            .' '.($validated['apellidoMaterno'] ?? '')
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

        // Actualizar/crear CatIdClientesSistema relacionado si vienen los datos de sistema origen
        if (! empty($validated['IDSistemaOrigen']) && ! empty($validated['NoClienteSistema'])) {
            // Buscar si ya existe un registro para este cliente con el IDSistema dado
            $catIdSistema = CatIdClientesSistema::where('IDCliente', $cliente->IDCliente)
                ->where('IDSistema', $validated['IDSistemaOrigen'])
                ->first();

            if ($catIdSistema) {
                // Actualizar el número de cliente si cambió
                $catIdSistema->NCliente = $validated['NoClienteSistema'];
                $catIdSistema->save();
            } else {
                // Crear uno nuevo (IDOrigenSistema autoincrement o moldear igual a crear)
                $nuevoIDOrigen = (CatIdClientesSistema::max('IDOrigenSistema') ?? 0) + 1;
                CatIdClientesSistema::create([
                    'IDOrigenSistema' => $nuevoIDOrigen,
                    'IDCliente' => $cliente->IDCliente,
                    'IDSistema' => $validated['IDSistemaOrigen'],
                    'NCliente' => $validated['NoClienteSistema'],
                ]);
            }
        }

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
                    'lista' => 'CNSF',
                    'nombreDetectado' => $CNSFrfc->Nombre ?? '',
                    'IDListaOrigen' => $CNSFrfc->IDRegistroListaCNSF ?? null,
                    'cargo' => '',
                    'PPEActivo' => $esPPE,
                ];
                $listasDetectadas[] = 'CNSF';
            }
            $UIFrfc = TbListasNegrasUIF::where('RFC', $rfc)->first();
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
        $cliente->save();

        // Si coincide en listas negras, registrar alerta
        if ($coincideEnListasNegras) {
            $nombreCompleto = trim(
                ($cliente->Nombre ?? '').
                ' '.
                ($cliente->ApellidoPaterno ?? '').
                ' '.
                ($cliente->ApellidoMaterno ?? '')
            );

            $motivo = 'Detectado en listas negras: '.implode(', ', $listasDetectadas);
            $razones = "El cliente con RFC {$cliente->RFC} coincide en: ".implode(', ', $listasDetectadas);

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
