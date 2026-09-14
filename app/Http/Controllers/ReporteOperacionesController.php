<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatOcupacionesGiros;
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

    private const CLAVE_SUJETO_OBLIGADO = '022123';

    private const ORGANO_SUPERVISOR = '003';

    /**
     * Encabezados del layout de operaciones relevantes, inusuales y preocupantes.
     */
    private const LAYOUT_HEADERS = [
        'Tipo Reporte',
        'Periodo Reporte',
        'Folio',
        'Organo Supervisor',
        'Clave Sujeto Obligado',
        'Localidad',
        'Sucursal',
        'Tipo Operacion',
        'Instrumento Monetario',
        'Poliza',
        'Monto',
        'Moneda',
        'Fecha Operacion',
        'Fecha Deteccion',
        'Nacionalidad',
        'Tipo Persona',
        'Razon Social',
        'Nombre',
        'Apellido Paterno',
        'Apellido Materno',
        'RFC',
        'CURP',
        'Fecha Nacimiento',
        'Domicilio',
        'Colonia',
        'Ciudad',
        'Telefono',
        'Ocupacion',
        'Nombre Agente',
        'Apellido Paterno Agente',
        'Apellido Materno Agente',
        'RFC Agente',
        'CURP Agente',
        'Cuenta',
        'Poliza Cuenta',
        'Clave Sujeto Obligado',
        'Nombre Titular',
        'Apellido Paterno Titular',
        'Apellido Materno Titular',
        'Descripcion',
        'Razon',
        'Estatus',
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
            'ids'          => 'nullable|array',
            'ids.*'        => 'integer',
            'tipo_reporte' => 'required|string|in:Relevante,Inusual,Preocupante,Todos,Todas',
        ]);

        $tipo = $validated['tipo_reporte'] === 'Todas' ? 'Todos' : $validated['tipo_reporte'];

        $filtros = $request->only(['tipo_reporte', 'estatus', 'fecha_ini', 'fecha_fin']);
        $filtros['tipo_reporte'] = $tipo;

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

        $fileName = $this->nombreArchivo($tipo);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $callback = function () use ($filas) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fwrite($file, implode(';', self::LAYOUT_HEADERS)."\r\n");

            foreach ($filas as $fila) {
                fwrite($file, implode(';', $fila)."\r\n");
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

    /**
     * Nombre del archivo conforme al Anexo 2:
     * TIPO(1) + CLAVE_SUJETO(6) + PERIODO(4/6) + "." + ORGANO_SUPERVISOR(3).
     */
    private function nombreArchivo(string $tipo): string
    {
        $prefijo = match ($tipo) {
            'Relevante'   => '1',
            'Inusual'     => '2',
            'Preocupante' => '3',
            default       => '0',
        };

        $periodo = $tipo === 'Relevante'
            ? now()->format('ym')
            : now()->format('ymd');

        return $prefijo.self::CLAVE_SUJETO_OBLIGADO.$periodo.'.'.self::ORGANO_SUPERVISOR.'.csv';
    }

    private function mapearFilas($reportes): array
    {
        $codigosMoneda = ['MXN' => '001', 'USD' => '002'];

        $tiposPersona   = CatTipoPersona::pluck('IDTipoPersona', 'TipoPersona');
        $formasPago     = CatFormaPagos::pluck('IDFormaPago', 'FormaPago');
        $nacionalidades = CatNacionalidad::pluck('IDNacionalidad', 'Nacionalidad');
        $ocupaciones    = CatOcupacionesGiros::pluck('CVE_GIRO', 'OcupacionGiro');

        $folio = 0;

        return $reportes->map(function ($r) use ($codigosMoneda, $tiposPersona, $formasPago, $nacionalidades, $ocupaciones, &$folio) {
            $folio++;

            $instrumento = $formasPago[$r->InstrumentoMonetario] ?? $r->InstrumentoMonetario;

            if (is_numeric($instrumento)) {
                $instrumento = str_pad((string) $instrumento, 2, '0', STR_PAD_LEFT);
            }

            $tipoOperacion = $r->IDTipoOperacion
                ? str_pad((string) $r->IDTipoOperacion, 2, '0', STR_PAD_LEFT)
                : '';

            return [
                $this->limpiar($r->IDTipoReporte),
                $this->limpiar($this->periodoReporte($r)),
                $this->limpiar(str_pad((string) $folio, 6, '0', STR_PAD_LEFT)),
                $this->limpiar($r->OrganoSupervisor),
                $this->limpiar($r->CveSujetoObligado),
                $this->limpiar($r->Localidad),
                $this->limpiar($r->Sucursal),
                $this->limpiar($tipoOperacion),
                $this->limpiar($instrumento),
                $this->limpiar($r->NoPoliza),
                $this->limpiar(number_format((float) ($r->Monto ?? 0), 2, '.', '')),
                $this->limpiar($codigosMoneda[$r->IDMoneda] ?? $r->IDMoneda),
                $this->limpiar($this->formatearFecha($r->FechaOperacion)),
                $this->limpiar($this->formatearFecha($r->FechaDeteccion)),
                $this->limpiar($nacionalidades[$r->Nacionalidad] ?? $r->Nacionalidad),
                $this->limpiar($tiposPersona[$r->TipoPersona] ?? $r->TipoPersona),
                $this->limpiar($r->RazonSocial),
                $this->limpiar($r->Nombre),
                $this->limpiar($r->APaterno),
                $this->limpiar($r->AMaterno),
                $this->limpiar($r->RFC),
                $this->limpiar($r->CURP),
                $this->limpiar($this->formatearFecha($r->FechaNacimiento)),
                $this->limpiar($r->Domicilio),
                $this->limpiar($r->Colonia),
                $this->limpiar($r->Ciudad),
                $this->limpiar($r->Telefono),
                $this->limpiar($ocupaciones[$r->Ocupacion] ?? $r->Ocupacion),
                $this->limpiar($r->NombreAgente),
                $this->limpiar($r->APaternoAgente),
                $this->limpiar($r->AMaternoAgente),
                $this->limpiar($r->RFCAgente),
                $this->limpiar($r->CURPAgente),
                $this->limpiar($r->Cuenta),
                $this->limpiar($r->NoPolizaCuenta),
                $this->limpiar($r->CveSujetoObl),
                $this->limpiar($r->NombreTitular),
                $this->limpiar($r->APaternoTitular),
                $this->limpiar($r->AMaternoTitular),
                $this->limpiar($r->Descripcion),
                $this->limpiar($r->Razon),
                $this->limpiar($r->Estatus),
            ];
        })->all();
    }

    private function periodoReporte($r): string
    {
        $tipo    = (int) ($r->IDTipoReporte ?? 1);
        $periodo = (string) ($r->PeriodoReporte ?? '');

        if ($tipo === 1) {
            if (strlen($periodo) >= 6) {
                return substr($periodo, 0, 6);
            }

            return $r->FechaDeteccion ? date('Ym', strtotime((string) $r->FechaDeteccion)) : '';
        }

        if (strlen($periodo) >= 8) {
            return substr($periodo, 0, 8);
        }

        return $r->FechaDeteccion ? date('Ymd', strtotime((string) $r->FechaDeteccion)) : '';
    }

    private function formatearFecha($fecha): string
    {
        if (empty($fecha)) {
            return '';
        }

        $timestamp = strtotime((string) $fecha);

        return $timestamp ? date('Ymd', $timestamp) : '';
    }

    private function limpiar($value): string
    {
        $value = (string) ($value ?? '');
        $value = str_replace(';', ' ', $value);
        $value = preg_replace('/[\r\n]+/', ' ', $value);

        return mb_strtoupper(trim($value));
    }
}
