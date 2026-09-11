<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatTipoPersona;
use App\Models\TbAlertas;
use App\Models\TbReporteRegulatorioPLD;
use App\Services\PLD\ReporteRegulatorioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteOperacionesController extends Controller
{
    private const PATRONES = [
        'Relevante',
        'Inusual',
        'Preocupante',
        'Cancelacion',
        'Fraccionado',
        'Acumulado',
        'Nuevo',
    ];

    private const ESTATUS = ['Enviado', 'Por reportar'];

    private const TIPOS_REPORTE = [
        'Relevante'   => ['Relevante'],
        'Inusual'     => ['Cancelacion', 'Fraccionado', 'Acumulado', 'Inusual', 'Nuevo'],
        'Preocupante' => ['Preocupante'],
    ];

    private const CSV_HEADERS = [
        'IDRRPLD',
        'aMaterno',
        'aMaternoAgente',
        'aMaternoTitular',
        'aPaterno',
        'aPaternoAgente',
        'aPaternoTitular',
        'ciudad',
        'colonia',
        'cuenta',
        'curp',
        'curpAgente',
        'cveSujetoObl',
        'cveSujetoObligado',
        'descripcion',
        'domicilio',
        'estatus',
        'fechaDeteccion',
        'fechaNacimiento',
        'fechaOperacion',
        'folio',
        'instrumentoMonetario',
        'localidad',
        'moneda',
        'monto',
        'nacionalidad',
        'nombre',
        'nombreAgente',
        'nombreTitular',
        'ocupacion',
        'organoSupervisor',
        'periodoReporte',
        'poliza',
        'polizaCuenta',
        'razon',
        'razonSocial',
        'rfc',
        'rfcAgente',
        'sucursal',
        'telefono',
        'tipoOperacion',
        'tipoPersona',
        'tipoReporte',
    ];

    public function index()
    {
        $alertas = TbAlertas::whereIn('Patron', self::PATRONES)
            ->whereIn('Estatus', self::ESTATUS)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('ReporteOperaciones/Index', [
            'alertas' => $alertas,
        ]);
    }

    public function obtenerReporte(Request $request)
    {
        $filtros = $request->only(['tipo_reporte', 'estatus', 'fecha_ini', 'fecha_fin']);

        $alertas = $this->construirQuery($filtros)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['alertas' => $alertas]);
    }

    public function exportarCSV(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'nullable|array',
            'ids.*' => 'integer',
        ]);

        $filtros = $request->only(['tipo_reporte', 'estatus', 'fecha_ini', 'fecha_fin']);

        $query = $this->construirQuery($filtros);

        if (! empty($validated['ids'])) {
            $query->whereIn('IDRegistroAlerta', $validated['ids']);
        }

        $alertas = $query->orderBy('created_at', 'desc')->get();

        if ($alertas->isEmpty()) {
            return response()->json(['message' => 'No hay datos para exportar'], 404);
        }

        $ids = $alertas->pluck('IDRegistroAlerta')->all();

        DB::transaction(function () use ($alertas) {
            $servicio = new ReporteRegulatorioService;

            foreach ($alertas as $alerta) {
                $servicio->emitirDesdeAlerta($alerta);

                if ($alerta->Estatus !== 'Enviado') {
                    $alerta->Estatus = 'Enviado';
                    $alerta->save();
                }
            }
        });

        $reportes = TbReporteRegulatorioPLD::whereIn('IDRegistroAlerta', $ids)
            ->orderBy('IDReporte')
            ->get();

        $filas = $this->mapearFilas($reportes);

        $fileName = 'reporte_operaciones_'.date('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($filas) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, self::CSV_HEADERS);

            foreach ($filas as $fila) {
                fputcsv($file, $fila);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function construirQuery(array $filtros)
    {
        $query = TbAlertas::whereIn('Patron', self::PATRONES)
            ->whereIn('Estatus', self::ESTATUS);

        $tipo = $filtros['tipo_reporte'] ?? '';

        if (! empty($tipo) && $tipo !== 'Todos' && isset(self::TIPOS_REPORTE[$tipo])) {
            $query->whereIn('Patron', self::TIPOS_REPORTE[$tipo]);
        }

        if (! empty($filtros['estatus']) && $filtros['estatus'] !== 'Todos') {
            $query->where('Estatus', $filtros['estatus']);
        }

        if (! empty($filtros['fecha_ini']) && ! empty($filtros['fecha_fin'])) {
            $inicio = $filtros['fecha_ini'].' 00:00:00';
            $fin = $filtros['fecha_fin'].' 23:59:59';
            $query->whereBetween('created_at', [$inicio, $fin]);
        }

        return $query;
    }

    private function mapearFilas($reportes): array
    {
        $codigosMoneda = ['MXN' => '001', 'USD' => '002'];

        $tiposPersona = CatTipoPersona::pluck('IDTipoPersona', 'TipoPersona');
        $formasPago = CatFormaPagos::pluck('IDFormaPago', 'FormaPago');
        $nacionalidades = CatNacionalidad::pluck('IDNacionalidad', 'Nacionalidad');

        return $reportes->map(function ($r) use ($codigosMoneda, $tiposPersona, $formasPago, $nacionalidades) {
            $instrumento = $formasPago[$r->InstrumentoMonetario] ?? $r->InstrumentoMonetario;

            if (is_numeric($instrumento)) {
                $instrumento = str_pad((string) $instrumento, 2, '0', STR_PAD_LEFT);
            }

            return [
                $r->IDReporte,
                $r->AMaterno,
                $r->AMaternoAgente,
                $r->AMaternoTitular,
                $r->APaterno,
                $r->APaternoAgente,
                $r->APaternoTitular,
                $r->Ciudad,
                $r->Colonia,
                $r->Cuenta,
                $r->CURP,
                $r->CURPAgente,
                $r->CveSujetoObl,
                $r->CveSujetoObligado,
                $r->Descripcion,
                $r->Domicilio,
                $r->Estatus,
                $this->formatearFecha($r->FechaDeteccion),
                $this->formatearFecha($r->FechaNacimiento),
                $this->formatearFecha($r->FechaOperacion),
                $r->Folio,
                $instrumento,
                $r->Localidad,
                $codigosMoneda[$r->IDMoneda] ?? $r->IDMoneda,
                $r->Monto,
                $nacionalidades[$r->Nacionalidad] ?? $r->Nacionalidad,
                $r->Nombre,
                $r->NombreAgente,
                $r->NombreTitular,
                $r->Ocupacion,
                $r->OrganoSupervisor,
                $r->PeriodoReporte,
                $r->NoPoliza,
                $r->NoPolizaCuenta,
                $r->Razon,
                $r->RazonSocial,
                $r->RFC,
                $r->RFCAgente,
                $r->Sucursal,
                $r->Telefono,
                $r->IDTipoOperacion,
                $tiposPersona[$r->TipoPersona] ?? $r->TipoPersona,
                $r->IDTipoReporte,
            ];
        })->all();
    }

    private function formatearFecha($fecha): ?string
    {
        if (empty($fecha)) {
            return null;
        }

        return date('Ymd', strtotime((string) $fecha));
    }
}
