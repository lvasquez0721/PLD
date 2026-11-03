<?php

namespace App\Http\Controllers\Ebr;

use App\Http\Controllers\Controller;
use App\Models\Ebr\EbrEjercicio;
use App\Services\Ebr\CalculadoraEbr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EbrController extends Controller
{
    public function index(Request $request)
    {
        $ejercicios = EbrEjercicio::query()
            ->withCount(['polizas', 'siniestros', 'alertas', 'importaciones'])
            ->orderByDesc('anio')
            ->get()
            ->map(fn (EbrEjercicio $e) => [
                'id' => $e->id,
                'anio' => $e->anio,
                'nombre' => $e->nombre,
                'fecha_inicio' => $e->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $e->fecha_fin?->format('Y-m-d'),
                'estado' => $e->estado,
                'totales' => $e->totales,
                'polizas_count' => $e->polizas()->where('excluida', false)->distinct()->count('no_poliza'),
                'operaciones_count' => $e->polizas()->where('excluida', false)->count(),
                'siniestros_count' => $e->siniestros_count,
                'alertas_count' => $e->alertas_count,
                'importaciones_count' => $e->importaciones_count,
                'calculado_at' => $e->calculado_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Ebr/Index', [
            'ejercicios' => $ejercicios,
            'puedeEditar' => $this->puedeEditar(),
            'anioSugerido' => max((int) date('Y'), (int) (EbrEjercicio::max('anio') ?? date('Y'))),
            'toast' => session('toast'),
        ]);
    }

    public function store(Request $request)
    {
        $this->autorizarEdicion();

        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100', 'unique:ebr_ejercicios,anio'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $inicio = Carbon::parse($data['fecha_inicio'])->startOfDay();
        $fin = Carbon::parse($data['fecha_fin'])->startOfDay();

        if ($inicio->year !== (int) $data['anio'] || $fin->year !== (int) $data['anio']) {
            return back()->withErrors(['anio' => 'Las fechas del periodo deben corresponder al año del ejercicio.'])->withInput();
        }

        if (! $inicio->isSameDay(Carbon::create((int) $data['anio'], 1, 1)->startOfDay())
            || ! $fin->isSameDay(Carbon::create((int) $data['anio'], 12, 31)->startOfDay())) {
            return back()->withErrors([
                'fecha_inicio' => 'El periodo del ejercicio debe cubrir doce meses, del 1 de enero al 31 de diciembre.',
            ])->withInput();
        }

        $mitigantes = $this->mitigantesFijos();

        $ejercicio = EbrEjercicio::create([
            'anio' => $data['anio'],
            'nombre' => "EBR {$data['anio']}",
            'fecha_inicio' => $data['fecha_inicio'],
            'fecha_fin' => $data['fecha_fin'],
            'estado' => 'borrador',
            'version_metodologia' => config('ebr.version_metodologia'),
            'mitigantes' => $mitigantes,
            'observaciones' => $data['observaciones'] ?? null,
            'importado_por' => $request->user()?->id,
        ]);

        return redirect()->route('ebr.show', $ejercicio)->with('toast', [
            'type' => 'success',
            'message' => "Ejercicio EBR {$ejercicio->anio} creado. Descarga las plantillas e importa los insumos.",
        ]);
    }

    public function show(EbrEjercicio $ejercicio)
    {
        $ejercicio->loadCount(['polizas', 'siniestros', 'alertas'])
            ->load(['importadoPor:id,nombre', 'calculadoPor:id,nombre']);
        $polizasDistintas = $ejercicio->polizas()->where('excluida', false)->distinct()->count('no_poliza');

        $config = config('ebr');
        $metodologia = [
            'version' => $config['version_metodologia'],
            'escala' => $config['escala'],
            'institucion' => $config['institucion'],
            'productos' => $config['productos'],
            'clientes' => $config['clientes'],
            'geografia' => $config['geografia'],
            'canales' => $config['canales'],
            'transacciones' => $config['transacciones'],
        ];

        $importaciones = $ejercicio->importaciones()->latest()->limit(12)->get()->map(fn ($i) => [
            'id' => $i->id,
            'tipo' => $i->tipo,
            'nombre_archivo' => $i->nombre_archivo,
            'estado' => $i->estado,
            'total_filas' => $i->total_filas,
            'filas_validas' => $i->filas_validas,
            'filas_error' => $i->filas_error,
            'resumen' => $i->resumen,
            'created_at' => $i->created_at?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Ebr/Show', [
            'ejercicio' => [
                'id' => $ejercicio->id,
                'anio' => $ejercicio->anio,
                'nombre' => $ejercicio->nombre,
                'fecha_inicio' => $ejercicio->fecha_inicio?->format('Y-m-d'),
                'fecha_fin' => $ejercicio->fecha_fin?->format('Y-m-d'),
                'estado' => $ejercicio->estado,
                'version_metodologia' => $ejercicio->version_metodologia,
                'mitigantes' => $ejercicio->mitigantes ?? config('ebr.mitigantes'),
                'resultados' => $ejercicio->resultados,
                'totales' => $ejercicio->totales,
                'comparativo' => $ejercicio->comparativo,
                'advertencias' => $ejercicio->advertencias ?? [],
                'observaciones' => $ejercicio->observaciones,
                'created_at' => $ejercicio->created_at?->format('Y-m-d H:i'),
                'calculado_at' => $ejercicio->calculado_at?->format('Y-m-d H:i'),
                'cerrado_at' => $ejercicio->cerrado_at?->format('Y-m-d H:i'),
                'importado_por_nombre' => $ejercicio->importadoPor?->nombre,
                'calculado_por_nombre' => $ejercicio->calculadoPor?->nombre,
                'polizas_count' => $polizasDistintas,
                'operaciones_count' => $ejercicio->polizas()->where('excluida', false)->count(),
                'siniestros_count' => $ejercicio->siniestros_count,
                'alertas_count' => $ejercicio->alertas_count,
            ],
            'metodologia' => $metodologia,
            'importaciones' => $importaciones,
            'plantillas' => [
                'clientes' => config('ebr.csv.clientes'),
                'siniestros' => config('ebr.csv.siniestros'),
                'alertas' => config('ebr.csv.alertas'),
            ],
            'mitigantesDefault' => config('ebr.mitigantes'),
            'nivelesEfectividad' => config('ebr.niveles_efectividad'),
            'puedeEditar' => $this->puedeEditar(),
            'importacionReciente' => session('importacion_reciente'),
            'erroresImportacion' => session('errores_importacion', []),
            'toast' => session('toast'),
        ]);
    }

    public function actualizarObservaciones(Request $request, EbrEjercicio $ejercicio)
    {
        $this->autorizarEdicion();
        if ($ejercicio->estado === 'cerrado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'El ejercicio está cerrado. Reábrelo para modificar observaciones.']);
        }

        $data = $request->validate([
            'observaciones' => ['nullable', 'string', 'max:5000'],
        ]);

        $ejercicio->update(['observaciones' => $data['observaciones'] ?? null]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Observaciones actualizadas.']);
    }

    public function actualizarMitigantes(Request $request, EbrEjercicio $ejercicio)
    {
        $this->autorizarEdicion();
        if ($ejercicio->estado === 'cerrado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'El ejercicio está cerrado. Reábrelo para modificar mitigantes.']);
        }

        $catalogo = collect(config('ebr.mitigantes'))->keyBy('id');

        $data = $request->validate([
            'mitigantes' => ['required', 'array', 'size:'.$catalogo->count()],
            'mitigantes.*.id' => ['required', 'integer'],
            'mitigantes.*.scope' => ['nullable', 'string'],
            'mitigantes.*.max' => ['nullable', 'numeric'],
            'mitigantes.*.nombre' => ['nullable', 'string'],
            'mitigantes.*.nivel' => ['required', 'in:Informal,Estandarizado,Monitoreado,Optimizado'],
        ]);

        $recibidos = collect($data['mitigantes'])->map(function (array $m) {
            $m['id'] = (int) $m['id'];

            return $m;
        });

        $idsRecibidos = $recibidos->pluck('id');

        if ($idsRecibidos->duplicates()->isNotEmpty()) {
            return back()->withErrors(['mitigantes' => 'No se permiten mitigantes duplicados.'])->withInput();
        }

        if ($idsRecibidos->diff($catalogo->keys())->isNotEmpty()
            || $catalogo->keys()->diff($idsRecibidos)->isNotEmpty()) {
            return back()->withErrors(['mitigantes' => 'La lista de mitigantes no corresponde al catálogo de la metodología.'])->withInput();
        }

        foreach ($recibidos as $recibido) {
            $base = $catalogo->get($recibido['id']);

            if (isset($recibido['scope']) && $recibido['scope'] !== $base['scope']) {
                return back()->withErrors(['mitigantes' => 'El alcance de los mitigantes está definido por la metodología y no puede modificarse.'])->withInput();
            }

            if (isset($recibido['nombre']) && $recibido['nombre'] !== $base['nombre']) {
                return back()->withErrors(['mitigantes' => 'El nombre de los mitigantes está definido por la metodología y no puede modificarse.'])->withInput();
            }

            if (isset($recibido['max']) && abs((float) $recibido['max'] - (float) $base['max']) > 0.000001) {
                return back()->withErrors(['mitigantes' => 'El porcentaje máximo de los mitigantes está definido por la metodología y no puede modificarse.'])->withInput();
            }

            if ($recibido['nivel'] !== $base['nivel']) {
                return back()->withErrors(['mitigantes' => 'El nivel de efectividad de los mitigantes está fijo en la metodología 2025.'])->withInput();
            }
        }

        $ejercicio->update(['mitigantes' => $this->mitigantesFijos()]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Los mitigantes de la metodología 2025 se mantienen fijos (20%, 10% y 35%).']);
    }

    public function calcular(EbrEjercicio $ejercicio, CalculadoraEbr $calculadora)
    {
        $this->autorizarEdicion();

        if ($ejercicio->estado === 'cerrado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'El ejercicio está cerrado. Reábrelo para recalcular.']);
        }

        $clientesImportados = $ejercicio->importaciones()
            ->where('tipo', 'clientes')
            ->where('estado', 'importado')
            ->exists();

        if (! $clientesImportados) {
            return back()->with('toast', [
                'type' => 'error',
                'message' => 'Importa y confirma la base de clientes/pólizas antes de calcular el EBR.',
            ]);
        }

        if ($ejercicio->polizas()->where('excluida', false)->count() === 0) {
            return back()->with('toast', ['type' => 'error', 'message' => 'No hay pólizas con FechaEmision dentro del periodo del ejercicio.']);
        }

        $anterior = EbrEjercicio::query()
            ->where('anio', '<', $ejercicio->anio)
            ->whereIn('estado', ['calculado', 'cerrado'])
            ->orderByDesc('anio')
            ->first();

        $resultado = $calculadora->calcular($ejercicio, $anterior ? [
            'anio' => $anterior->anio,
            'resultados' => $anterior->resultados,
        ] : null);

        DB::transaction(function () use ($ejercicio, $resultado) {
            $ejercicio->update([
                'resultados' => $resultado['resultados'],
                'totales' => $resultado['totales'],
                'comparativo' => $resultado['resultados']['comparativo'] ?? null,
                'advertencias' => $resultado['advertencias'],
                'estado' => 'calculado',
                'calculado_por' => auth()->id(),
                'calculado_at' => now(),
            ]);
        });

        return redirect()->route('ebr.show', $ejercicio)->with('toast', [
            'type' => 'success',
            'message' => "EBR {$ejercicio->anio} calculado. Riesgo residual final: ".number_format($resultado['totales']['residual_final'], 2).' ('.$resultado['totales']['nivel'].').',
        ]);
    }

    public function cerrar(EbrEjercicio $ejercicio)
    {
        $this->autorizarEdicion();

        if ($ejercicio->estado !== 'calculado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'Solo se puede cerrar un ejercicio calculado.']);
        }

        $ejercicio->update(['estado' => 'cerrado', 'cerrado_at' => now()]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Ejercicio EBR cerrado.']);
    }

    public function reabrir(EbrEjercicio $ejercicio)
    {
        $this->autorizarEdicion();

        if ($ejercicio->estado !== 'cerrado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'Solo se puede reabrir un ejercicio cerrado.']);
        }

        $ejercicio->update(['estado' => 'calculado', 'cerrado_at' => null]);

        return back()->with('toast', ['type' => 'warning', 'message' => 'Ejercicio reabierto en estado calculado. Puedes corregir insumos o mitigantes y recalcular.']);
    }

    public function destroy(EbrEjercicio $ejercicio)
    {
        $this->autorizarEdicion();

        if ($ejercicio->estado === 'cerrado') {
            return back()->with('toast', ['type' => 'error', 'message' => 'No se puede eliminar un ejercicio cerrado.']);
        }

        $ejercicio->delete();

        return redirect()->route('ebr.index')->with('toast', ['type' => 'success', 'message' => 'Ejercicio EBR eliminado.']);
    }

    private function mitigantesFijos(): array
    {
        $niveles = config('ebr.niveles_efectividad');

        return collect(config('ebr.mitigantes'))->map(function ($mitigante) use ($niveles) {
            $efectividad = (float) ($niveles[$mitigante['nivel']] ?? 1);

            return [
                'id' => (int) $mitigante['id'],
                'nombre' => $mitigante['nombre'],
                'scope' => $mitigante['scope'],
                'max' => (float) $mitigante['max'],
                'nivel' => $mitigante['nivel'],
                'efectividad' => $efectividad,
                'efecto' => round((float) $mitigante['max'] * $efectividad, 6),
            ];
        })->values()->all();
    }

    private function puedeEditar(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['Administrador', 'Oficial de cumplimiento']);
    }

    private function autorizarEdicion(): void
    {
        abort_unless($this->puedeEditar(), 403, 'No tienes permiso para modificar el EBR.');
    }
}
