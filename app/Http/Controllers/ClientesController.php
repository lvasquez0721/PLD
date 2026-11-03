<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\CatMonedas;
use App\Models\CatTipoPago;
use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatIDClientesSistema;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\CatTipoPersona;
use App\Models\Clientes\PersonaImportacion;
use App\Models\Clientes\TbClientes;
use App\Models\Clientes\TbClientesDomicilio;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use App\Models\ListasBloqueadas\TbListasNegrasUIF;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbPerfilTransaccional;
use App\Services\PLD\EvaluadorRiesgoCliente;
use App\Services\PLD\ImportadorPersonas;
use App\Services\PLD\IndicadoresOperacionesCliente;
use App\Services\PLD\RegistroPLDService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ClientesController extends Controller
{
    protected string $tipoPersona = TbClientes::TIPO_CLIENTE;

    protected string $rutaBase = '/clientes';

    protected string $tituloModulo = 'Clientes';

    protected string $sustantivoSingular = 'cliente';

    protected string $sustantivoPlural = 'clientes';

    public function index(Request $request)
    {
        $query = $this->buildQuery($request);

        // Ordenar los clientes de forma descendente por el campo 'id' (puedes cambiar a otro campo si se requiere diferente criterio)
        $query->orderByDesc('IDCliente');

        if ($this->tipoPersona !== TbClientes::TIPO_CLIENTE) {
            $query->with('domicilios');
        }

        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [5, 10, 20, 50, 100], true)) {
            $perPage = 10;
        }
        $clientes = $query->paginate($perPage)->withQueryString();

        // Obtener TODOS los RFCs (únicamente) de la lista negra, a mayúsculas y sin espacios
        $rfcEnListaNegra = $this->getRfcEnListaNegra();

        // Añadir los campos dinámicos 'CNSF', 'coincidencias', 'autorizadoApareceEnListas', 'countCoincidencias', 'esPPE' y 'fueraDeCategoria' a cada cliente
        $clientes->getCollection()->transform(function ($cliente) use ($rfcEnListaNegra) {
            return $this->transformCliente($cliente, $rfcEnListaNegra);
        });

        // return response()->json($clientes);

        return inertia('Clientes/Index', [
            'clientes' => $clientes,
            'filters' => $request->only(['search', 'tipo', 'estatus', 'per_page', 'category']),
            'modulo' => $this->moduloProps(),
            'catalogos' => $this->tipoPersona === TbClientes::TIPO_CLIENTE ? null : $this->catalogosPersona(),
            'importacion_reciente' => session('importacion_reciente'),
            'import_errors' => session('import_errors', []),
            'toast' => session('toast'),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $query = $this->buildQuery($request);
        $clientes = $query->get();

        if ($clientes->isEmpty()) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => "No hay {$this->sustantivoPlural} para exportar con los filtros seleccionados.",
            ]);
        }

        $rfcEnListaNegra = $this->getRfcEnListaNegra();

        $fileName = $this->sustantivoPlural.'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($clientes, $rfcEnListaNegra) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($file, [
                'Nombre/Razón Social',
                'Apellido Paterno',
                'Apellido Materno',
                'RFC',
                'CURP',
                'Tipo Persona',
                'Nacionalidad',
                'Coincidencias Listas Negras',
                'Es PPE',
                'Estatus CNSF',
                'Fuera de Categoría',
            ]);

            foreach ($clientes as $cliente) {
                $cliente = $this->transformCliente($cliente, $rfcEnListaNegra);

                $nombreCompleto = $cliente->RazonSocial ?: $cliente->Nombre;

                fputcsv($file, [
                    $nombreCompleto,
                    $cliente->ApellidoPaterno,
                    $cliente->ApellidoMaterno,
                    $cliente->RFC,
                    $cliente->CURP,
                    $cliente->IDTipoPersona == 1 ? 'Física' : 'Moral',
                    $cliente->IDNacionalidad,
                    $cliente->CoincideEnListasNegras,
                    $cliente->esPPE ? 'Sí' : 'No',
                    $cliente->CNSF ? 'Coincide' : 'Sin Coincidencia',
                    $cliente->fueraDeCategoria ? 'Sí' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildQuery(Request $request)
    {
        // Inicialmente solo los registros del tipo gestionado por este controlador.
        $query = TbClientes::query()->where('TipoPersona', $this->tipoPersona);

        // Búsqueda exhaustiva: AND por palabras, insensible a mayúsculas/acentos
        // (collation utf8mb4_unicode_ci) y sin que se escape ningún registro.
        if ($request->filled('search')) {
            $raw = trim((string) $request->input('search'));
            // Colapsar espacios múltiples / tabs / saltos de línea.
            $raw = (string) preg_replace('/\s+/u', ' ', $raw);
            $raw = mb_substr($raw, 0, 100);

            if ($raw !== '') {
                $tokens = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $tokens = array_slice($tokens, 0, 8);
                $isNumericSearch = ctype_digit($raw);

                $query->where(function ($outer) use ($tokens, $isNumericSearch, $raw) {
                    // Grupo AND: cada palabra debe aparecer en ALGÚN campo.
                    $outer->where(function ($and) use ($tokens) {
                        foreach ($tokens as $token) {
                            // Escapar comodines de LIKE para que "%" o "_" literales no rompan el filtro.
                            $escaped = addcslashes($token, '%_\\');
                            $like = "%{$escaped}%";

                            // Cada palabra debe aparecer en ALGÚN campo (AND entre palabras, OR dentro).
                            $and->where(function ($q) use ($like) {
                                $q->where('Nombre', 'like', $like)
                                    ->orWhere('ApellidoPaterno', 'like', $like)
                                    ->orWhere('ApellidoMaterno', 'like', $like)
                                    ->orWhere('RazonSocial', 'like', $like)
                                    ->orWhere('RFC', 'like', $like)
                                    ->orWhere('CURP', 'like', $like)
                                    // RFC/CURP robustos a espacios en BD.
                                    ->orWhereRaw("TRIM(RFC) LIKE ? ESCAPE '\\\\'", [$like])
                                    ->orWhereRaw("TRIM(CURP) LIKE ? ESCAPE '\\\\'", [$like])
                                    // Nombre completo repartido en varias columnas.
                                    ->orWhereRaw("CONCAT_WS(' ', Nombre, ApellidoPaterno, ApellidoMaterno) LIKE ? ESCAPE '\\\\'", [$like])
                                    ->orWhereRaw("CONCAT_WS(' ', ApellidoPaterno, ApellidoMaterno, Nombre) LIKE ? ESCAPE '\\\\'", [$like])
                                    // Domicilio / teléfono.
                                    ->orWhereHas('domicilios', function ($d) use ($like) {
                                        $d->where('Calle', 'like', $like)
                                            ->orWhere('Colonia', 'like', $like)
                                            ->orWhere('CP', 'like', $like)
                                            ->orWhere('Municipio', 'like', $like)
                                            ->orWhere('Localidad', 'like', $like)
                                            ->orWhere('Telefono', 'like', $like)
                                            ->orWhere('NoExterior', 'like', $like)
                                            ->orWhere('NoInterior', 'like', $like);
                                    })
                                    // NCliente en sistemas origen.
                                    ->orWhereHas('idsSistema', function ($s) use ($like) {
                                        $s->where('NCliente', 'like', $like);
                                    })
                                    // Pólizas / endosos.
                                    ->orWhereHas('operaciones', function ($o) use ($like) {
                                        $o->where('FolioPoliza', 'like', $like)
                                            ->orWhere('FolioEndoso', 'like', $like);
                                    });
                            });
                        }
                    });

                    // Búsqueda directa por IDCliente exacto (ej. "6633") como alternativa OR.
                    if ($isNumericSearch) {
                        $outer->orWhere('IDCliente', (int) $raw);
                    }
                });
            }
        }

        // Filtro por tipo de persona
        if ($request->filled('tipo') && $request->input('tipo') !== 'todos') {
            if ($request->input('tipo') === 'fisica') {
                $query->where('IDTipoPersona', 1);
            } elseif ($request->input('tipo') === 'moral') {
                $query->where('IDTipoPersona', '!=', 1);
            }
        }

        // Filtro por estatus del cliente (campo Activo)
        if ($request->filled('estatus') && $request->input('estatus') !== 'todos') {
            if ($request->input('estatus') === 'activo') {
                $query->where('Activo', 1);
            } elseif ($request->input('estatus') === 'inactivo') {
                $query->where(function ($q) {
                    $q->where('Activo', 0)->orWhereNull('Activo');
                });
            }
        }

        // Filtro por categoría PLD
        if ($request->filled('category') && $request->input('category') !== 'todos') {
            $categories = $request->input('category');
            if (! is_array($categories)) {
                $categories = [$categories];
            }
            $categories = array_values(array_intersect($categories, [
                'sin-coincidencia', 'coincidencia-revision', 'ppe-revision',
                'autorizada-listas', 'fuera-categoria', 'listas-internas',
            ]));

            // Subquery SQL (sin traer miles de RFCs a PHP): UPPER(TRIM(RFC)) no vacíos.
            $cnsfRfcSubquery = fn () => TbListasNegraCNSF::select(DB::raw('UPPER(TRIM(RFC))'))
                ->whereNotNull('RFC')
                ->whereRaw("TRIM(RFC) != ''");

            $query->where(function ($q) use ($categories, $cnsfRfcSubquery) {
                foreach ($categories as $category) {
                    switch ($category) {
                        case 'sin-coincidencia':
                            $q->orWhere(function ($subQuery) use ($cnsfRfcSubquery) {
                                $subQuery->where('CoincideEnListasNegras', 0)
                                    ->where(function ($q2) use ($cnsfRfcSubquery) {
                                        $q2->whereNull('RFC')
                                            ->orWhere('RFC', '')
                                            ->orWhereRaw("TRIM(RFC) = ''")
                                            ->orWhereNotIn(DB::raw('UPPER(TRIM(RFC))'), $cnsfRfcSubquery());
                                    });
                            });
                            break;
                        case 'coincidencia-revision':
                            // Coincidencias en listas que aún NO están autorizadas
                            $q->orWhere(function ($subQuery) {
                                $subQuery->where('CoincideEnListasNegras', '>', 0)
                                    ->where(function ($q2) {
                                        $q2->whereNull('Activo')
                                            ->orWhere('Activo', '!=', 1);
                                    });
                            });
                            break;
                        case 'ppe-revision':
                            $q->orWhere('EsPPEActivo', 1);
                            break;
                        case 'autorizada-listas':
                            $q->orWhere(function ($subQuery) {
                                $subQuery->where('Activo', 1)
                                    ->where('CoincideEnListasNegras', '>', 0);
                            });
                            break;
                        case 'fuera-categoria':
                            $q->orWhere(function ($subQuery) {
                                $subQuery->whereNull('RFC')
                                    ->orWhere('RFC', '')
                                    ->orWhere('IDNacionalidad', '!=', 'MX');
                            });
                            break;
                        case 'listas-internas':
                            $q->orWhereIn(DB::raw('UPPER(TRIM(RFC))'), $cnsfRfcSubquery());
                            break;
                    }
                }
            });
        }

        return $query;
    }

    private function getRfcEnListaNegra()
    {
        return TbListasNegraCNSF::pluck('RFC')
            ->map(function ($rfc) {
                return strtoupper(trim($rfc ?? ''));
            })
            ->filter(function ($rfc) {
                return $rfc !== '';
            })
            ->unique()
            ->toArray();
    }

    private function transformCliente($cliente, $rfcEnListaNegra)
    {
        $clienteRFC = strtoupper(trim($cliente->RFC ?? ''));
        $cliente->CNSF = $clienteRFC !== '' && in_array($clienteRFC, $rfcEnListaNegra, true);

        // Añadir el campo booleano "coincidencias"
        $cliente->coincidencias = ((int) $cliente->CoincideEnListasNegras) > 0;
        $cliente->countCoincidencias = $cliente->CoincideEnListasNegras;
        $cliente->autorizadoApareceEnListas = ((int) $cliente->Activo === 1) && ((int) $cliente->CoincideEnListasNegras > 0);
        $cliente->esPPE = $cliente->EsPPEActivo;

        // Nuevo campo dinámico: fueraDeCategoria
        $rfcVacio = trim((string) $cliente->RFC) === '';
        $idNacionalidad = isset($cliente->IDNacionalidad) ? (string) $cliente->IDNacionalidad : '';
        $cliente->fueraDeCategoria = $rfcVacio || ($idNacionalidad !== 'MX');

        // Si autorizadoApareceEnListas es true, entonces seteamos CNSF y coincidencias como false.
        if ($cliente->autorizadoApareceEnListas === true) {
            $cliente->CNSF = false;
            $cliente->coincidencias = false;
        }

        // Si coincidencias ha sido seteado a false, entonces también hay que setear countCoincidencias = 0
        if ($cliente->coincidencias === false) {
            $cliente->countCoincidencias = 0;
        }

        return $cliente;
    }

    public function verDetallesCliente(TbClientes $id_cliente)
    {
        $cliente = $id_cliente;

        abort_unless($cliente->TipoPersona === $this->tipoPersona, 404);

        $domicilios = TbClientesDomicilio::where('IDCliente', $cliente->IDCliente)->get();
        $operacionesRaw = TbOperaciones::where('IDCliente', $cliente->IDCliente)
            ->with('pagos')
            ->get();

        // Indicadores económicos del cliente (promedios y sumas de primas).
        $indicadores = app(IndicadoresOperacionesCliente::class)->calcular($operacionesRaw);

        $alertas = TbAlertas::where('IDCliente', $cliente->IDCliente)->get();
        // Buscar en CNSF por RFC O CURP del cliente (algunos registros podrían carecer de uno u otro)
        $clienteRFC = strtoupper(trim($cliente->RFC ?? ''));
        $clienteCURP = strtoupper(trim($cliente->CURP ?? ''));

        // Solo buscar si hay RFC o CURP válidos, evita buscar por cadenas vacías
        if ($clienteRFC !== '' || $clienteCURP !== '') {
            $listasNegras = TbListasNegraCNSF::where(function ($q) use ($clienteRFC, $clienteCURP) {
                if ($clienteRFC !== '') {
                    $q->orWhere(DB::raw('UPPER(TRIM(RFC))'), $clienteRFC);
                }
                if ($clienteCURP !== '') {
                    $q->orWhere(DB::raw('UPPER(TRIM(CURP))'), $clienteCURP);
                }
            })->get();
        } else {
            $listasNegras = collect(); // colección vacía si no hay datos con qué buscar
        }

        $perfilTransaccional = TbPerfilTransaccional::where('IDCliente', $cliente->IDCliente)
            ->orderByDesc('IDRegistroPerfil')
            ->first();

        // Solo buscar en listas UIF si hay RFC o CURP válidos, evita buscar por cadenas vacías
        if ($clienteRFC !== '' || $clienteCURP !== '') {
            $listasUIF = TbListasNegrasUIF::where(function ($q) use ($clienteRFC, $clienteCURP) {
                if ($clienteRFC !== '') {
                    $q->orWhere(DB::raw('UPPER(TRIM(RFC))'), $clienteRFC);
                }
                if ($clienteCURP !== '') {
                    $q->orWhere(DB::raw('UPPER(TRIM(CURP))'), $clienteCURP);
                }
            })->get();
        } else {
            $listasUIF = collect(); // colección vacía si no hay datos con qué buscar
        }

        $sistemasDelCliente = CatIDClientesSistema::where('IDCliente', $cliente->IDCliente)
            ->with('sistema')
            ->get();

        // Factores que motivan la marca de alto riesgo del cliente
        $evaluadorRiesgo = app(EvaluadorRiesgoCliente::class);
        $datosRiesgo = $evaluadorRiesgo->evaluar(
            $cliente->IDOcupacionGiro,
            $domicilios->map(fn ($d) => ['IDEstado' => $d->IDEstado])->toArray(),
            $listasNegras->isNotEmpty() || $listasUIF->isNotEmpty(),
            (bool) $cliente->EsPPEActivo
        );

        $perfilAltoRiesgo = $this->perfilEsAltoRiesgo($perfilTransaccional);
        $motivosPerfilRiesgo = $this->motivosPerfilTransaccional($perfilTransaccional);
        $factoresRiesgo = $this->motivosAltoRiesgo(
            $cliente,
            $alertas,
            $datosRiesgo['factores'] ?? [],
            $motivosPerfilRiesgo
        );

        return inertia('Clientes/Detalles', [
            'cliente' => $cliente,
            'modulo' => $this->moduloProps(),
            'domicilios' => $domicilios,
            'operaciones' => $operacionesRaw,
            'alertas' => $alertas,
            'listasNegras' => $listasNegras,
            'perfilTransaccional' => $perfilTransaccional,
            'listasUIF' => $listasUIF,
            'sistemasDelCliente' => $sistemasDelCliente,
            'esAltoRiesgo' => (bool) $cliente->EsAltoRiesgo || $perfilAltoRiesgo,
            'perfilAltoRiesgo' => $perfilAltoRiesgo,
            'factoresRiesgo' => $factoresRiesgo,
            'motivosPerfilRiesgo' => $motivosPerfilRiesgo,
            'indicadores' => $indicadores,
            'catalogos' => $this->catalogosOperaciones(),
        ]);
    }

    /**
     * Indica si el perfil transaccional clasifica al cliente en alto riesgo.
     */
    private function perfilEsAltoRiesgo(?TbPerfilTransaccional $perfil): bool
    {
        return $perfil !== null
            && is_numeric($perfil->Perfil)
            && (float) $perfil->Perfil >= 2;
    }

    /**
     * Construye los motivos legibles de la clasificación de alto riesgo.
     *
     * @param  array<int, string>  $factoresEvaluados
     * @param  array<int, string>  $motivosPerfil
     * @return array<int, string>
     */
    private function motivosAltoRiesgo(
        TbClientes $cliente,
        $alertas,
        array $factoresEvaluados,
        array $motivosPerfil
    ): array {
        $motivos = $factoresEvaluados;

        // Respaldo con las alertas guardadas: cubre clasificaciones históricas
        // cuyo reevaluador ya no devuelve factores (datos o listas actualizados).
        if ((bool) $cliente->EsAltoRiesgo && empty($factoresEvaluados)) {
            $patrones = [
                EvaluadorRiesgoCliente::PATRON_ACTIVIDAD_VULNERABLE,
                EvaluadorRiesgoCliente::PATRON_ZONA_GEOGRAFICA,
                'Personas Bloqueadas',
                'PPE',
            ];

            foreach ($alertas as $alerta) {
                $patron = trim((string) ($alerta->Patron ?? ''));
                if (! in_array($patron, $patrones, true)) {
                    continue;
                }

                $detalle = trim((string) ($alerta->Razones ?? ''));
                if ($detalle === '') {
                    $detalle = trim((string) ($alerta->Descripcion ?? ''));
                }
                if ($detalle !== '') {
                    $motivos[] = 'Alerta PLD - '.$patron.': '.$detalle;
                }
            }
        }

        foreach ($motivosPerfil as $motivo) {
            $motivos[] = 'Perfil transaccional: '.$motivo;
        }

        return $this->deduplicarTextos($motivos);
    }

    /**
     * Describe las dimensiones que contribuyen a un perfil transaccional alto.
     *
     * @return array<int, string>
     */
    private function motivosPerfilTransaccional(?TbPerfilTransaccional $perfil): array
    {
        if (! $this->perfilEsAltoRiesgo($perfil)) {
            return [];
        }

        $niveles = [1 => 'Bajo', 2 => 'Medio', 3 => 'Alto'];
        $decimal = fn ($valor) => is_numeric($valor)
            ? number_format((float) $valor, 2, '.', '')
            : trim((string) $valor);

        $motivos = [];
        $dimensiones = [
            ['NivelRiesgoNac', 'CalculoNacimiento', 'Nacionalidad', null],
            ['NivelRiesgoResidencia', 'CalculoResidencia', 'Residencia', null],
            ['NivelRiesgo', 'CalculoOcupacion', 'Ocupación/giro', trim((string) ($perfil->OcupGiro ?? ''))],
        ];

        foreach ($dimensiones as [$nivelKey, $calculoKey, $etiqueta, $detalle]) {
            $nivel = $perfil->{$nivelKey} ?? null;
            $calculo = $perfil->{$calculoKey} ?? null;
            if (($nivel === null || $nivel === '') && ($calculo === null || $calculo === '')) {
                continue;
            }

            $partes = [];
            if ($detalle !== null && $detalle !== '') {
                $partes[] = '"'.$detalle.'"';
            }
            if ($nivel !== null && $nivel !== '') {
                $partes[] = 'nivel '.($niveles[(int) $nivel] ?? (string) $nivel);
            }
            if ($calculo !== null && $calculo !== '') {
                $partes[] = 'aporte '.$decimal($calculo);
            }

            $motivos[] = $etiqueta.': '.implode('; ', $partes).'.';
        }

        if ($perfil->TotalUbicacion !== null && $perfil->TotalUbicacion !== '') {
            $motivos[] = 'Ubicación total: '.$decimal($perfil->TotalUbicacion).'.';
        }
        if ($perfil->TotalEconomico !== null && $perfil->TotalEconomico !== '') {
            $motivos[] = 'Económico total: '.$decimal($perfil->TotalEconomico).'.';
        }
        if ($perfil->Perfil !== null && $perfil->Perfil !== '') {
            $motivos[] = 'Perfil total calculado: '.$decimal($perfil->Perfil).' (alto riesgo a partir de 2).';
        }

        return $this->deduplicarTextos($motivos);
    }

    /**
     * Elimina motivos vacíos o repetidos conservando el orden original.
     *
     * @param  array<int, string>  $valores
     * @return array<int, string>
     */
    private function deduplicarTextos(array $valores): array
    {
        $resultado = [];
        $vistos = [];

        foreach ($valores as $valor) {
            $texto = trim((string) $valor);
            if ($texto === '') {
                continue;
            }

            $clave = mb_strtolower($texto);
            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $resultado[] = $texto;
        }

        return $resultado;
    }

    /**
     * Catálogos usados por los filtros de la pestaña de Operaciones.
     */
    private function catalogosOperaciones(): array
    {
        return [
            'monedas' => CatMonedas::orderBy('IDMoneda')->get(['IDMoneda', 'Moneda']),
            'formasPago' => CatFormaPagos::orderBy('FormaPago')->get(['IDFormaPago', 'FormaPago']),
            'tiposPago' => CatTipoPago::orderBy('TipoPago')->get(['IDTipoPago', 'TipoPago']),
        ];
    }

    /**
     * Exporta a CSV todas las operaciones del cliente (emisiones y endosos).
     */
    public function exportOperacionesCsv(TbClientes $id_cliente)
    {
        $cliente = $id_cliente;

        abort_unless($cliente->TipoPersona === $this->tipoPersona, 404);

        $operaciones = TbOperaciones::where('IDCliente', $cliente->IDCliente)
            ->with('pagos')
            ->orderBy('FolioPoliza')
            ->orderByRaw("CASE WHEN FolioEndoso IS NULL OR FolioEndoso = '' THEN 0 ELSE 1 END")
            ->orderBy('FechaEmision')
            ->orderBy('IDOperacion')
            ->get();

        if ($operaciones->isEmpty()) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => "El {$this->sustantivoSingular} no tiene operaciones registradas para exportar.",
            ]);
        }

        $indicadoresService = app(IndicadoresOperacionesCliente::class);

        $nombreCliente = trim((string) ($cliente->RazonSocial ?? '')) !== ''
            ? $cliente->RazonSocial
            : trim(implode(' ', array_filter([
                $cliente->Nombre,
                $cliente->ApellidoPaterno,
                $cliente->ApellidoMaterno,
            ])));

        $formasPago = CatFormaPagos::pluck('FormaPago', 'IDFormaPago');
        $tiposPago = CatTipoPago::pluck('TipoPago', 'IDTipoPago');

        $fileName = 'operaciones_'.$this->sustantivoSingular.'_'.$cliente->IDCliente.'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($operaciones, $cliente, $nombreCliente, $indicadoresService, $formasPago, $tiposPago) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($file, [
                'ID Cliente',
                'Nombre/Razón Social',
                'RFC',
                'ID Operación',
                'Folio Póliza',
                'Folio Endoso',
                'Tipo de operación',
                'Fecha Emisión',
                'Inicio Vigencia',
                'Fin Vigencia',
                'Prima Total',
                'Moneda',
                'Gastos Emisión',
                'Agente',
                'RFC Agente',
                'CURP Agente',
                'Forma de Pago',
                'Tipo de Pago',
                'Paga Tercero',
                'Esquema de Pago',
                'Núm. Pagos',
                'Total Pagado',
                'Saldo Pendiente',
                'Cancelada',
            ]);

            foreach ($operaciones as $op) {
                $moneda = strtoupper(trim((string) ($op->IDMoneda ?? '')));
                $prima = $indicadoresService->prima($op);

                // El total pagado se acumula solo con los pagos en la misma moneda
                // de la operación, para no mezclar importes entre monedas.
                $totalPagado = 0.0;
                $numPagos = 0;
                foreach ($op->pagos ?? [] as $pago) {
                    if (strtoupper(trim((string) ($pago->IDMoneda ?? ''))) !== $moneda) {
                        continue;
                    }
                    $totalPagado += round((float) ($pago->Monto ?? 0), 2);
                    $numPagos++;
                }

                $idFormaPago = $op->IDFormaPago ?? null;
                if ($idFormaPago === null || $idFormaPago === '') {
                    $primerPago = ($op->pagos ?? collect())->first();
                    $idFormaPago = $primerPago->IDFormaPago ?? null;
                }

                fputcsv($file, [
                    $cliente->IDCliente,
                    $nombreCliente,
                    $cliente->RFC,
                    $op->IDOperacion,
                    $op->FolioPoliza,
                    $op->FolioEndoso,
                    $indicadoresService->tipoOperacion($op),
                    $op->FechaEmision,
                    $op->FechaInicioVigencia,
                    $op->FechaFinVigencia,
                    number_format($prima, 2, '.', ''),
                    $op->IDMoneda,
                    number_format((float) ($op->GastosEmision ?? 0), 2, '.', ''),
                    trim(implode(' ', array_filter([
                        $op->NombreAgente,
                        $op->APaternoAgente,
                        $op->AMaternoAgente,
                    ]))),
                    $op->RFCAgente,
                    $op->CURPAgente,
                    $idFormaPago !== null && $idFormaPago !== ''
                        ? ($formasPago[$idFormaPago] ?? $idFormaPago)
                        : '',
                    $op->IDTipoPago !== null && $op->IDTipoPago !== ''
                        ? ($tiposPago[$op->IDTipoPago] ?? $op->IDTipoPago)
                        : '',
                    $op->PagaTercero !== null ? ($op->PagaTercero ? 'Sí' : 'No') : '',
                    $op->EsquemaDePago,
                    $numPagos,
                    number_format($totalPagado, 2, '.', ''),
                    number_format($prima - $totalPagado, 2, '.', ''),
                    $indicadoresService->esCancelacion($op) ? 'Sí' : 'No',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Exporta a CSV las estadísticas del cliente, con una fila por moneda.
     */
    public function exportEstadisticasCsv(TbClientes $id_cliente)
    {
        $cliente = $id_cliente;

        abort_unless($cliente->TipoPersona === $this->tipoPersona, 404);

        $operaciones = TbOperaciones::where('IDCliente', $cliente->IDCliente)
            ->with('pagos')
            ->get();

        if ($operaciones->isEmpty()) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => "El {$this->sustantivoSingular} no tiene operaciones registradas para exportar sus estadísticas.",
            ]);
        }

        $indicadores = app(IndicadoresOperacionesCliente::class)->calcular($operaciones);

        $nombreCliente = trim((string) ($cliente->RazonSocial ?? '')) !== ''
            ? $cliente->RazonSocial
            : trim(implode(' ', array_filter([
                $cliente->Nombre,
                $cliente->ApellidoPaterno,
                $cliente->ApellidoMaterno,
            ])));

        $fileName = 'estadisticas_'.$this->sustantivoSingular.'_'.$cliente->IDCliente.'_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($indicadores, $cliente, $nombreCliente) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($file, [
                'ID Cliente',
                'Nombre/Razón Social',
                'RFC',
                'Moneda',
                'Total operaciones',
                'Emisiones',
                'Endosos',
                'Endosos de aumento',
                'Endosos de disminución',
                'Endosos de cancelación',
                'Endosos sin cambio',
                'Operaciones canceladas',
                'Pólizas',
                'Pagos',
                'Suma prima total',
                'Prima promedio por operación',
                'Prima total modal',
                'Frecuencia de la moda',
                'Suma prima emisiones',
                'Prima promedio emisiones',
                'Suma aumentos',
                'Prima promedio aumentos',
                'Suma disminuciones',
                'Prima promedio disminuciones',
                'Prima cancelaciones',
                'Prima máxima',
                'Prima mínima',
                'Gastos de emisión',
                'Promedio gastos de emisión',
                'Balance de pólizas',
                'Balance promedio por póliza',
                'Total pagado',
                'Saldo pendiente',
            ]);

            foreach ($indicadores['por_moneda'] as $ind) {
                $moda = $ind['moda_prima_total'];

                fputcsv($file, [
                    $cliente->IDCliente,
                    $nombreCliente,
                    $cliente->RFC,
                    $ind['moneda'],
                    $ind['total_operaciones'],
                    $ind['total_emisiones'],
                    $ind['total_endosos'],
                    $ind['total_aumentos'],
                    $ind['total_disminuciones'],
                    $ind['total_cancelaciones'],
                    $ind['total_sin_cambio'],
                    $ind['total_operaciones_canceladas'],
                    $ind['num_polizas'],
                    $ind['num_pagos'],
                    number_format($ind['suma_prima_total'], 2, '.', ''),
                    number_format($ind['promedio_prima_total'], 2, '.', ''),
                    $moda === null ? '' : number_format($moda, 2, '.', ''),
                    $ind['moda_prima_total_frecuencia'],
                    number_format($ind['suma_prima_emisiones'], 2, '.', ''),
                    number_format($ind['promedio_prima_emisiones'], 2, '.', ''),
                    number_format($ind['suma_aumentos'], 2, '.', ''),
                    number_format($ind['promedio_aumentos'], 2, '.', ''),
                    number_format($ind['suma_disminuciones'], 2, '.', ''),
                    number_format($ind['promedio_disminuciones'], 2, '.', ''),
                    number_format($ind['suma_prima_cancelaciones'], 2, '.', ''),
                    number_format($ind['prima_max'], 2, '.', ''),
                    number_format($ind['prima_min'], 2, '.', ''),
                    number_format($ind['suma_gastos_emision'], 2, '.', ''),
                    number_format($ind['promedio_gastos_emision'], 2, '.', ''),
                    number_format($ind['suma_balance_polizas'], 2, '.', ''),
                    number_format($ind['promedio_balance_por_poliza'], 2, '.', ''),
                    number_format($ind['total_pagado'], 2, '.', ''),
                    number_format($ind['saldo_pendiente'], 2, '.', ''),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function descargarPlantillaCsv(ImportadorPersonas $importador): StreamedResponse
    {
        $this->abortUnlessGestion();

        $plantilla = $importador->plantilla($this->tipoPersona);
        $headers = $plantilla['headers'] ?? [];
        $ejemplos = $plantilla['ejemplos'] ?? [];
        $nombre = 'plantilla_'.$this->sustantivoPlural.'.csv';

        return response()->streamDownload(function () use ($headers, $ejemplos) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, $headers, ',', '"', '\\');

            foreach ($ejemplos as $ejemplo) {
                $fila = [];
                foreach ($headers as $header) {
                    $fila[] = $ejemplo[$header] ?? '';
                }
                fputcsv($file, $fila, ',', '"', '\\');
            }

            fclose($file);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function prevalidarImportacion(Request $request, ImportadorPersonas $importador)
    {
        $this->abortUnlessGestion();

        $contentLength = (int) ($request->server('CONTENT_LENGTH') ?? 0);
        $postMax = $this->bytes(ini_get('post_max_size') ?: '0');
        if ($contentLength > 0 && $postMax > 0 && $contentLength > $postMax) {
            return back()->withErrors([
                'archivo' => 'El archivo excede el límite de carga del servidor (post_max_size = '.ini_get('post_max_size').').',
            ])->withInput();
        }

        $archivo = $request->file('archivo');
        if ($archivo instanceof UploadedFile && ! $archivo->isValid()) {
            $limite = ini_get('upload_max_filesize') ?: '2M';
            $mensaje = match ($archivo->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "El archivo excede el límite de carga del servidor ({$limite}). Reduce el archivo o solicita que se aumente upload_max_filesize/post_max_size.",
                UPLOAD_ERR_PARTIAL => 'La carga del archivo se interrumpió. Intenta subirlo de nuevo.',
                UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene configurado un directorio temporal para las cargas.',
                UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo temporal. Revisa permisos y espacio en disco.',
                UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la carga del archivo.',
                default => 'No fue posible subir el archivo. Revisa el tamaño y vuelve a intentarlo.',
            };

            return back()->withErrors(['archivo' => $mensaje])->withInput();
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [
            'archivo.required' => 'Selecciona un archivo CSV.',
            'archivo.file' => 'El archivo no es válido.',
            'archivo.uploaded' => 'No fue posible subir el archivo. Verifica el tamaño permitido por el servidor.',
            'archivo.mimes' => 'El archivo debe ser CSV o TXT.',
            'archivo.max' => 'El archivo no debe superar los 10 MB.',
        ]);

        try {
            $importacion = $importador->prevalidar(
                $archivo,
                $this->tipoPersona,
                $request->user()?->id
            );
        } catch (Throwable $e) {
            Log::error('Error al prevalidar importación de '.$this->sustantivoPlural.': '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors([
                'archivo' => 'No fue posible validar el archivo. Intenta nuevamente.',
            ])->withInput();
        }

        $ok = $importacion->estado === 'prevalidado';

        return redirect()->back()->with([
            'importacion_reciente' => $this->resumenImportacion($importacion),
            'import_errors' => $importacion->errores ?? [],
            'toast' => [
                'type' => $ok ? 'success' : 'error',
                'message' => $ok
                    ? "Archivo prevalidado: {$importacion->filas_validas} fila(s) válida(s). Confirma la importación."
                    : "La prevalidación encontró {$importacion->filas_error} error(es). Corrige el CSV y vuelve a cargarlo.",
            ],
        ]);
    }

    public function confirmarImportacion(PersonaImportacion $importacion, ImportadorPersonas $importador)
    {
        $this->abortUnlessGestion();
        abort_unless($importacion->tipo_persona === $this->tipoPersona, 404);

        $importacion = $importador->confirmar($importacion);
        $ok = $importacion->estado === 'importado';

        $message = $ok
            ? "Se importaron {$importacion->filas_validas} {$this->sustantivoPlural} correctamente."
            : 'No fue posible importar el archivo. Revisa los errores mostrados.';

        return redirect()->back()->with([
            'importacion_reciente' => $this->resumenImportacion($importacion),
            'import_errors' => $importacion->errores ?? [],
            'toast' => [
                'type' => $ok ? 'success' : 'error',
                'message' => $message,
            ],
        ]);
    }

    public function descargarErroresImportacion(PersonaImportacion $importacion): StreamedResponse
    {
        $this->abortUnlessGestion();
        abort_unless($importacion->tipo_persona === $this->tipoPersona, 404);

        $errores = $importacion->errores ?? [];

        return response()->streamDownload(function () use ($errores) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['linea', 'campo', 'valor', 'mensaje'], ',', '"', '\\');

            foreach ($errores as $error) {
                fputcsv($file, [
                    $error['linea'] ?? '',
                    $error['campo'] ?? '',
                    $error['valor'] ?? '',
                    $error['mensaje'] ?? '',
                ], ',', '"', '\\');
            }

            fclose($file);
        }, 'errores_'.$this->sustantivoPlural.'_'.$importacion->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenImportacion(PersonaImportacion $importacion): array
    {
        return [
            'id' => $importacion->id,
            'tipo_persona' => $importacion->tipo_persona,
            'estado' => $importacion->estado,
            'total_filas' => $importacion->total_filas,
            'filas_validas' => $importacion->filas_validas,
            'filas_error' => $importacion->filas_error,
            'resumen' => $importacion->resumen,
            'nombre_archivo' => $importacion->nombre_archivo,
        ];
    }

    private function abortUnlessGestion(): void
    {
        abort_unless(in_array($this->tipoPersona, [TbClientes::TIPO_AGENTE, TbClientes::TIPO_COLABORADOR], true), 404);
    }

    private function bytes(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === '-1') {
            return 0;
        }

        $unidad = strtolower(substr($valor, -1));
        $numero = (float) $valor;

        return (int) match ($unidad) {
            'g' => $numero * 1024 * 1024 * 1024,
            'm' => $numero * 1024 * 1024,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    public function activarCliente(TbClientes $id_cliente)
    {
        abort_unless($id_cliente->TipoPersona === $this->tipoPersona, 404);

        $id_cliente->Activo = 1;
        $id_cliente->save();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => ucfirst($this->sustantivoSingular).' activado correctamente.',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validarPersona($request);
        $domicilio = $this->normalizarDomicilio($data['domicilio']);

        $resultado = null;

        DB::beginTransaction();
        try {
            $persona = TbClientes::create(array_merge($this->datosPersona($data), [
                'Activo' => true,
                'CoincideEnListasNegras' => 0,
                'EsPPEActivo' => false,
                'EsAltoRiesgo' => false,
            ]));

            $persona->domicilios()->create($domicilio);

            $resultado = app(RegistroPLDService::class)
                ->analizarYRegistrar($persona, [$domicilio]);

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error al crear '.$this->sustantivoSingular.': '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors([
                'general' => 'No se pudo crear el '.$this->sustantivoSingular.'. Intenta nuevamente.',
            ]);
        }

        app(RegistroPLDService::class)->notificar($persona, $resultado);

        $message = ucfirst($this->sustantivoSingular).' creado correctamente.';
        if ($resultado['personaBloqueada'] ?? false) {
            $message .= ' Cuenta con coincidencias en listas.';
        }
        if ($resultado['esPPE'] ?? false) {
            $message .= ' Fue detectado como PPE.';
        }
        if ($resultado['esAltoRiesgo'] ?? false) {
            $message .= ' Fue marcado como alto riesgo.';
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function update(Request $request, TbClientes $id_cliente)
    {
        abort_unless($id_cliente->TipoPersona === $this->tipoPersona, 404);

        $data = $this->validarPersona($request, $id_cliente->IDCliente);
        $domicilio = $this->normalizarDomicilio($data['domicilio']);

        $resultado = null;

        DB::beginTransaction();
        try {
            $id_cliente->update($this->datosPersona($data));

            $id_cliente->domicilios()->delete();
            $id_cliente->domicilios()->create($domicilio);

            $resultado = app(RegistroPLDService::class)
                ->analizarYRegistrar($id_cliente, [$domicilio], 'Detectado');

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Error al actualizar '.$this->sustantivoSingular.': '.$e->getMessage(), [
                'IDCliente' => $id_cliente->IDCliente,
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors([
                'general' => 'No se pudo actualizar el '.$this->sustantivoSingular.'. Intenta nuevamente.',
            ]);
        }

        app(RegistroPLDService::class)->notificar($id_cliente, $resultado);

        $message = ucfirst($this->sustantivoSingular).' actualizado correctamente.';
        if ($resultado['personaBloqueada'] ?? false) {
            $message .= ' Cuenta con coincidencias en listas.';
        }
        if ($resultado['esPPE'] ?? false) {
            $message .= ' Fue detectado como PPE.';
        }
        if ($resultado['esAltoRiesgo'] ?? false) {
            $message .= ' Fue marcado como alto riesgo.';
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    public function alternarEstatus(TbClientes $id_cliente)
    {
        abort_unless($id_cliente->TipoPersona === $this->tipoPersona, 404);

        $id_cliente->Activo = ! $id_cliente->Activo;
        $id_cliente->save();

        $estado = $id_cliente->Activo ? 'activado' : 'desactivado';

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => ucfirst($this->sustantivoSingular).' '.$estado.' correctamente.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validarPersona(Request $request, ?int $idCliente = null): array
    {
        if ($request->filled('RFC')) {
            $request->merge(['RFC' => strtoupper(trim((string) $request->input('RFC')))]);
        }
        if ($request->filled('CURP')) {
            $request->merge(['CURP' => strtoupper(trim((string) $request->input('CURP')))]);
        }

        $esFisica = (string) $request->input('IDTipoPersona') === '1';

        $data = $request->validate([
            'IDTipoPersona' => ['required', 'integer', 'in:1,2'],
            'RFC' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tbClientes', 'RFC')->ignore($idCliente, 'IDCliente'),
            ],
            'Nombre' => ['nullable', 'string', 'max:255', Rule::requiredIf($esFisica)],
            'ApellidoPaterno' => ['nullable', 'string', 'max:255', Rule::requiredIf($esFisica)],
            'ApellidoMaterno' => ['nullable', 'string', 'max:255'],
            'RazonSocial' => ['nullable', 'string', 'max:255', Rule::requiredIf(! $esFisica)],
            'CURP' => ['nullable', 'string', 'max:18'],
            'IDNacionalidad' => ['required', 'string', 'exists:catNacionalidad,IDNacionalidad'],
            'IDOcupacionGiro' => ['nullable', 'integer', 'exists:catOcupacionesGiros,IDOcupacionGiro'],
            'FechaNacimiento' => ['nullable', 'date'],
            'FechaConstitucion' => ['nullable', 'date'],
            'FolioMercantil' => ['nullable', 'string', 'max:255'],
            'IDEstadoNacimiento' => ['nullable', 'string', 'max:10', 'exists:catEstados,IDEstado'],
            'IngresosEstimados' => ['nullable', 'numeric', 'min:0'],
            'Preguntas' => ['nullable', 'string'],

            'domicilio' => ['required', 'array'],
            'domicilio.Calle' => ['required', 'string', 'max:255'],
            'domicilio.NoExterior' => ['nullable', 'string', 'max:255'],
            'domicilio.NoInterior' => ['nullable', 'string', 'max:255'],
            'domicilio.Colonia' => ['required', 'string', 'max:255'],
            'domicilio.CP' => ['required', 'string', 'max:10'],
            'domicilio.IDEstado' => ['required', 'exists:catEstados,IDEstado'],
            'domicilio.Municipio' => ['required', 'string', 'max:255'],
            'domicilio.Localidad' => ['nullable', 'string', 'max:255'],
            'domicilio.Telefono' => ['nullable', 'string', 'max:20'],
        ]);

        $data['RFC'] = strtoupper(trim($data['RFC']));
        if (! empty($data['CURP'])) {
            $data['CURP'] = strtoupper(trim($data['CURP']));
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function datosPersona(array $data): array
    {
        $esFisica = (int) $data['IDTipoPersona'] === 1;
        $idOcupacionGiro = $data['IDOcupacionGiro'] ?? null;

        if (empty($idOcupacionGiro) && $this->tipoPersona === TbClientes::TIPO_COLABORADOR) {
            $idOcupacionGiro = $this->ocupacionPorDefectoColaborador();
        }

        return [
            'RFC' => $data['RFC'],
            'Nombre' => $esFisica ? ($data['Nombre'] ?? null) : null,
            'ApellidoPaterno' => $esFisica ? ($data['ApellidoPaterno'] ?? null) : null,
            'ApellidoMaterno' => $esFisica ? ($data['ApellidoMaterno'] ?? null) : null,
            'RazonSocial' => $esFisica ? null : ($data['RazonSocial'] ?? null),
            'IDTipoPersona' => $data['IDTipoPersona'],
            'CURP' => $esFisica ? ($data['CURP'] ?? null) : null,
            'IDOcupacionGiro' => $idOcupacionGiro,
            'FechaNacimiento' => $esFisica ? ($data['FechaNacimiento'] ?? null) : null,
            'FechaConstitucion' => $esFisica ? null : ($data['FechaConstitucion'] ?? null),
            'FolioMercantil' => $data['FolioMercantil'] ?? null,
            'IDNacionalidad' => $data['IDNacionalidad'],
            'IDEstadoNacimiento' => $data['IDEstadoNacimiento'] ?? null,
            'IngresosEstimados' => $data['IngresosEstimados'] ?? null,
            'Preguntas' => $data['Preguntas'] ?? null,
            'TipoPersona' => $this->tipoPersona,
            'ckAgente' => $this->tipoPersona === TbClientes::TIPO_AGENTE ? 1 : 0,
        ];
    }

    private function ocupacionPorDefectoColaborador(): ?int
    {
        $id = CatOcupacionesGiros::where('OcupacionGiro', 'like', 'Empleado del sector privado%')
            ->value('IDOcupacionGiro');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  array<string, mixed>  $domicilio
     * @return array<string, mixed>
     */
    private function normalizarDomicilio(array $domicilio): array
    {
        return [
            'Calle' => $domicilio['Calle'],
            'NoExterior' => $domicilio['NoExterior'] ?? null,
            'NoInterior' => $domicilio['NoInterior'] ?? null,
            'Colonia' => $domicilio['Colonia'],
            'CP' => $domicilio['CP'],
            'IDEstado' => (string) $domicilio['IDEstado'],
            'Municipio' => $domicilio['Municipio'],
            'Localidad' => $domicilio['Localidad'] ?? null,
            'Telefono' => $domicilio['Telefono'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogosPersona(): array
    {
        return [
            'tiposPersona' => CatTipoPersona::orderBy('IDTipoPersona')
                ->get(['IDTipoPersona', 'TipoPersona']),
            'nacionalidades' => CatNacionalidad::orderBy('Nacionalidad')
                ->get(['IDNacionalidad', 'Nacionalidad']),
            'ocupaciones' => CatOcupacionesGiros::orderBy('OcupacionGiro')
                ->get(['IDOcupacionGiro', 'OcupacionGiro', 'NivelRiesgo']),
            'estados' => CatEstados::orderBy('Estado')
                ->get(['IDEstado', 'Estado']),
        ];
    }

    /**
     * Metadatos del módulo usados por las vistas compartidas de Inertia.
     */
    protected function moduloProps(): array
    {
        return [
            'basePath' => $this->rutaBase,
            'titulo' => $this->tituloModulo,
            'singular' => $this->sustantivoSingular,
            'plural' => $this->sustantivoPlural,
            'tipoPersona' => $this->tipoPersona,
        ];
    }
}
