<?php

namespace App\Http\Controllers;

use App\Models\Clientes\CatIDClientesSistema;
use App\Models\Clientes\TbClientes;
use App\Models\Clientes\TbClientesDomicilio;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use App\Models\ListasBloqueadas\TbListasNegrasUIF;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbOperacionesPagos;
use App\Models\TbPerfilTransaccional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientesController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->buildQuery($request);

        // Ordenar los clientes de forma descendente por el campo 'id' (puedes cambiar a otro campo si se requiere diferente criterio)
        $query->orderByDesc('IDCliente');

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
                'message' => 'No hay clientes para exportar con los filtros seleccionados.'
            ]);
        }

        $rfcEnListaNegra = $this->getRfcEnListaNegra();

        $fileName = 'clientes_' . date('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
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
                'Fuera de Categoría'
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
                    $cliente->fueraDeCategoria ? 'Sí' : 'No'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function buildQuery(Request $request)
    {
        $query = TbClientes::query();

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
        $domicilios = TbClientesDomicilio::where('IDCliente', $cliente->IDCliente)->get();
        $operacionesRaw = TbOperaciones::where('IDCliente', $cliente->IDCliente)
            ->with('pagos')
            ->get();

        $polizas = $operacionesRaw->groupBy('FolioPoliza')->map(function ($ops, $folioPoliza) {
            $operacionesPrincipales = $ops->filter(fn($op) => empty($op->FolioEndoso))->values();
            $endosos = $ops->filter(fn($op) => !empty($op->FolioEndoso))->values();
            $balancePrimaTotal = $ops->sum('PrimaTotal');

            return [
                'folio_poliza' => $folioPoliza,
                'operaciones' => $operacionesPrincipales,
                'endosos' => $endosos,
                'todas_operaciones' => $ops->values(),
                'balance_prima_total' => $balancePrimaTotal,
            ];
        })->values();
        $alertas = TbAlertas::where('IDCliente', $cliente->IDCliente)->get();

        // Buscar en CNSF por RFC O CURP del cliente (algunos registros podrían carecer de uno u otro)
        $clienteRFC = strtoupper(trim($cliente->RFC ?? ''));
        $clienteCURP = strtoupper(trim($cliente->CURP ?? ''));

        // Solo buscar si hay RFC o CURP válidos, evita buscar por cadenas vacías
        if ($clienteRFC !== '' || $clienteCURP !== '') {
            $listasNegras = TbListasNegraCNSF::where(function($q) use ($clienteRFC, $clienteCURP) {
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

        // Si no se encuentra perfil, intenta buscar el registro más reciente sin filtrar por cliente (fallback)
        if (!$perfilTransaccional) {
            $perfilTransaccional = TbPerfilTransaccional::orderByDesc('IDRegistroPerfil')->first();
        }

        // Solo buscar en listas UIF si hay RFC o CURP válidos, evita buscar por cadenas vacías
        if ($clienteRFC !== '' || $clienteCURP !== '') {
            $listasUIF = TbListasNegrasUIF::where(function($q) use ($clienteRFC, $clienteCURP) {
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

        return inertia('Clientes/Detalles', [
            'cliente' => $cliente,
            'domicilios' => $domicilios,
            'operaciones' => $operacionesRaw,
            'polizas' => $polizas,
            'alertas' => $alertas,
            'listasNegras' => $listasNegras,
            'perfilTransaccional' => $perfilTransaccional,
            'listasUIF' => $listasUIF,
            'sistemasDelCliente' => $sistemasDelCliente,
        ]);
    }

    public function activarCliente(TbClientes $id_cliente)
    {
        $id_cliente->Activo = 1;
        $id_cliente->save();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Cliente activado correctamente.',
        ]);
    }
}
