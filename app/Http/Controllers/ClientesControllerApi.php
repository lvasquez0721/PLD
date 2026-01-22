<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Clientes\TbClientes;
use App\Models\Clientes\TbClientesDomicilio;
use App\Models\Clientes\CatIDClientesSistema;
use App\Services\ListasNegras\BuscadorListasIntegral;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientesControllerApi extends Controller {
    public function storeMasivo(Request $request) {
        $clientesData = $request->input('clientes');

        if (!is_array($clientesData)) {
            return response()->json([
                'message' => 'El parámetro "clientes" debe ser un arreglo de objetos de cliente.'
            ], 400);
        }

        $reglas = [
            'IDSolicitante'    => 'nullable|numeric',
            'no_cliente'       => 'required|string|unique:clientes,no_cliente',
            'nombre'           => 'required|string|max:255',
            'apellido_p'       => 'required|string|max:255',
            'apellido_m'       => 'nullable|string|max:255',
            'tipo_persona'     => 'required|in:FISICA,MORAL',
            'curp'             => 'nullable|string|max:18',
            'rfc'              => 'nullable|string|max:13',
            'ocupacion_giro'   => 'nullable|string|max:255',
            'estado_radica'    => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'edad'             => 'nullable|integer|min:0',
            'calle'            => 'nullable|string|max:255',
            'no_exterior'      => 'nullable|string|max:16',
            'colonia'          => 'nullable|string|max:255',
            'cp'               => 'nullable|string|max:8',
            'id_estado'        => 'nullable|integer',
            'id_municipio'     => 'nullable|integer',
            'id_localidad'     => 'nullable|integer',
        ];

        $errores = [];
        $clientesInsertados = [];
        foreach ($clientesData as $i => $cliente) {
            $validator = Validator::make($cliente, $reglas);

            if ($validator->fails()) {
                $errores[$i] = $validator->errors()->all();
                continue;
            }
            $clientesInsertados[] = $cliente;
        }

        if (!empty($errores)) {
            return response()->json([
                'message' => 'Algunos clientes no cumplen los criterios de validación.',
                'errores' => $errores,
            ], 422);
        }

        try {
            $insertados = \App\Models\Cliente::insert($clientesInsertados);

            return response()->json([
                'message' => 'Clientes insertados correctamente.',
                'insertados' => $clientesInsertados
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al insertar los clientes: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function guardarCliente(Request $request) {
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
            'IDNacionalidad' => 'nullable|integer',
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
            'domicilios.*.municipio' => 'required|integer',
            'domicilios.*.localidad' => 'nullable|integer',
            'domicilios.*.telefono' => 'nullable|string|max:20',
            'domicilios.*.principal' => 'nullable|boolean',

            'IDSistemaOrigen' => 'nullable|integer',
            'NoClienteSistema' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $rfc = isset($data['RFC']) ? strtoupper(trim($data['RFC'])) : null;

        // Validación de RFC duplicado
        if (!empty($rfc)) {
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
            $nuevoIDCliente = (TbClientes::max('IDCliente') ?? 0) + 1;

            $cliente = TbClientes::create([
                'IDCliente' => $nuevoIDCliente,
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
                'IngresosEstimados' => $data['ingresosEstimados'] ?? null
            ]);

            $domiciliosInsertados = [];
            foreach ($data['domicilios'] as $dom) {
                $nuevoIDDomicilio = (TbClientesDomicilio::max('IDDomicilio') ?? 0) + 1;

                $domObj = TbClientesDomicilio::create([
                    'IDDomicilio' => $nuevoIDDomicilio,
                    'IDCliente'   => $cliente->IDCliente,
                    'Calle'       => $dom['calle'],
                    'NoExterior'  => $dom['noExterior'] ?? null,
                    'NoInterior'  => $dom['noInterior'] ?? null,
                    'Colonia'     => $dom['colonia'],
                    'CP'          => $dom['CP'],
                    'IDEstado'    => $dom['IDEstado'],
                    'IDMunicipio' => $dom['municipio'],
                    'IDLocalidad' => $dom['localidad'] ?? null,
                    'Telefono'    => $dom['telefono'] ?? null,
                ]);

                $domiciliosInsertados[] = $domObj;
            }

            if (!empty($data['IDSistemaOrigen']) && !empty($data['NoClienteSistema'])) {
                $nuevoIDOrigen = (CatIdClientesSistema::max('IDOrigenSistema') ?? 0) + 1;

                CatIdClientesSistema::create([
                    'IDOrigenSistema' => $nuevoIDOrigen,
                    'IDCliente'       => $cliente->IDCliente,
                    'IDSistema'       => $data['IDSistemaOrigen'],
                    'NCliente'        => $data['NoClienteSistema'],
                ]);
            }

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
            Log::error('Error al guardar cliente: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al guardar los datos en BD.',
                'error' => '1',
            ], 500);
        }
    }

}
