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
        // Al cargar no se muestra ningún registro hasta que el usuario filtre y presione Buscar
        return Inertia::render('ReporteOperaciones/Index', [
            'alertas' => [],
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
            'con_headers'  => 'nullable|boolean',
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

        // Solo descarga: ya no marca como Enviado ni emite reporte regulatorio.
        // La emisión/cambio a Enviado se hace exclusivamente vía reportar().
        $reportes = TbReporteRegulatorioPLD::whereIn('IDRegistroAlerta', $ids)
            ->orderBy('IDReporte')
            ->get();

        // Si aún no hay reporte regulatorio generado para las alertas filtradas,
        // generar filas directamente desde las alertas sin emitir (solo exportación)
        // para no cambiar estatus. Si existen reportes, usarlos.
        if ($reportes->isEmpty()) {
            // Fallback: generar CSV directamente desde alertas sin crear registro regulatorio
            // Para mantener compatibilidad, si no hay reporte previo simplemente exportamos las alertas
            // mapeando desde alertas como fuente alternativa
            $filas = $this->mapearFilasDesdeAlertas($alertas);
            // Usar fecha de alertas para nombre archivo
            $fileName = $this->nombreArchivoDesdeAlertas($tipo, $alertas);
        } else {
            $filas = $this->mapearFilas($reportes);
            $fileName = $this->nombreArchivo($tipo, $reportes);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        $conHeaders = $request->boolean('con_headers', false);

        $callback = function () use ($filas, $conHeaders) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($conHeaders) {
                fwrite($file, implode(';', self::LAYOUT_HEADERS)."\r\n");
            }

            foreach ($filas as $fila) {
                fwrite($file, implode(';', $fila)."\r\n");
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function reportar(Request $request)
    {
        $validated = $request->validate([
            'ids'          => 'required|array|min:1',
            'ids.*'        => 'integer',
            'tipo_reporte' => 'nullable|string|in:Relevante,Inusual,Preocupante,Todos,Todas',
        ]);

        $tipo = $validated['tipo_reporte'] ?? 'Todos';
        if ($tipo === 'Todas') {
            $tipo = 'Todos';
        }

        $filtros = $request->only(['tipo_reporte', 'estatus', 'fecha_ini', 'fecha_fin']);
        $filtros['tipo_reporte'] = $tipo;

        $query = $this->construirQuery($filtros);
        $query->whereIn('IDRegistroAlerta', $validated['ids']);
        // Solo se pueden reportar los que están Por reportar
        $query->where('Estatus', 'Por reportar');

        $alertas = $query->get();

        if ($alertas->isEmpty()) {
            return response()->json(['message' => 'No hay registros por reportar con los criterios seleccionados. Verifique que los registros estén en estatus "Por reportar".'], 422);
        }

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

        return response()->json([
            'message' => 'Se reportaron '.count($alertas).' registro(s) correctamente.',
            'count'   => count($alertas),
        ]);
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
     * TIPO(1) + CLAVE_SUJETO(6) + PERIODO(YYMM 4 / YYMMDD 6) + "." + ORGANO_SUPERVISOR(3).
     * Ej. 2022123260731.003 => 2 022123 260731 .003
     * Periodo tomado de FechaDeteccion del primer reporte del lote, fallback now().
     */
    private function nombreArchivo(string $tipo, $reportes = null): string
    {
        $prefijo = match ($tipo) {
            'Relevante'   => '1',
            'Inusual'     => '2',
            'Preocupante' => '3',
            default       => '0',
        };

        $fechaRef = null;
        if ($reportes && $reportes->isNotEmpty()) {
            $first = $reportes->first();
            $fechaRef = $first->FechaDeteccion ?? $first->FechaOperacion ?? null;
            if (empty($fechaRef) && ! empty($first->PeriodoReporte)) {
                // Si no hay fecha pero hay periodo almacenado, usarlo directamente
                $raw = preg_replace('/\D/', '', (string) $first->PeriodoReporte);
                if ($tipo === 'Relevante' && strlen($raw) >= 4) {
                    $periodo = substr($raw, -4);
                    if (strlen($periodo) === 4) {
                        return $prefijo.self::CLAVE_SUJETO_OBLIGADO.$periodo.'.'.self::ORGANO_SUPERVISOR.'.csv';
                    }
                } elseif (strlen($raw) >= 6) {
                    $periodo = substr($raw, -6);
                    if (strlen($periodo) === 6) {
                        return $prefijo.self::CLAVE_SUJETO_OBLIGADO.$periodo.'.'.self::ORGANO_SUPERVISOR.'.csv';
                    }
                }
            }
        }

        if ($fechaRef) {
            $ts = strtotime((string) $fechaRef);
            $periodo = $tipo === 'Relevante'
                ? ($ts ? date('ym', $ts) : now()->format('ym'))
                : ($ts ? date('ymd', $ts) : now()->format('ymd'));
        } else {
            $periodo = $tipo === 'Relevante'
                ? now()->format('ym')
                : now()->format('ymd');
        }

        return $prefijo.self::CLAVE_SUJETO_OBLIGADO.$periodo.'.'.self::ORGANO_SUPERVISOR.'.csv';
    }

    private function nombreArchivoDesdeAlertas(string $tipo, $alertas): string
    {
        $prefijo = match ($tipo) {
            'Relevante'   => '1',
            'Inusual'     => '2',
            'Preocupante' => '3',
            default       => '0',
        };
        $first = $alertas->first();
        $fechaRef = $first->FechaDeteccion ?? $first->FechaOperacion ?? $first->created_at ?? null;
        if ($fechaRef) {
            $ts = strtotime((string) $fechaRef);
            $periodo = $tipo === 'Relevante'
                ? ($ts ? date('ym', $ts) : now()->format('ym'))
                : ($ts ? date('ymd', $ts) : now()->format('ymd'));
        } else {
            $periodo = $tipo === 'Relevante' ? now()->format('ym') : now()->format('ymd');
        }
        return $prefijo.self::CLAVE_SUJETO_OBLIGADO.$periodo.'.'.self::ORGANO_SUPERVISOR.'.csv';
    }

    private function mapearFilasDesdeAlertas($alertas): array
    {
        // Mapeo simplificado desde alertas cuando aún no existe reporte regulatorio.
        // No crea registro, solo genera filas para descarga sin cambiar estatus.
        $codigosMoneda = ['MXN' => '001', 'USD' => '002', '001' => '001', '002' => '002', '1' => '001', '2' => '002'];
        $folio = 0;
        return $alertas->map(function ($a) use ($codigosMoneda, &$folio) {
            $folio++;
            $tipoReporte = match ($a->Patron) {
                'Relevante'   => '1',
                'Preocupante' => '3',
                default       => '2',
            };
            $fechaDet = $a->FechaDeteccion ?? $a->FechaOperacion ?? null;
            $ts = $fechaDet ? strtotime((string) $fechaDet) : null;
            $periodo = $tipoReporte === '1'
                ? ($ts ? date('ym', $ts) : '')
                : ($ts ? date('ymd', $ts) : '');
            $rawMoneda = strtoupper(trim((string) ($a->IDMoneda ?? '')));
            $moneda = $codigosMoneda[$rawMoneda] ?? ($rawMoneda !== '' ? $rawMoneda : '001');
            if (! in_array($moneda, ['001','002'], true)) $moneda = '001';
            $instrumento = trim((string) ($a->InstrumentoMonetario ?? ''));
            if ($instrumento === '') $instrumento = '01';
            if (is_numeric($instrumento)) $instrumento = str_pad($instrumento, 2, '0', STR_PAD_LEFT);

            return [
                $this->limpiar($tipoReporte),
                $this->limpiar($periodo),
                $this->limpiar(str_pad((string) $folio, 6, '0', STR_PAD_LEFT)),
                $this->limpiar('001003'),
                $this->limpiar('022123'),
                $this->limpiar('03342009'),
                $this->limpiar('0'),
                $this->limpiar('10'),
                $this->limpiar($instrumento),
                $this->limpiar($a->Poliza),
                $this->limpiar(number_format((float) ($a->MontoOperacion ?? 0), 2, '.', '')),
                $this->limpiar($moneda),
                $this->limpiar($this->formatearFecha($a->FechaOperacion)),
                $this->limpiar($this->formatearFecha($a->FechaDeteccion)),
                $this->limpiar('MX'),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar($a->Cliente),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar($a->RFCAgente),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar($a->Agente),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar($a->RFCAgente),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar($a->Descripcion),
                $this->limpiar($a->Razones),
                $this->limpiar($a->Estatus),
            ];
        })->all();
    }

    private function mapearFilas($reportes): array
    {
        $codigosMoneda = ['MXN' => '001', 'USD' => '002', '001' => '001', '002' => '002', '1' => '001', '2' => '002'];

        $tiposPersona   = CatTipoPersona::pluck('IDTipoPersona', 'TipoPersona');
        $formasPago     = CatFormaPagos::pluck('IDFormaPago', 'FormaPago');
        $nacionalidades = CatNacionalidad::pluck('IDNacionalidad', 'Nacionalidad');
        $ocupaciones    = CatOcupacionesGiros::pluck('CVE_GIRO', 'OcupacionGiro');

        // Mapas normalizados upper para lookup insensible a mayúsculas
        $formasPagoUpper = [];
        foreach ($formasPago as $nombre => $id) {
            $formasPagoUpper[mb_strtoupper(trim((string) $nombre))] = $id;
        }
        $tiposPersonaUpper = [];
        foreach ($tiposPersona as $nombre => $id) {
            $tiposPersonaUpper[mb_strtoupper(trim((string) $nombre))] = $id;
        }
        $nacionalidadesUpper = [];
        foreach ($nacionalidades as $nombre => $id) {
            $nacionalidadesUpper[mb_strtoupper(trim((string) $nombre))] = $id;
        }
        $ocupacionesUpper = [];
        foreach ($ocupaciones as $nombre => $cve) {
            $ocupacionesUpper[mb_strtoupper(trim((string) $nombre))] = $cve;
        }

        $folio = 0;

        return $reportes->map(function ($r) use ($codigosMoneda, $tiposPersona, $formasPago, $nacionalidades, $ocupaciones, $formasPagoUpper, $tiposPersonaUpper, $nacionalidadesUpper, $ocupacionesUpper, &$folio) {
            $folio++;

            // Instrumento monetario: siempre 2 dígitos, default 01 (Efectivo) si vacío
            $rawInst = trim((string) ($r->InstrumentoMonetario ?? ''));
            $keyUpper = mb_strtoupper($rawInst);
            $instrumento = $formasPago[$rawInst] ?? $formasPagoUpper[$keyUpper] ?? $rawInst;
            if ($instrumento === '' || $instrumento === null) {
                $instrumento = '01';
            } elseif (is_numeric($instrumento)) {
                $instrumento = str_pad((string) $instrumento, 2, '0', STR_PAD_LEFT);
            } else {
                // Si viene texto no mapeado, intentar extraer número
                if (preg_match('/\d+/', (string) $instrumento, $m)) {
                    $instrumento = str_pad($m[0], 2, '0', STR_PAD_LEFT);
                } else {
                    $instrumento = '01';
                }
            }

            $tipoOperacion = $r->IDTipoOperacion
                ? str_pad((string) $r->IDTipoOperacion, 2, '0', STR_PAD_LEFT)
                : '';

            // Moneda nunca en blanco, default 001
            $rawMoneda = strtoupper(trim((string) ($r->IDMoneda ?? '')));
            $moneda = $codigosMoneda[$rawMoneda] ?? ($rawMoneda !== '' ? $rawMoneda : '001');
            if (! in_array($moneda, ['001', '002'], true)) {
                // Si viene texto como "PESOS" o ID numérico desconocido, fallback 001
                $moneda = $codigosMoneda[$rawMoneda] ?? '001';
            }

            // Nacionalidad: si viene texto "Mexicana" mapea a "MX", si ya es código deja igual
            $rawNac = trim((string) ($r->Nacionalidad ?? ''));
            $nacUpper = mb_strtoupper($rawNac);
            $nacionalidad = $nacionalidades[$rawNac] ?? $nacionalidadesUpper[$nacUpper] ?? $rawNac;
            if ($nacionalidad === '' || $nacionalidad === null) {
                $nacionalidad = 'MX';
            }

            // Tipo persona: 1 o 2, mapea texto -> id
            $rawTipo = trim((string) ($r->TipoPersona ?? ''));
            $tipoUpper = mb_strtoupper($rawTipo);
            $tipoPersonaVal = $tiposPersona[$rawTipo] ?? $tiposPersonaUpper[$tipoUpper] ?? $rawTipo;
            // Normalizar nombres comunes
            if ($tipoUpper === 'PERSONA FISICA' || $tipoUpper === 'FISICA' || $tipoUpper === 'PF') {
                $tipoPersonaVal = '1';
            } elseif ($tipoUpper === 'PERSONA MORAL' || $tipoUpper === 'MORAL' || $tipoUpper === 'PM') {
                $tipoPersonaVal = '2';
            }
            if ($tipoPersonaVal === '' || $tipoPersonaVal === null) {
                $tipoPersonaVal = '';
            }

            // Ocupacion: clave CVE_GIRO
            $rawOcup = trim((string) ($r->Ocupacion ?? ''));
            $ocupUpper = mb_strtoupper($rawOcup);
            $ocupacion = $ocupaciones[$rawOcup] ?? $ocupacionesUpper[$ocupUpper] ?? $rawOcup;

            // Coherencia PF/PM para columnas Nombre/RazonSocial/CURP (defensa en capa de exportación)
            $isPF = ((string) $tipoPersonaVal === '1');
            $isPM = ((string) $tipoPersonaVal === '2');
            $razonSocial = $r->RazonSocial;
            $nombre = $r->Nombre;
            $aPaterno = $r->APaterno;
            $aMaterno = $r->AMaterno;
            $curp = $r->CURP;
            if ($isPF) {
                $razonSocial = '';
            } elseif ($isPM) {
                $nombre = '';
                $aPaterno = '';
                $aMaterno = '';
                $curp = '';
            }

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
                $this->limpiar($moneda),
                $this->limpiar($this->formatearFecha($r->FechaOperacion)),
                $this->limpiar($this->formatearFecha($r->FechaDeteccion)),
                $this->limpiar($nacionalidad),
                $this->limpiar($tipoPersonaVal),
                $this->limpiar($razonSocial),
                $this->limpiar($nombre),
                $this->limpiar($aPaterno),
                $this->limpiar($aMaterno),
                $this->limpiar($r->RFC),
                $this->limpiar($curp),
                $this->limpiar($this->formatearFecha($r->FechaNacimiento)),
                $this->limpiar($r->Domicilio),
                $this->limpiar($r->Colonia),
                $this->limpiar($r->Ciudad),
                $this->limpiar($r->Telefono),
                $this->limpiar($ocupacion),
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
        $periodo = preg_replace('/\D/', '', (string) ($r->PeriodoReporte ?? ''));

        if ($tipo === 1) {
            // Relevante: YYMM (4) segun ejemplo 2022123260731.003 -> yymm
            if (strlen($periodo) >= 4) {
                // Si viene YYYYMM (6) convertir a YYMM
                if (strlen($periodo) >= 6) {
                    return substr($periodo, -4);
                }
                return substr($periodo, 0, 4);
            }
            return $r->FechaDeteccion ? date('ym', strtotime((string) $r->FechaDeteccion)) : '';
        }

        // Inusual/Preocupante: YYMMDD (6)
        if (strlen($periodo) >= 6) {
            if (strlen($periodo) >= 8) {
                // YYYYMMDD -> YYMMDD
                return substr($periodo, -6);
            }
            return substr($periodo, 0, 6);
        }

        return $r->FechaDeteccion ? date('ymd', strtotime((string) $r->FechaDeteccion)) : '';
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
