<?php

namespace App\Http\Controllers;

use App\Models\LogCorreo;
use App\Services\NotificacionCumplimientoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CorreosController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:50',
            'estado' => 'nullable|string|max:30',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date|after_or_equal:fecha_desde',
            'q_cuerpo' => 'nullable|string|max:255',
            'sort' => 'nullable|string|in:id,fecha,destinatario,asunto,tipo,estado',
            'direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $sortMap = [
            'id' => 'logCorreos.id',
            'fecha' => 'logCorreos.created_at',
            'destinatario' => 'logCorreos.Destinatario',
            'asunto' => 'logCorreos.Asunto',
            'tipo' => 'logCorreos.Tipo',
            'estado' => 'logCorreos.Estado',
        ];

        $sort = $sortMap[$validated['sort'] ?? ''] ?? 'logCorreos.id';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = $validated['per_page'] ?? 10;

        $hasCuerpo = Schema::hasColumn('logCorreos', 'CuerpoHtml');

        $query = LogCorreo::query()->select([
            'logCorreos.id',
            'logCorreos.Destinatario',
            'logCorreos.NombreDestinatario',
            'logCorreos.Asunto',
            'logCorreos.Tipo',
            'logCorreos.Estado',
            'logCorreos.IDCliente',
            'logCorreos.IDOperacion',
            'logCorreos.AlertasCount',
            'logCorreos.Truncado',
            'logCorreos.created_at',
        ]);

        if ($hasCuerpo) {
            $query->selectRaw("CASE WHEN logCorreos.CuerpoHtml IS NOT NULL AND logCorreos.CuerpoHtml != '' THEN 1 ELSE 0 END as tiene_cuerpo");
        } else {
            $query->selectRaw('0 as tiene_cuerpo');
        }

        if (! empty($validated['tipo'])) {
            $query->where('logCorreos.Tipo', $validated['tipo']);
        }

        if (! empty($validated['estado'])) {
            $query->where('logCorreos.Estado', $validated['estado']);
        }

        if (! empty($validated['fecha_desde']) || ! empty($validated['fecha_hasta'])) {
            $desde = ! empty($validated['fecha_desde'])
                ? Carbon::parse($validated['fecha_desde'])->startOfDay()
                : Carbon::createFromTimestamp(0);
            $hasta = ! empty($validated['fecha_hasta'])
                ? Carbon::parse($validated['fecha_hasta'])->endOfDay()
                : Carbon::now();
            $query->whereBetween('logCorreos.created_at', [$desde, $hasta]);
        }

        if (! empty($validated['q_cuerpo']) && $hasCuerpo) {
            $query->where('logCorreos.CuerpoHtml', 'like', '%'.$validated['q_cuerpo'].'%');
        }

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('logCorreos.Destinatario', 'like', "%{$search}%")
                    ->orWhere('logCorreos.Asunto', 'like', "%{$search}%")
                    ->orWhere('logCorreos.NombreDestinatario', 'like', "%{$search}%");
            });
        }

        $query->orderBy($sort, $direction);

        $correos = $query->paginate($perPage)->withQueryString();

        $correos->getCollection()->transform(function ($c) {
            return [
                'id' => $c->id,
                'destinatario' => $c->Destinatario,
                'nombre' => $c->NombreDestinatario,
                'asunto' => $c->Asunto,
                'tipo' => $c->Tipo,
                'estado' => $c->Estado,
                'id_cliente' => $c->IDCliente,
                'id_operacion' => $c->IDOperacion,
                'alertas_count' => (int) $c->AlertasCount,
                'fecha' => $c->created_at ? $c->created_at->format('Y-m-d H:i:s') : null,
                'tiene_cuerpo' => (bool) ($c->tiene_cuerpo ?? false),
                'truncado' => (bool) $c->Truncado,
            ];
        });

        return Inertia::render('Correos/Index', [
            'correos' => $correos,
            'filters' => $request->only(['search', 'tipo', 'estado', 'fecha_desde', 'fecha_hasta', 'q_cuerpo', 'sort', 'direction', 'per_page']),
            'filterOptions' => $this->filterOptions(),
            'toast' => session('toast'),
            'oficialCorreo' => NotificacionCumplimientoService::getConfiguracionOficial()?->Correo,
        ]);
    }

    public function reenviar($id)
    {
        $correo = LogCorreo::find($id);

        if (! $correo) {
            return redirect()->back()->with('toast', [
                'type' => 'error',
                'message' => 'Correo no encontrado.',
            ]);
        }

        $resultado = NotificacionCumplimientoService::reenviarCorreoOficial($correo);

        $message = $resultado['message'];
        if ($resultado['ok'] && $correo->Truncado) {
            $message .= ' El contenido original está truncado; se envió el HTML almacenado.';
        }

        return redirect()->back()->with('toast', [
            'type' => $resultado['ok'] ? 'success' : 'error',
            'message' => $message,
        ]);
    }

    public function show($id)
    {
        $c = LogCorreo::select(['id', 'Destinatario', 'NombreDestinatario', 'Asunto', 'Tipo', 'Estado', 'IDCliente', 'IDOperacion', 'Patrones', 'AlertasCount', 'Truncado', 'Error', 'CuerpoHtml', 'created_at'])->find($id);

        if (! $c) {
            return response()->json(['success' => false, 'message' => 'Correo no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'cuerpo' => $c->CuerpoHtml,
            'meta' => [
                'id' => $c->id,
                'destinatario' => $c->Destinatario,
                'nombre' => $c->NombreDestinatario,
                'asunto' => $c->Asunto,
                'tipo' => $c->Tipo,
                'estado' => $c->Estado,
                'id_cliente' => $c->IDCliente,
                'id_operacion' => $c->IDOperacion,
                'patrones' => $c->Patrones,
                'alertas_count' => (int) $c->AlertasCount,
                'error' => $c->Error,
                'fecha' => $c->created_at ? $c->created_at->format('Y-m-d H:i:s') : null,
                'truncado' => (bool) $c->Truncado,
            ],
        ]);
    }

    private function filterOptions(): array
    {
        return Cache::remember('correos_filter_options_v2', 300, function () {
            $destinatarios = LogCorreo::query()
                ->select('Destinatario')
                ->selectRaw('COUNT(*) as total')
                ->whereNotNull('Destinatario')
                ->where('Destinatario', '!=', '')
                ->groupBy('Destinatario')
                ->orderByDesc('total')
                ->limit(50)
                ->pluck('total', 'Destinatario')
                ->map(fn ($total, $correo) => ['correo' => $correo, 'total' => (int) $total])
                ->values()
                ->all();

            return [
                'tipos' => LogCorreo::TIPOS,
                'estados' => LogCorreo::ESTADOS,
                'destinatarios' => $destinatarios,
            ];
        });
    }
}
