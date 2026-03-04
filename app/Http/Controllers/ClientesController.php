<?php

namespace App\Http\Controllers;

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

        $perPage = $request->input('per_page', 10);
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
            'filters' => $request->only(['search', 'tipo', 'per_page', 'category']),
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

        // Filtro por búsqueda
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('Nombre', 'like', "%{$search}%")
                    ->orWhere('ApellidoPaterno', 'like', "%{$search}%")
                    ->orWhere('ApellidoMaterno', 'like', "%{$search}%")
                    ->orWhere('RFC', 'like', "%{$search}%")
                    ->orWhere('CURP', 'like', "%{$search}%")
                    ->orWhere('RazonSocial', 'like', "%{$search}%");
            });
        }

        // Filtro por tipo de persona
        if ($request->filled('tipo') && $request->input('tipo') !== 'todos') {
            if ($request->input('tipo') === 'fisica') {
                $query->where('IDTipoPersona', 1);
            } elseif ($request->input('tipo') === 'moral') {
                $query->where('IDTipoPersona', '!=', 1);
            }
        }

        // Filtro por categoría PLD
        if ($request->filled('category') && $request->input('category') !== 'todos') {
            $categories = $request->input('category');
            if (!is_array($categories)) {
                $categories = [$categories];
            }

            $query->where(function ($q) use ($categories) {
                foreach ($categories as $category) {
                    switch ($category) {
                        case 'sin-coincidencia':
                            $q->orWhere(function ($subQuery) {
                                $subQuery->where('CoincideEnListasNegras', 0)
                                    ->where(function ($q2) {
                                        $q2->whereNull('RFC')
                                            ->orWhere('RFC', '')
                                            ->orWhereNotIn(DB::raw('UPPER(TRIM(RFC))'), TbListasNegraCNSF::pluck('RFC')->map(fn ($rfc) => strtoupper(trim($rfc ?? '')))->filter(fn ($rfc) => $rfc !== ''));
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
                            $q->orWhereIn(DB::raw('UPPER(TRIM(RFC))'), TbListasNegraCNSF::pluck('RFC')->map(fn ($rfc) => strtoupper(trim($rfc ?? '')))->filter(fn ($rfc) => $rfc !== ''));
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
        $operaciones = TbOperaciones::where('IDCliente', $cliente->IDCliente)
            ->with('pagos')
            ->get();
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

        return inertia('Clientes/Detalles', [
            'cliente' => $cliente,
            'domicilios' => $domicilios,
            'operaciones' => $operaciones,
            'alertas' => $alertas,
            'listasNegras' => $listasNegras,
            'perfilTransaccional' => $perfilTransaccional,
            'listasUIF' => $listasUIF,
        ]);
    }
}
