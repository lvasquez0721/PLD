<?php

namespace App\Http\Controllers;

use App\Models\LogApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class LogsController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'metodo' => 'nullable|string|max:10',
            'ruta' => 'nullable|string|max:500',
            'estatus' => 'nullable|integer|min:100|max:599',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'q_payload' => 'nullable|string|max:255',
            'q_response' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:id,fecha,usuario,metodo,ruta,ip,estatus,duracion_ms',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $sortMap = [
            'id' => 'logApi.id',
            'fecha' => 'logApi.created_at',
            'usuario' => 'logApi.Usuario',
            'metodo' => 'logApi.Metodo',
            'ruta' => 'logApi.Ruta',
            'ip' => 'logApi.IP',
            'estatus' => 'logApi.Estatus',
            'duracion_ms' => 'logApi.DuracionMs',
        ];

        $sort = $sortMap[$validated['sort'] ?? ''] ?? 'logApi.id';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = $validated['per_page'] ?? 10;

        $hasPayloadColumn = Schema::hasColumn('logApi', 'Payload');
        $hasResponseColumn = Schema::hasColumn('logApi', 'Respuesta');

        $query = LogApi::query()
            ->select([
                'logApi.id',
                'logApi.Usuario',
                'logApi.Metodo',
                'logApi.Ruta',
                'logApi.IP',
                'logApi.Estatus',
                'logApi.DuracionMs',
                'logApi.created_at',
            ]);

        if ($hasPayloadColumn) {
            $query->selectRaw("CASE WHEN logApi.Payload IS NOT NULL AND logApi.Payload != '' THEN 1 ELSE 0 END as tiene_body");
        } else {
            $query->selectRaw('0 as tiene_body');
        }

        if ($hasResponseColumn) {
            $query->selectRaw("CASE WHEN logApi.Respuesta IS NOT NULL AND logApi.Respuesta != '' THEN 1 ELSE 0 END as tiene_response");
        } else {
            $query->selectRaw('0 as tiene_response');
        }

        if (! empty($validated['metodo'])) {
            $query->where('logApi.Metodo', $validated['metodo']);
        }

        if (! empty($validated['ruta'])) {
            $rutaFiltro = $validated['ruta'];
            // Si es un patrón con placeholders ({id}/{uuid}), filtrar por REGEXP
            // anclado para no arrastrar otros endpoints con el mismo prefijo
            // (ej. api/clientes/{id} no debe incluir api/clientes/guardarCliente).
            if (str_contains($rutaFiltro, '{id}') || str_contains($rutaFiltro, '{uuid}')) {
                $query->whereRaw('logApi.Ruta REGEXP ?', [$this->rutaPatronARegexp($rutaFiltro)]);
            } else {
                $query->where('logApi.Ruta', $rutaFiltro);
            }
        }

        if (! empty($validated['estatus'])) {
            $query->where('logApi.Estatus', (int) $validated['estatus']);
        }

        if (! empty($validated['fecha_desde']) || ! empty($validated['fecha_hasta'])) {
            $desde = ! empty($validated['fecha_desde'])
                ? Carbon::parse($validated['fecha_desde'])->startOfDay()
                : Carbon::createFromTimestamp(0);
            $hasta = ! empty($validated['fecha_hasta'])
                ? Carbon::parse($validated['fecha_hasta'])->endOfDay()
                : Carbon::now();
            $query->whereBetween('logApi.created_at', [$desde, $hasta]);
        }

        if (! empty($validated['q_payload']) && $hasPayloadColumn) {
            $query->where('logApi.Payload', 'like', '%'.$validated['q_payload'].'%');
        }

        if (! empty($validated['q_response']) && $hasResponseColumn) {
            $query->where('logApi.Respuesta', 'like', '%'.$validated['q_response'].'%');
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('logApi.Usuario', 'like', "%{$search}%")
                    ->orWhere('logApi.Ruta', 'like', "%{$search}%")
                    ->orWhere('logApi.IP', 'like', "%{$search}%")
                    ->orWhere('logApi.URL', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sort, $direction);

        $logs = $query->paginate($perPage)->withQueryString();

        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'usuario' => $log->Usuario,
                'metodo' => $log->Metodo,
                'ruta' => $log->Ruta,
                'ip' => $log->IP,
                'estatus' => $log->Estatus,
                'duracion_ms' => $log->DuracionMs !== null ? (float) $log->DuracionMs : null,
                'fecha' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : null,
                'tiene_body' => (bool) ($log->tiene_body ?? false),
                'tiene_response' => (bool) ($log->tiene_response ?? false),
            ];
        });

        return Inertia::render('Logs/Index', [
            'logs' => $logs,
            'filters' => $request->only([
                'search', 'metodo', 'ruta', 'estatus',
                'fecha_desde', 'fecha_hasta',
                'q_payload', 'q_response',
                'sort', 'direction', 'per_page',
            ]),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $log = LogApi::select([
            'id', 'Usuario', 'Metodo', 'Ruta', 'URL', 'IP',
            'Estatus', 'DuracionMs', 'Payload', 'Respuesta', 'created_at',
        ])->find($id);

        if (! $log) {
            return response()->json(['success' => false, 'message' => 'Log no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'body' => $log->Payload,
            'response' => $log->Respuesta,
            'meta' => [
                'id' => $log->id,
                'usuario' => $log->Usuario,
                'metodo' => $log->Metodo,
                'ruta' => $log->Ruta,
                'url' => $log->URL,
                'ip' => $log->IP,
                'estatus' => $log->Estatus,
                'duracion_ms' => $log->DuracionMs !== null ? (float) $log->DuracionMs : null,
                'fecha' => $log->created_at ? $log->created_at->format('Y-m-d H:i:s') : null,
                'truncado_body' => is_string($log->Payload) && str_contains($log->Payload, '[TRUNCADO]'),
                'truncado_response' => is_string($log->Respuesta) && str_contains($log->Respuesta, '[TRUNCADO]'),
            ],
        ]);
    }

    private function filterOptions(): array
    {
        // Clave versionada: el formato de `rutas` cambió a [{patron, total}].
        return Cache::remember('logs_filter_options_v2', 300, function () {
            $metodos = LogApi::query()
                ->select('Metodo')
                ->whereNotNull('Metodo')
                ->distinct()
                ->orderBy('Metodo')
                ->pluck('Metodo')
                ->filter()
                ->values()
                ->all();

            $estatuses = LogApi::query()
                ->select('Estatus')
                ->whereNotNull('Estatus')
                ->distinct()
                ->orderBy('Estatus')
                ->pluck('Estatus')
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => (int) $v)
                ->values()
                ->all();

            // Ruta tiene alta cardinalidad por IDs en el path (api/clientes/6633,
            // api/clientes/5481...): agrupar por patrón normalizado con conteo.
            $conteos = LogApi::query()
                ->select('Ruta')
                ->selectRaw('COUNT(*) as total')
                ->whereNotNull('Ruta')
                ->where('Ruta', '!=', '')
                ->groupBy('Ruta')
                ->pluck('total', 'Ruta');

            $agrupadas = [];
            foreach ($conteos as $ruta => $total) {
                $patron = $this->normalizarRuta((string) $ruta);
                $agrupadas[$patron] = ($agrupadas[$patron] ?? 0) + (int) $total;
            }
            arsort($agrupadas);

            $rutas = [];
            foreach (array_slice($agrupadas, 0, 150, true) as $patron => $total) {
                $rutas[] = ['patron' => $patron, 'total' => $total];
            }

            return [
                'metodos' => $metodos,
                'estatuses' => $estatuses,
                'rutas' => $rutas,
            ];
        });
    }

    /**
     * Normaliza una ruta concreta a su patrón agrupable:
     * segmentos solo-dígitos -> {id}, UUIDs -> {uuid}.
     * Ej: api/clientes/6633 -> api/clientes/{id}
     */
    private function normalizarRuta(string $ruta): string
    {
        $segmentos = explode('/', $ruta);
        foreach ($segmentos as &$seg) {
            if ($seg !== '' && ctype_digit($seg)) {
                $seg = '{id}';
            } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $seg)) {
                $seg = '{uuid}';
            }
        }
        unset($seg);

        return implode('/', $segmentos);
    }

    /**
     * Convierte un patrón (api/clientes/{id}) a REGEXP MySQL anclado.
     */
    private function rutaPatronARegexp(string $patron): string
    {
        $partes = preg_split('/(\{id\}|\{uuid\})/', $patron, -1, PREG_SPLIT_DELIM_CAPTURE);
        $rx = '';
        foreach ($partes as $p) {
            if ($p === '{id}') {
                $rx .= '[0-9]+';
            } elseif ($p === '{uuid}') {
                $rx .= '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
            } else {
                $rx .= preg_quote($p, '/');
            }
        }

        return '^'.$rx.'$';
    }
}
