<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;
use App\Models\ListasBloqueadas\TbListasNegraCNSF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientesController extends Controller
{
    public function index(Request $request)
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
                            $q->orWhere(function ($subQuery) {
                                $subQuery->where('CoincideEnListasNegras', '>', 0);
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

        $perPage = $request->input('per_page', 10);
        $clientes = $query->paginate($perPage)->withQueryString();

        // Obtener TODOS los RFCs (únicamente) de la lista negra, a mayúsculas y sin espacios
        $rfcEnListaNegra = TbListasNegraCNSF::pluck('RFC')
            ->map(function ($rfc) {
                return strtoupper(trim($rfc ?? ''));
            })
            ->filter(function ($rfc) {
                return $rfc !== '';
            })
            ->unique()
            ->toArray();

        // Añadir los campos dinámicos 'CNSF', 'coincidencias', 'autorizadoApareceEnListas', 'countCoincidencias', 'esPPE' y 'fueraDeCategoria' a cada cliente
        $clientes->getCollection()->transform(function ($cliente) use ($rfcEnListaNegra) {
            $clienteRFC = strtoupper(trim($cliente->RFC ?? ''));
            $cliente->CNSF = $clienteRFC !== '' && in_array($clienteRFC, $rfcEnListaNegra, true);

            // Añadir el campo booleano "coincidencias"
            $cliente->coincidencias = ((int) $cliente->CoincideEnListasNegras) > 0;
            $cliente->countCoincidencias = $cliente->CoincideEnListasNegras;
            $cliente->autorizadoApareceEnListas = ((int) $cliente->Activo === 1) && ((int) $cliente->CoincideEnListasNegras > 0);
            $cliente->esPPE = $cliente->EsPPEActivo;

            // Nuevo campo dinámico: fueraDeCategoria
            // true si RFC está vacío O si IDNacionalidad != "MX" (IDNacionalidad es string)
            $rfcVacio = trim((string) $cliente->RFC) === '';
            $idNacionalidad = isset($cliente->IDNacionalidad) ? (string) $cliente->IDNacionalidad : '';
            $cliente->fueraDeCategoria = $rfcVacio || ($idNacionalidad !== 'MX');

            return $cliente;
        });

        // return response()->json($clientes);

        return inertia('Clientes/Index', [
            'clientes' => $clientes,
            'filters' => $request->only(['search', 'tipo', 'per_page', 'category']),
            'toast' => session('toast'),
        ]);
    }
}
