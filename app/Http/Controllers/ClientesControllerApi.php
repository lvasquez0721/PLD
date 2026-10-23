<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Helpers\ClienteHelper;
use App\Models\CatCategoriaPersonasBloqueadas;
use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatIDClientesSistema;
use App\Models\Clientes\CatSistemas;
use App\Models\Clientes\CatTipoPersona;
use App\Models\Clientes\LogClientes;
use App\Models\Clientes\LogClientesDomicilio;
use App\Models\Clientes\LogDetectClientesListas;
use App\Models\Clientes\TbClientes;
use App\Models\Clientes\TbClientesDomicilio;
use App\Models\Clientes\TbClientesPPE;
use App\Models\TbAlertas;
use App\Services\ListasNegras\AnalizadorListasNegras;
use App\Services\NotificacionCumplimientoService;
use App\Services\PLD\EvaluadorRiesgoCliente;
use App\Services\ParametriaPLD\ParametriaPLDService;
use App\Support\AuditDate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator; // Agregar modelo de alertas

class ClientesControllerApi extends Controller
{
    public function guardarCliente(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'RFC' => 'required|string|max:18',
            'nombre' => 'sometimes|nullable|string|max:255',
            'apellidoPaterno' => 'sometimes|nullable|string|max:255',
            'apellidoMaterno' => 'sometimes|nullable|string|max:255',
            'razonSocial' => 'nullable|string|max:255',
            'IDTipoPersona' => 'required|integer|exists:catTipoPersona,IDTipoPersona',
            'CURP' => 'sometimes|nullable|string|max:18',
            'IDOcupacionGiro' => 'nullable|integer',
            'fechaNacimiento' => 'nullable|date',
            'fechaConstitucion' => 'nullable|date',
            'folioMercantil' => 'nullable|string|max:255',
            'IDNacionalidad' => 'required|string',
            'IDEstadoNacimiento' => 'nullable',
            'Preguntas' => 'nullable|string',
            'ingresosEstimados' => 'nullable|numeric',

            'domicilios' => 'required|array|min:1',
            'domicilios.*.calle' => 'required|string|max:255',
            'domicilios.*.noExterior' => 'nullable|string|max:255',
            'domicilios.*.noInterior' => 'nullable|string|max:255',
            'domicilios.*.colonia' => 'required|string|max:255',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required',
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
            ], 422);
        }

        $data = $validator->validated();
        $rfc = isset($data['RFC']) ? strtoupper(trim($data['RFC'])) : null;

        // Resolver IDEstadoNacimiento (acepta "9", "09", clave de entidad o nombre del estado) - tolera string o int
        $idEstadoNacimientoResuelto = null;

        if (! empty($data['IDEstadoNacimiento'])) {
            $idEstadoNacimientoResuelto = $this->resolveIDEstado($data['IDEstadoNacimiento']);

            if ($idEstadoNacimientoResuelto === null) {
                return response()->json([
                    'codigoError' => 1,
                    'message' => 'El valor de IDEstadoNacimiento no es válido.',
                    'errors' => [
                        'IDEstadoNacimiento' => ['El valor "'.$data['IDEstadoNacimiento'].'" no corresponde a ningún estado del catálogo.'],
                    ],
                ], 422);
            }
        }

        // Resolver IDEstado de cada domicilio (acepta string "9"/"09"/"Nayarit"/"Ciudad de México" o int 9)
        if (! empty($data['domicilios']) && is_array($data['domicilios'])) {
            foreach ($data['domicilios'] as $idx => $dom) {
                $valorEstado = $dom['IDEstado'] ?? null;
                $resuelto = $this->resolveIDEstado($valorEstado);
                if ($resuelto === null) {
                    return response()->json([
                        'codigoError' => 1,
                        'message' => 'El valor de IDEstado en domicilios no es válido.',
                        'errors' => [
                            "domicilios.$idx.IDEstado" => ['El valor "'.$valorEstado.'" no corresponde a ningún estado del catálogo.'],
                        ],
                    ], 422);
                }
                $data['domicilios'][$idx]['IDEstado'] = $resuelto;
            }
        }

        // Validación de RFC duplicado
        if (! empty($rfc)) {
            if ($rfc !== 'XAXX010101000') {
                $clienteExistente = TbClientes::whereRaw('UPPER(RFC) = ?', [$rfc])->first();
                if ($clienteExistente) {
                    if (! empty($data['IDSistemaOrigen']) && ! empty($data['NoClienteSistema'])) {
                        $sistema = CatSistemas::find($data['IDSistemaOrigen']);
                        $nombreSistema = $sistema ? $sistema->Sistema : 'ID '.$data['IDSistemaOrigen'];

                        $existeRegistroSistema = CatIDClientesSistema::where('IDCliente', $clienteExistente->IDCliente)
                            ->where('IDSistema', $data['IDSistemaOrigen'])
                            ->first();

                        if (! $existeRegistroSistema) {
                            try {
                                $nuevoIDOrigen = (CatIDClientesSistema::max('IDOrigenSistema') ?? 0) + 1;
                                CatIDClientesSistema::create([
                                    'IDOrigenSistema' => $nuevoIDOrigen,
                                    'IDCliente' => $clienteExistente->IDCliente,
                                    'IDSistema' => $data['IDSistemaOrigen'],
                                    'NCliente' => $data['NoClienteSistema'],
                                ]);

                                return response()->json([
                                    'codigoError' => 0,
                                    'message' => 'Cliente registrado correctamente en el sistema '.$nombreSistema,
                                    'IDCliente' => $clienteExistente->IDCliente,
                                    'IDSistema' => $data['IDSistemaOrigen'],
                                    'nombreSistema' => $nombreSistema,
                                ], 200);
                            } catch (\Exception $e) {
                                Log::error('Error al registrar cliente duplicado en CatIDClientesSistema: '.$e->getMessage());

                                return response()->json([
                                    'codigoError' => 1,
                                    'message' => 'Error al registrar en CatIDClientesSistema: '.$e->getMessage(),
                                    'IDCliente' => $clienteExistente->IDCliente,
                                ], 200);
                            }
                        }

                        return response()->json([
                            'codigoError' => 0,
                            'message' => 'El cliente ya se encuentra registrado en el sistema '.$nombreSistema,
                            'IDCliente' => $clienteExistente->IDCliente,
                            'IDSistema' => $data['IDSistemaOrigen'],
                            'nombreSistema' => $nombreSistema,
                            'IDOrigenSistema' => $existeRegistroSistema->IDOrigenSistema,
                        ], 200);
                    }

                    return response()->json([
                        'codigoError' => 2,
                        'message' => 'El RFC ya se encuentra registrado.',
                        'error_code' => 'RFC_DUPLICADO',
                        'IDCliente' => $clienteExistente->IDCliente,
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
                    ], 422);
                }
            }
            $data['RFC'] = $rfc;
        }

        $curp = isset($data['CURP']) ? strtoupper(trim($data['CURP'])) : null;

        $nombreParaBusqueda = trim(
            ($data['nombre'] ?? '').
            ' '.($data['apellidoPaterno'] ?? '').
            ' '.($data['apellidoMaterno'] ?? '')
        );
        if (empty($nombreParaBusqueda)) {
            $nombreParaBusqueda = trim($data['razonSocial'] ?? '');
        }

        $analisisListas = app(AnalizadorListasNegras::class)->analizar([
            'IDTipoPersona' => $data['IDTipoPersona'],
            'RFC' => $rfc,
            'CURP' => $curp,
            'nombre' => $data['nombre'] ?? null,
            'apellidoPaterno' => $data['apellidoPaterno'] ?? null,
            'apellidoMaterno' => $data['apellidoMaterno'] ?? null,
            'razonSocial' => $data['razonSocial'] ?? null,
        ]);

        $personaBloqueada = $analisisListas['personaBloqueada'];
        $detalleListaBloqueadas = $analisisListas['detalleListaBloqueadas'];
        $listasDetectadas = $analisisListas['listasDetectadas'];

        // Evaluar coincidencia de persona expuesta políticamente (PPE)
        [$esPPE, $dataPPE] = $this->obtenerDatosPPE($nombreParaBusqueda);

        if ($esPPE) {
            $detalleListaBloqueadas = array_merge(
                $detalleListaBloqueadas,
                $this->construirDetallesPPE($dataPPE)
            );
        }

        // Evaluar los factores de alto riesgo del cliente: actividad vulnerable (ocupación con
        // NivelRiesgo = 3), zona geográfica (domicilio en estado con IndicePaz = 3), coincidencia
        // en listas negras (CNSF / UIF) y PPE.
        $evaluadorRiesgo = app(EvaluadorRiesgoCliente::class);
        $datosRiesgo = $evaluadorRiesgo->evaluar(
            $data['IDOcupacionGiro'] ?? null,
            $data['domicilios'] ?? [],
            $personaBloqueada,
            $esPPE
        );
        $esAltoRiesgo = $datosRiesgo['esAltoRiesgo'];
        $factoresRiesgo = $datosRiesgo['factores'];
        $alertasRiesgo = $datosRiesgo['alertas'];

        // El cliente siempre se da de alta como Activo, aun con coincidencia en listas.
        // La coincidencia se conserva en CoincideEnListasNegras y se avisa al oficial de cumplimiento.
        $activo = true;

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
                'EsAltoRiesgo' => $esAltoRiesgo,
                'IDNacionalidad' => $data['IDNacionalidad'] ?? null,
                'IDEstadoNacimiento' => $idEstadoNacimientoResuelto,
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
                    'Municipio' => $dom['municipio'],
                    'Localidad' => $dom['localidad'] ?? null,
                    'Telefono' => $dom['telefono'] ?? null,
                ]);
                $domiciliosInsertados[] = $domObj;
            }

            if (! empty($data['IDSistemaOrigen']) && ! empty($data['NoClienteSistema'])) {
                $nuevoIDOrigen = (CatIDClientesSistema::max('IDOrigenSistema') ?? 0) + 1;
                CatIDClientesSistema::create([
                    'IDOrigenSistema' => $nuevoIDOrigen,
                    'IDCliente' => $cliente->IDCliente,
                    'IDSistema' => $data['IDSistemaOrigen'],
                    'NCliente' => $data['NoClienteSistema'],
                ]);
            }

            $nombreCompleto = TbAlertas::nombreParaCliente($cliente);

            $alertasGeneradas = [];

            // Emitir alerta si coincide en listas negras
            if ($personaBloqueada) {
                $motivo = 'Detectado en listas negras: '.implode(', ', $listasDetectadas);
                $razones = "El cliente con RFC {$cliente->RFC} coincide en: ".implode(', ', $listasDetectadas);

                $alertasGeneradas[] = TbAlertas::create([
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

            // Emitir alertas de nivel cliente por actividad vulnerable y zona geográfica
            foreach ($alertasRiesgo as $alertaRiesgo) {
                $alertasGeneradas[] = $this->crearAlertaCliente(
                    $cliente,
                    $alertaRiesgo['patron'],
                    $alertaRiesgo['descripcion'],
                    $alertaRiesgo['razones'],
                    'Generado'
                );
            }

            // Registrar detecciones en listas negras
            foreach ($detalleListaBloqueadas as $deteccion) {
                LogDetectClientesListas::create([
                    'IDCliente' => $cliente->IDCliente,
                    'Lista' => $deteccion['fuente'] ?? 'Desconocida',
                    'NombreDetectado' => $deteccion['Nombre'] ?? null,
                    'Origen' => 'SIT',
                    'TimeStampDeteccion' => now(),
                ]);
            }

            // Registrar detección de PPE
            if ($esPPE) {
                TbClientesPPE::create([
                    'IDCliente' => $cliente->IDCliente,
                    'Lista' => 'PPE',
                    'Cargo' => null,
                    'Estado' => 'Detectado',
                    'FechaDeteccion' => now()->toDateString(),
                    'Origen' => 'SIT',
                    'TimeStampRegistro' => now(),
                ]);
            }

            DB::commit();

            $mensajeExito = 'Cliente ingresado exitosamente';
            if ($personaBloqueada) {
                $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
            }
            if ($esAltoRiesgo) {
                $mensajeExito .= '. Nota: El cliente fue marcado como de alto riesgo.';
            }

            if (! empty($alertasGeneradas) || $esPPE) {
                try {
                    $this->enviarAvisoCumplimiento(
                        $cliente,
                        $esPPE,
                        $listasDetectadas,
                        $detalleListaBloqueadas,
                        $alertasGeneradas,
                        $esAltoRiesgo,
                        $factoresRiesgo
                    );
                } catch (\Throwable $e) {
                    Log::warning('Error al enviar aviso de cumplimiento (guardarCliente): '.$e->getMessage());
                }
            }

            return response()->json([
                'codigoError' => 0,
                'message' => $mensajeExito,
                'IDCliente' => $cliente->IDCliente,
                'esPPE' => $cliente->EsPPEActivo,
                'personaBloqueada' => $cliente->CoincideEnListasNegras,
                'esAltoRiesgo' => $esAltoRiesgo,
                'factoresRiesgo' => $factoresRiesgo,
                'patronesDetectados' => $this->patronesDeAlertas($alertasGeneradas),
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

    /**
     * Resuelve IDEstado genérico (usado para domicilios.IDEstado y IDEstadoNacimiento).
     * Acepta int o string: "9", "09", " 9 ", CveEntidad o nombre completo.
     * Devuelve IDEstado (int) si existe en catEstados, null si no.
     */
    private function resolveIDEstado($valor)
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        $valor = trim((string) $valor);

        // Caso numérico: "9", "09", "00" -> int (elimina ceros a la izquierda)
        if (is_numeric($valor)) {
            $idNumerico = (int) $valor;

            $estado = CatEstados::where('IDEstado', $idNumerico)->first();

            return $estado ? $estado->IDEstado : null;
        }

        // Caso clave de entidad, ej. "09", "DF" (CveEntidad en catEstados)
        $estado = CatEstados::whereRaw('UPPER(CveEntidad) = ?', [strtoupper($valor)])->first();
        if ($estado) {
            return $estado->IDEstado;
        }

        // Caso nombre completo, ej. "Nayarit", "Ciudad de México"
        $estado = CatEstados::whereRaw('UPPER(Estado) = ?', [strtoupper($valor)])->first();
        if ($estado) {
            return $estado->IDEstado;
        }

        return null;
    }

    /**
     * Alias para compatibilidad: resuelve IDEstadoNacimiento.
     */
    private function resolveIDEstadoNacimiento($valor)
    {
        return $this->resolveIDEstado($valor);
    }

    public function actualizarCliente(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'RFC' => 'nullable|string|max:18',
            'nombre' => 'nullable|string|max:255',
            'apellidoPaterno' => 'nullable|string|max:255',
            'apellidoMaterno' => 'nullable|string|max:255',
            'razonSocial' => 'nullable|string|max:255',
            'IDTipoPersona' => 'required|integer|exists:catTipoPersona,IDTipoPersona',
            'CURP' => 'nullable|string|max:18',
            'IDOcupacionGiro' => 'nullable|integer',
            'fechaNacimiento' => 'nullable|date',
            'fechaConstitucion' => 'nullable|date',
            'folioMercantil' => 'nullable|string|max:255',
            'IDNacionalidad' => 'nullable|string',
            'IDEstadoNacimiento' => 'nullable',
            'Preguntas' => 'nullable|string',
            'ingresosEstimados' => 'nullable|numeric',

            'domicilios' => 'required|array|min:1',
            'domicilios.*.calle' => 'required|string|max:255',
            'domicilios.*.noExterior' => 'nullable|string|max:255',
            'domicilios.*.noInterior' => 'nullable|string|max:255',
            'domicilios.*.colonia' => 'required|string|max:255',
            'domicilios.*.CP' => 'required|string|max:10',
            'domicilios.*.IDEstado' => 'required',
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
            ], 422);
        }
        $validated = $validator->validated();

        // RFC e IDNacionalidad son NOT NULL en tbClientes: en PUT parcial la clave
        // puede omitirse (se conserva el valor actual), pero un null explícito
        // reventaría con 1048 al guardar. Se rechaza con 422.
        if (array_key_exists('RFC', $validated) && trim((string) ($validated['RFC'] ?? '')) === '') {
            return response()->json([
                'codigoError' => 1,
                'message' => 'El RFC no puede ser nulo ni vacío.',
                'errors' => [
                    'RFC' => ['El RFC es obligatorio y no puede ser nulo.'],
                ],
            ], 422);
        }
        if (array_key_exists('IDNacionalidad', $validated) && trim((string) ($validated['IDNacionalidad'] ?? '')) === '') {
            return response()->json([
                'codigoError' => 1,
                'message' => 'El IDNacionalidad no puede ser nulo ni vacío.',
                'errors' => [
                    'IDNacionalidad' => ['El IDNacionalidad es obligatorio y no puede ser nulo.'],
                ],
            ], 422);
        }

        // Resolver IDEstadoNacimiento si viene (acepta int 9 o string "09"/"Nayarit")
        if (array_key_exists('IDEstadoNacimiento', $validated) && $validated['IDEstadoNacimiento'] !== null && trim((string) $validated['IDEstadoNacimiento']) !== '') {
            $resueltoNac = $this->resolveIDEstado($validated['IDEstadoNacimiento']);
            if ($resueltoNac === null) {
                return response()->json([
                    'codigoError' => 1,
                    'message' => 'El valor de IDEstadoNacimiento no es válido.',
                    'errors' => [
                        'IDEstadoNacimiento' => ['El valor "'.$validated['IDEstadoNacimiento'].'" no corresponde a ningún estado del catálogo.'],
                    ],
                ], 422);
            }
            $validated['IDEstadoNacimiento'] = $resueltoNac;
        }

        // Resolver IDEstado de cada domicilio (tolerante a string o int, con lookup en catEstados)
        if (! empty($validated['domicilios']) && is_array($validated['domicilios'])) {
            foreach ($validated['domicilios'] as $idx => $dom) {
                $valorEstado = $dom['IDEstado'] ?? null;
                $resuelto = $this->resolveIDEstado($valorEstado);
                if ($resuelto === null) {
                    return response()->json([
                        'codigoError' => 1,
                        'message' => 'El valor de IDEstado en domicilios no es válido.',
                        'errors' => [
                            "domicilios.$idx.IDEstado" => ['El valor "'.$valorEstado.'" no corresponde a ningún estado del catálogo.'],
                        ],
                    ], 422);
                }
                $validated['domicilios'][$idx]['IDEstado'] = $resuelto;
            }
        }

        $cliente = TbClientes::find($id);

        if (! $cliente) {
            return response()->json([
                'codigoError' => 1,
                'message' => 'Cliente no encontrado',
            ], 404);
        }

        // Normalizar IDTipoPersona histórico: tbClientes no tiene FK y puede
        // contener valores legacy inválidos (p. ej. 0). logClientes sí tiene FK
        // a catTipoPersona, así que un valor inexistente provoca 1452.
        // Se guarda NULL en el log cuando el valor no existe en el catálogo.
        $tipoPersonaLog = null;
        if ($cliente->IDTipoPersona !== null && CatTipoPersona::where('IDTipoPersona', $cliente->IDTipoPersona)->exists()) {
            $tipoPersonaLog = $cliente->IDTipoPersona;
        } else {
            Log::warning('actualizarCliente: IDTipoPersona histórico inválido, se guarda NULL en log.', [
                'IDCliente' => $cliente->IDCliente,
                'IDTipoPersona' => $cliente->IDTipoPersona,
            ]);
        }

        $rfcParaListas = array_key_exists('RFC', $validated) ? $validated['RFC'] : $cliente->RFC;
        $curpParaListas = array_key_exists('CURP', $validated) ? $validated['CURP'] : $cliente->CURP;
        $nombreParaListas = array_key_exists('nombre', $validated) ? $validated['nombre'] : $cliente->Nombre;
        $apellidoPaternoParaListas = array_key_exists('apellidoPaterno', $validated) ? $validated['apellidoPaterno'] : $cliente->ApellidoPaterno;
        $apellidoMaternoParaListas = array_key_exists('apellidoMaterno', $validated) ? $validated['apellidoMaterno'] : $cliente->ApellidoMaterno;
        $razonSocialParaListas = array_key_exists('razonSocial', $validated) ? $validated['razonSocial'] : $cliente->RazonSocial;

        $analisisListas = app(AnalizadorListasNegras::class)->analizar([
            'IDTipoPersona' => $validated['IDTipoPersona'],
            'RFC' => $rfcParaListas,
            'CURP' => $curpParaListas,
            'nombre' => $nombreParaListas,
            'apellidoPaterno' => $apellidoPaternoParaListas,
            'apellidoMaterno' => $apellidoMaternoParaListas,
            'razonSocial' => $razonSocialParaListas,
        ]);

        $coincideEnListasNegras = $analisisListas['personaBloqueada'];
        $detalleListaBloqueadas = $analisisListas['detalleListaBloqueadas'];
        $listasDetectadas = $analisisListas['listasDetectadas'];

        DB::beginTransaction();

        try {
            // Guardar el estado actual del cliente en el log antes de la actualización
            LogClientes::create([
                'IDCliente' => $cliente->IDCliente,
                'RfcAnterior' => $cliente->RFC,
                'Nombre' => $cliente->Nombre,
                'ApellidoPaterno' => $cliente->ApellidoPaterno,
                'ApellidoMaterno' => $cliente->ApellidoMaterno,
                'RazonSocial' => $cliente->RazonSocial,
                'IDTipoPersona' => $tipoPersonaLog,
                'CURP' => $cliente->CURP,
                'IDOcupacionGiro' => $cliente->IDOcupacionGiro,
                'FechaNacimiento' => $cliente->FechaNacimiento,
                'FechaConstitucion' => $cliente->FechaConstitucion,
                'FolioMercantil' => $cliente->FolioMercantil,
                'CoincideEnListasNegras' => $cliente->CoincideEnListasNegras,
                'EsPPEActivo' => $cliente->EsPPEActivo,
                'EsAltoRiesgo' => $cliente->EsAltoRiesgo,
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

            // Para PPE, armar nombre completo lo mejor posible
            $nombreC = trim(
                ($nombre ? $nombre : '')
                .' '.($apellidoPaterno ? $apellidoPaterno : '')
                .' '.($apellidoMaterno ? $apellidoMaterno : '')
            );

            [$esPPE, $dataPPE] = $this->obtenerDatosPPE($nombreC);

            foreach ($detalleListaBloqueadas as $indice => $detalle) {
                $detalleListaBloqueadas[$indice]['PPEActivo'] = $esPPE;
            }

            if ($esPPE) {
                $detalleListaBloqueadas = array_merge(
                    $detalleListaBloqueadas,
                    $this->construirDetallesPPE($dataPPE)
                );
            }

            // Evaluar los factores de alto riesgo del cliente: actividad vulnerable (ocupación con
            // NivelRiesgo = 3), zona geográfica (domicilio en estado con IndicePaz = 3), coincidencia
            // en listas negras (CNSF / UIF) y PPE. El flag se recalcula en cada edición.
            $evaluadorRiesgo = app(EvaluadorRiesgoCliente::class);
            $datosRiesgo = $evaluadorRiesgo->evaluar(
                array_key_exists('IDOcupacionGiro', $validated) ? $validated['IDOcupacionGiro'] : $cliente->IDOcupacionGiro,
                $validated['domicilios'] ?? [],
                $coincideEnListasNegras,
                $esPPE
            );
            $esAltoRiesgo = $datosRiesgo['esAltoRiesgo'];
            $factoresRiesgo = $datosRiesgo['factores'];
            $alertasRiesgo = $datosRiesgo['alertas'];

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
            $cliente->EsAltoRiesgo = $esAltoRiesgo;

            // CoincideEnListasNegras y Activo se actualizan más abajo según lógica

            // Actualizar/crear CatIdClientesSistema relacionado si vienen los datos de sistema origen
            if (! empty($validated['IDSistemaOrigen']) && ! empty($validated['NoClienteSistema'])) {
                $catIdSistema = CatIDClientesSistema::where('IDCliente', $cliente->IDCliente)
                    ->where('IDSistema', $validated['IDSistemaOrigen'])
                    ->first();

                if ($catIdSistema) {
                    $catIdSistema->NCliente = $validated['NoClienteSistema'];
                    $catIdSistema->save();
                } else {
                    $nuevoIDOrigen = (CatIDClientesSistema::max('IDOrigenSistema') ?? 0) + 1;
                    CatIDClientesSistema::create([
                        'IDOrigenSistema' => $nuevoIDOrigen,
                        'IDCliente' => $cliente->IDCliente,
                        'IDSistema' => $validated['IDSistemaOrigen'],
                        'NCliente' => $validated['NoClienteSistema'],
                    ]);
                }
            }

            $cliente->CoincideEnListasNegras = $coincideEnListasNegras;
            // El cliente siempre permanece Activo, aun con coincidencia en listas.
            $cliente->Activo = 1;
            $cliente->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error en actualizarCliente: '.$e->getMessage(), [
                'IDCliente' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'codigoError' => 1,
                'message' => 'Error al actualizar el cliente: '.$e->getMessage(),
            ], 500);
        }

        $nombreCompleto = TbAlertas::nombreParaCliente($cliente);

        $alertasGeneradas = [];

        // Si coincide en listas negras, registrar alerta
        if ($coincideEnListasNegras) {
            $motivo = 'Detectado en listas negras: '.implode(', ', $listasDetectadas);
            $razones = "El cliente con RFC {$cliente->RFC} coincide en: ".implode(', ', $listasDetectadas);

            $alertasGeneradas[] = TbAlertas::create([
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

        // Emitir alertas de nivel cliente por actividad vulnerable y zona geográfica
        foreach ($alertasRiesgo as $alertaRiesgo) {
            $alertasGeneradas[] = $this->crearAlertaCliente(
                $cliente,
                $alertaRiesgo['patron'],
                $alertaRiesgo['descripcion'],
                $alertaRiesgo['razones'],
                'Generado'
            );
        }

        // Registrar detecciones en listas negras
        foreach ($detalleListaBloqueadas as $deteccion) {
            LogDetectClientesListas::create([
                'IDCliente' => $cliente->IDCliente,
                'Lista' => $deteccion['lista'] ?? 'Desconocida',
                'NombreDetectado' => $deteccion['nombreDetectado'] ?? $deteccion['nombre'] ?? null,
                'Origen' => 'SIT',
                'TimeStampDeteccion' => now(),
            ]);
        }

        // Registrar detección de PPE
        if ($esPPE) {
            TbClientesPPE::create([
                'IDCliente' => $cliente->IDCliente,
                'Lista' => 'PPE',
                'Cargo' => null,
                'Estado' => 'Detectado',
                'FechaDeteccion' => now()->toDateString(),
                'Origen' => 'SIT',
                'TimeStampRegistro' => now(),
            ]);
        }

            if (! empty($alertasGeneradas) || $esPPE) {
                try {
                    $this->enviarAvisoCumplimiento(
                        $cliente,
                        $esPPE,
                        $listasDetectadas,
                        $detalleListaBloqueadas,
                        $alertasGeneradas,
                        $esAltoRiesgo,
                        $factoresRiesgo
                    );
                } catch (\Throwable $e) {
                    Log::warning('Error al enviar aviso de cumplimiento (actualizarCliente): '.$e->getMessage());
                }
            }

        $mensajeExito = 'Cliente actualizado exitosamente';
        if ($coincideEnListasNegras) {
            $mensajeExito .= '. Nota: El cliente cuenta con coincidencias en listas.';
        }
        if ($esAltoRiesgo) {
            $mensajeExito .= '. Nota: El cliente fue marcado como de alto riesgo.';
        }

        return response()->json([
            'codigoError' => 0,
            'message' => $mensajeExito,
            'IDCliente' => $cliente->IDCliente,
            'esPPE' => $esPPE,
            'personaBloqueada' => $coincideEnListasNegras,
            'esAltoRiesgo' => $esAltoRiesgo,
            'factoresRiesgo' => $factoresRiesgo,
            'patronesDetectados' => $this->patronesDeAlertas($alertasGeneradas),
            'detalleListaBloqueadas' => $detalleListaBloqueadas,
        ], 200);
    }

    /**
     * Consulta Q-Detect para determinar si el nombre corresponde a una PPE.
     *
     * @return array{0: bool, 1: ?array}
     */
    private function obtenerDatosPPE(string $nombre): array
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            return [false, null];
        }

        $porcentajePPE = (float) ParametriaPLDService::get('percentMatchQeQ', 80);
        $tokenPPE = ClienteHelper::getTokenPPE();

        if (empty($tokenPPE)) {
            Log::warning('No se pudo obtener token para búsqueda PPE.', [
                'nombre' => $nombre,
            ]);

            return [false, null];
        }

        $dataPPE = ClienteHelper::getPPE($tokenPPE, $nombre, $porcentajePPE);

        if (! is_array($dataPPE)) {
            Log::warning('Respuesta inválida de Q-Detect para búsqueda PPE.', [
                'nombre' => $nombre,
                'percent' => $porcentajePPE,
            ]);

            return [false, $dataPPE];
        }

        $totalCoincidencias = isset($dataPPE['data']) && is_array($dataPPE['data'])
            ? count($dataPPE['data'])
            : 0;

        Log::info('Búsqueda PPE en Q-Detect completada.', [
            'nombre' => $nombre,
            'percent' => $porcentajePPE,
            'success' => $dataPPE['success'] ?? null,
            'status' => $dataPPE['status'] ?? null,
            'totalCoincidencias' => $totalCoincidencias,
        ]);

        return [$totalCoincidencias > 0, $dataPPE];
    }

    /**
     * Construye los detalles de coincidencias PPE con la misma estructura
     * que las coincidencias de UIF y CNSF.
     */
    private function construirDetallesPPE(?array $dataPPE): array
    {
        if (empty($dataPPE['data']) || ! is_array($dataPPE['data'])) {
            return [];
        }

        $detalles = [];

        foreach ($dataPPE['data'] as $registro) {
            $nombre = $registro['NOMBRECOMP'] ?? ($registro['NOMBRE'] ?? '');
            $estatus = $registro['ESTATUS'] ?? '';

            $detalles[] = [
                'lista' => $registro['LISTA'] ?? 'PPE',
                'fuente' => 'PPE',
                'nombreDetectado' => $nombre,
                'Nombre' => $nombre,
                'IDListaOrigen' => 1,
                'rfc' => $registro['RFC'] ?? '',
                'RFC' => $registro['RFC'] ?? '',
                'curp' => '',
                'CURP' => '',
                'cargo' => $registro['PUESTO'] ?? '',
                'estado' => $registro['ENTIDAD'] ?? '',
                'estatus' => $estatus,
                'PPEActivo' => true,
                'metodo' => 'PPE',
                'coincidencia' => 100,
            ];
        }

        return $detalles;
    }

    /**
     * Crea una alerta de nivel cliente (sin operación asociada) en tbAlertas.
     */
    private function crearAlertaCliente(
        TbClientes $cliente,
        string $patron,
        string $descripcion,
        string $razones,
        string $estatus = 'Generado'
    ): TbAlertas {
        return TbAlertas::create([
            'Folio' => null,
            'Patron' => $patron,
            'IDCliente' => $cliente->IDCliente,
            'Cliente' => TbAlertas::nombreParaCliente($cliente),
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
            'Estatus' => $estatus,
            'Descripcion' => $descripcion,
            'Razones' => $razones,
            'Evidencias' => '',
            'IDReporteOP' => null,
            'IDPago' => null,
        ]);
    }

    /**
     * Devuelve la lista de patrones de alertamiento generados, sin duplicados.
     *
     * @param  array<int, TbAlertas>  $alertas
     * @return array<int, string>
     */
    private function patronesDeAlertas(array $alertas): array
    {
        $patrones = [];

        foreach ($alertas as $alerta) {
            $patron = $alerta instanceof TbAlertas ? $alerta->Patron : ($alerta['Patron'] ?? $alerta['patron'] ?? null);
            if (! empty($patron)) {
                $patrones[] = $patron;
            }
        }

        return array_values(array_unique($patrones));
    }

    /**
     * Envía el aviso al oficial de cumplimiento, conservando el formato
     * original del SIT pero emitiéndose desde el sistema PLD.
     *
     * @param  array<int, TbAlertas>  $alertasGeneradas  Alertas emitidas en el alta/edición.
     * @param  array<int, string>  $factoresRiesgo  Factores que hacen al cliente de alto riesgo.
     */
    private function enviarAvisoCumplimiento(
        TbClientes $cliente,
        bool $esPPE,
        array $listasDetectadas,
        array $detalleListaBloqueadas,
        array $alertasGeneradas = [],
        bool $esAltoRiesgo = false,
        array $factoresRiesgo = []
    ): void {
        $nombreCompleto = trim(
            ($cliente->Nombre ?? '').
            ' '.
            ($cliente->ApellidoPaterno ?? '').
            ' '.
            ($cliente->ApellidoMaterno ?? '')
        );
        if (empty($nombreCompleto)) {
            $nombreCompleto = trim($cliente->RazonSocial ?? '');
        }

        $tipo = (int) $cliente->IDTipoPersona === 2 ? 'empresa' : 'persona';

        $registrosEncontrados = count($detalleListaBloqueadas);
        $nombresDetectados = [];
        $listasDetectadasFinal = [];

        foreach ($detalleListaBloqueadas as $deteccion) {
            $nombreDetectado = $deteccion['Nombre'] ?? $deteccion['nombreDetectado'] ?? '';
            if (! empty($nombreDetectado)) {
                $nombresDetectados[] = $nombreDetectado;
            }

            $lista = $deteccion['fuente'] ?? $deteccion['lista'] ?? '';
            if (! empty($lista)) {
                $listasDetectadasFinal[] = $lista;
            }
        }

        if (empty($listasDetectadasFinal)) {
            $listasDetectadasFinal = $listasDetectadas;
        }

        $idCategoria = $esPPE ? 4 : 2;
        $categoria = $this->obtenerCategoriaTexto($idCategoria);
        $observaciones = CatCategoriaPersonasBloqueadas::getComentariosDeteccion($idCategoria);

        // Patrones de alertamiento emitidos en el alta/edición del cliente
        $patrones = [];
        foreach ($alertasGeneradas as $alerta) {
            $patrones[] = [
                'patron' => $alerta->Patron ?? '—',
                'descripcion' => $alerta->Descripcion ?? '—',
            ];
        }

        if ($esPPE && empty(array_filter($patrones, fn ($p) => $p['patron'] === 'PPE'))) {
            $patrones[] = [
                'patron' => 'PPE',
                'descripcion' => 'Persona politicamente expuesta detectada en la consulta de Q-Detect.',
            ];
        }

        $asunto = $esAltoRiesgo
            ? 'Aviso de alertamiento PLD - cliente de alto riesgo - '.$nombreCompleto
            : 'Aviso de detección en listas - PLD - '.$nombreCompleto;

        NotificacionCumplimientoService::enviar([
            'asunto' => $asunto,
            'idCliente' => $cliente->IDCliente,
            'nombre' => $nombreCompleto ?: '—',
            'tipo' => $tipo,
            // AUDITORÍA (reversible vía AUDIT_BACKDATE_CORREOS): misma fecha, un año atrás.
            'fecha' => AuditDate::now()->translatedFormat('d \d\e F \d\e Y'),
            'hora' => AuditDate::now()->format('H:i'),
            'registrosEncontrados' => $registrosEncontrados,
            'nombresDetectados' => implode(', ', array_unique($nombresDetectados)),
            'listasDetectadas' => implode(', ', array_unique($listasDetectadasFinal)),
            'categoria' => $categoria,
            'observaciones' => $observaciones,
            'esPPE' => $esPPE,
            'patrones' => $patrones,
            'esAltoRiesgo' => $esAltoRiesgo,
            'factoresRiesgo' => array_values($factoresRiesgo),
            'urlDetalleCliente' => $this->urlDetalleCliente($cliente),
        ]);
    }

    /**
     * Enlace al módulo de Detalle cliente para el oficial de cumplimiento.
     */
    private function urlDetalleCliente(TbClientes $cliente): string
    {
        try {
            return route('clientes.ver-detalles', ['id_cliente' => $cliente->IDCliente]);
        } catch (\Throwable $e) {
            Log::warning('No se pudo generar el enlace al detalle del cliente: '.$e->getMessage());

            return url('/clientes/ver-detalles/'.$cliente->IDCliente);
        }
    }

    /**
     * Devuelve la categoría con su título (ID: título) según la clasificación PLD.
     */
    private function obtenerCategoriaTexto(int $idCategoria): string
    {
        try {
            $cat = CatCategoriaPersonasBloqueadas::where('IDCategoria', $idCategoria)->first();
            if ($cat && ! empty($cat->Categoria)) {
                return $idCategoria.': '.$cat->Categoria;
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo leer catCategoriaPersonasBloqueadas, se usa categoría por defecto: '.$e->getMessage());
        }

        $fallback = [
            2 => 'Persona / Empresa aparece en listas bloqueadas, necesita revisión',
            4 => 'Persona detectada en listas políticamente expuestas, necesita revisión',
        ];

        return $idCategoria.': '.($fallback[$idCategoria] ?? 'Necesita revisión');
    }
}
