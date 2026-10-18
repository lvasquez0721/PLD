<?php

namespace App\Http\Controllers;

use App\Models\CatFormaPagos;
use App\Models\Clientes\CatLocalidad;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\CatTipoPersona;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbReporteRegulatorioPLD;
use App\Services\PLD\ReporteRegulatorioService;
use App\Support\Curp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteOperacionesController extends Controller
{
    private const ESTATUS = ['Enviado', 'Por reportar'];

    /**
     * Patrones con tipo de reporte propio (se filtran por igualdad).
     * Todo patrón no listado aquí se considera "Monto Inusual"
     * (Cancelacion, Fraccionado, Acumulado Efectivo, Nuevo, Monto Inusual, PPE, etc.).
     */
    private const PATRONES_PROPIOS = [
        'Monto',
        'Nuevo',
    ];

    private const TIPOS_REPORTE = [
        'Monto' => ['Monto'],
        'Nuevo' => ['Nuevo'],
    ];

    private const CLAVE_SUJETO_OBLIGADO = '022123';

    private const ORGANO_SUPERVISOR = '003';

    /**
     * Encabezados del layout de operaciones monto, monto inusual y nuevo.
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
            ->orderBy('a.created_at', 'desc')
            ->get();

        return response()->json(['alertas' => $alertas]);
    }

    public function exportarCSV(Request $request)
    {
        $validated = $request->validate([
            'ids'          => 'nullable|array',
            'ids.*'        => 'integer',
            'tipo_reporte' => 'required|string|in:Monto,Monto Inusual,Nuevo,Todos,Todas',
            'con_headers'  => 'nullable|boolean',
        ]);

        $tipo = $validated['tipo_reporte'] === 'Todas' ? 'Todos' : $validated['tipo_reporte'];

        $filtros = $request->only(['tipo_reporte', 'estatus', 'fecha_ini', 'fecha_fin']);
        $filtros['tipo_reporte'] = $tipo;

        $query = $this->construirQuery($filtros);

        if (! empty($validated['ids'])) {
            $query->whereIn('a.IDRegistroAlerta', $validated['ids']);
        }

        $alertas = $query->orderBy('a.created_at', 'desc')->get();

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

            // Filtrar filas totalmente vacías para no generar un registro en blanco extra
            $filas = array_values(array_filter($filas, function ($fila) {
                if (! is_array($fila)) return false;
                foreach ($fila as $celda) {
                    if (trim((string) $celda) !== '') return true;
                }
                return false;
            }));

            $primeraLinea = true;
            $totalColumnas = count(self::LAYOUT_HEADERS);

            if ($conHeaders) {
                fwrite($file, implode(';', self::LAYOUT_HEADERS).';');
                $primeraLinea = false;
            }

            foreach ($filas as $fila) {
                if (! $primeraLinea) {
                    fwrite($file, "\r\n");
                }

                // Garantizar siempre las 41 columnas y el delimitador final.
                $fila = array_values((array) $fila);
                if (count($fila) < $totalColumnas) {
                    $fila = array_pad($fila, $totalColumnas, '');
                } elseif (count($fila) > $totalColumnas) {
                    $fila = array_slice($fila, 0, $totalColumnas);
                }

                fwrite($file, implode(';', $fila).';');
                $primeraLinea = false;
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
            'tipo_reporte' => 'nullable|string|in:Monto,Monto Inusual,Nuevo,Todos,Todas',
        ]);

        $tipo = $validated['tipo_reporte'] ?? 'Todos';
        if ($tipo === 'Todas') {
            $tipo = 'Todos';
        }

        $filtros = $request->only(['tipo_reporte', 'estatus', 'fecha_ini', 'fecha_fin']);
        $filtros['tipo_reporte'] = $tipo;

        $query = $this->construirQuery($filtros);
        $query->whereIn('a.IDRegistroAlerta', $validated['ids']);
        // Solo se pueden reportar los que están Por reportar
        $query->where('a.Estatus', 'Por reportar');

        $alertas = $query->get();

        if ($alertas->isEmpty()) {
            return response()->json(['message' => 'No hay registros por reportar con los criterios seleccionados. Verifique que los registros estén en estatus "Por reportar".'], 422);
        }

        try {
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
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error al reportar operaciones', [
                'ids' => $validated['ids'],
                'exception' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'No se pudo completar el reporte. Verifique los datos de los registros seleccionados e intente de nuevo.'], 500);
        }

        return response()->json([
            'message' => 'Se reportaron '.count($alertas).' registro(s) correctamente.',
            'count'   => count($alertas),
        ]);
    }

    private function construirQuery(array $filtros)
    {
        $query = TbAlertas::from('tbAlertas as a')
            ->leftJoin('tbOperaciones as op', 'op.IDOperacion', '=', 'a.IDOperacion')
            // Mostrar todos los patrones de alerta, solo con estatus Por reportar / Enviado
            ->whereIn('a.Estatus', self::ESTATUS)
            ->select([
                'a.*',
                'op.FolioEndoso as OperacionFolioEndoso',
                'op.PrimaTotal as OperacionPrimaTotal',
                'op.GastosEmision as OperacionGastosEmision',
                'op.IDMoneda as OperacionIDMoneda',
                'op.IDFormaPago as OperacionIDFormaPago',
                'op.FechaEmision as OperacionFechaEmision',
                'op.FechaInicioVigencia as OperacionFechaInicioVigencia',
                'op.FechaFinVigencia as OperacionFechaFinVigencia',
                'op.tipoDocumento as OperacionTipoDocumento',
                'op.EsquemaDePago as OperacionEsquemaDePago',
                'op.PagaTercero as OperacionPagaTercero',
                'op.operacionCancelada as OperacionCancelada',
                'op.EsEndosoCancelacion as OperacionEsEndosoCancelacion',
                'op.cancelaPoliza as OperacionCancelaPoliza',
                'op.NombreAgente as OperacionNombreAgente',
                'op.APaternoAgente as OperacionAPaternoAgente',
                'op.AMaternoAgente as OperacionAMaternoAgente',
                'op.RazonSocialAgente as OperacionRazonSocialAgente',
                'op.RFCAgente as OperacionRFCAgente',
                'op.FolioPoliza as OperacionFolioPoliza',
            ]);

        $tipo = $filtros['tipo_reporte'] ?? '';
        if ($tipo === 'Todas') {
            $tipo = 'Todos';
        }

        if (! empty($tipo) && $tipo !== 'Todos') {
            if ($tipo === 'Monto Inusual') {
                // Monto Inusual = patrón "Monto Inusual" + todo patrón no mencionado en el select
                // (Cancelacion, Fraccionado, Acumulado Efectivo, Nuevo, PPE, etc.).
                // Se incluye IS NULL porque NOT IN por sí solo excluye los nulos en MySQL.
                $query->where(function ($q) {
                    $q->whereNotIn('a.Patron', self::PATRONES_PROPIOS)
                      ->orWhereNull('a.Patron');
                });
            } elseif (isset(self::TIPOS_REPORTE[$tipo])) {
                $query->whereIn('a.Patron', self::TIPOS_REPORTE[$tipo]);
            }
        }

        if (! empty($filtros['estatus']) && $filtros['estatus'] !== 'Todos' && in_array($filtros['estatus'], self::ESTATUS, true)) {
            $query->where('a.Estatus', $filtros['estatus']);
        }

        if (! empty($filtros['fecha_ini']) && ! empty($filtros['fecha_fin'])) {
            $inicio = $filtros['fecha_ini'].' 00:00:00';
            $fin = $filtros['fecha_fin'].' 23:59:59';
            $query->whereBetween('a.created_at', [$inicio, $fin]);
        }

        return $query;
    }

    /**
     * Nombre del archivo conforme al Anexo 2:
     * TIPO(1) + CLAVE_SUJETO(6) + PERIODO(YYMM 4 / YYMMDD 6) + "." + ORGANO_SUPERVISOR(3).
     * Ej. 2022123260731.003 => 2 022123 260731 .003
     * El periodo corresponde a la fecha de generación del reporte (hoy).
     */
    private function nombreArchivo(string $tipo, $reportes = null): string
    {
        return $this->construirNombreArchivo($tipo);
    }

    private function nombreArchivoDesdeAlertas(string $tipo, $alertas): string
    {
        return $this->construirNombreArchivo($tipo);
    }

    private function construirNombreArchivo(string $tipo): string
    {
        $prefijo = match ($tipo) {
            'Monto'         => '1',
            'Monto Inusual' => '2',
            'Nuevo'         => '3',
            default         => '0',
        };

        $periodo = $tipo === 'Monto' ? now()->format('ym') : now()->format('ymd');

        return $prefijo.self::CLAVE_SUJETO_OBLIGADO.$periodo.'.'.self::ORGANO_SUPERVISOR.'.csv';
    }

    private function mapearFilasDesdeAlertas($alertas): array
    {
        // Mapeo desde alertas cuando aún no existe reporte regulatorio.
        // No crea registro, solo genera filas para descarga sin cambiar estatus.
        $codigosMoneda = ['MXN' => '1', 'USD' => '2', '001' => '1', '002' => '2', '01' => '1', '02' => '2', '1' => '1', '2' => '2'];
        $ocupacionesPorId = CatOcupacionesGiros::pluck('CVE_GIRO', 'IDOcupacionGiro');

        [, $clientes] = $this->contextoClientes($alertas);

        $folio = 0;
        return $alertas->map(function ($a) use ($codigosMoneda, $ocupacionesPorId, $clientes, &$folio) {
            $folio++;
            $tipoReporte = match ($a->Patron) {
                'Monto' => '1',
                'Nuevo' => '3',
                default => '2',
            };
            // El periodo del reporte corresponde a la fecha de generación (hoy),
            // no a la fecha de operación/detección. Es el mismo para todos los registros.
            $periodo = $this->periodoDesdeFechaGeneracion($tipoReporte);
            $rawMoneda = strtoupper(trim((string) ($a->IDMoneda ?? '')));
            $moneda = $codigosMoneda[$rawMoneda] ?? ($rawMoneda !== '' ? $rawMoneda : '1');
            $moneda = ltrim((string) $moneda, '0');
            if ($moneda === '') $moneda = '0';
            if (! in_array($moneda, ['1','2'], true)) $moneda = '1';
            $instrumento = trim((string) ($a->InstrumentoMonetario ?? ''));
            if ($instrumento === '') $instrumento = '01';
            if (is_numeric($instrumento)) $instrumento = str_pad($instrumento, 2, '0', STR_PAD_LEFT);

            // Col.14 FechaDeteccion vacía solo en Monto (tipo 1)
            $fechaDeteccion = $tipoReporte === '1' ? '' : $this->formatearFecha($a->FechaDeteccion);

            // Cols.29-31: fraccionar nombre completo del agente
            [$nomAg, $patAg, $matAg] = $this->fraccionarAgente(
                $a->Agente ?? null,
                $a->OperacionNombreAgente ?? null,
                $a->OperacionAPaternoAgente ?? null,
                $a->OperacionAMaternoAgente ?? null,
                $a->OperacionRazonSocialAgente ?? null
            );

            $cliente = $clientes->get($a->IDCliente);
            $domicilio = $cliente?->domicilios->first();

            $idTipoPersona = (string) ($cliente?->IDTipoPersona ?? '');
            $tipoPersonaVal = in_array($idTipoPersona, ['1', '2'], true) ? $idTipoPersona : '';
            $esPF = $tipoPersonaVal === '1';
            $esPM = $tipoPersonaVal === '2';

            $razonSocial = $esPM ? $cliente?->RazonSocial : ($esPF ? '' : $cliente?->RazonSocial);
            $nombre = $esPF || ! $esPM ? ($cliente?->Nombre ?? $a->Cliente) : '';
            $aPaterno = $esPF ? $cliente?->ApellidoPaterno : '';
            $aMaterno = $esPF ? $cliente?->ApellidoMaterno : '';
            $curp = $esPF ? $cliente?->CURP : '';

            $fechaNacimiento = $this->fechaNacimientoCliente($cliente, $tipoPersonaVal);
            $ciudad = $this->claveLocalidad($domicilio);
            $ocupacion = $this->claveOcupacion($cliente?->IDOcupacionGiro, $ocupacionesPorId);

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
                $this->limpiar($fechaDeteccion),
                $this->limpiar('MX'),
                $this->limpiar($tipoPersonaVal),
                $this->limpiar($razonSocial),
                $this->limpiar($nombre),
                $this->limpiar($aPaterno),
                $this->limpiar($aMaterno),
                $this->limpiar($cliente?->RFC ?? $a->RFCAgente),
                $this->limpiar($curp),
                $this->limpiar($this->formatearFecha($fechaNacimiento)),
                $this->limpiar($this->domicilioTexto($domicilio)),
                $this->limpiar($domicilio?->Colonia),
                $this->limpiar($ciudad),
                $this->limpiar($domicilio?->Telefono),
                $this->limpiar($ocupacion),
                $this->limpiar($nomAg),
                $this->limpiar($patAg),
                $this->limpiar($matAg),
                $this->limpiar($a->RFCAgente),
                $this->limpiar(''),
                $this->limpiar('00'),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar(''),
                $this->limpiar($a->Descripcion),
                $this->limpiar($a->Razones),
            ];
        })->all();
    }

    private function mapearFilas($reportes): array
    {
        $codigosMoneda = ['MXN' => '1', 'USD' => '2', '001' => '1', '002' => '2', '01' => '1', '02' => '2', '1' => '1', '2' => '2'];

        $tiposPersona   = CatTipoPersona::pluck('IDTipoPersona', 'TipoPersona');
        $formasPago     = CatFormaPagos::pluck('IDFormaPago', 'FormaPago');
        $nacionalidades = CatNacionalidad::pluck('IDNacionalidad', 'Nacionalidad');
        $ocupaciones    = CatOcupacionesGiros::pluck('CVE_GIRO', 'OcupacionGiro');
        $ocupacionesPorId = CatOcupacionesGiros::pluck('CVE_GIRO', 'IDOcupacionGiro');

        // Datos actuales de cliente/domicilio para completar campos que el
        // reporte regulatorio haya guardado vacíos (p. ej. fecha de nacimiento,
        // ciudad u ocupación). No se sobreescriben valores ya capturados.
        $alertasReporte = TbAlertas::whereIn(
            'IDRegistroAlerta',
            $reportes->pluck('IDRegistroAlerta')->filter()->unique()->values()
        )->get();
        [$alertasPorRegistro, $clientes] = $this->contextoClientes($alertasReporte);

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

        return $reportes->map(function ($r) use ($codigosMoneda, $tiposPersona, $formasPago, $nacionalidades, $ocupaciones, $ocupacionesPorId, $alertasPorRegistro, $clientes, $formasPagoUpper, $tiposPersonaUpper, $nacionalidadesUpper, $ocupacionesUpper, &$folio) {
            $folio++;

            $alerta = $alertasPorRegistro->get($r->IDRegistroAlerta);
            $cliente = $alerta ? $clientes->get($alerta->IDCliente) : null;
            $domicilio = $cliente?->domicilios->first();

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

            // Moneda nunca en blanco, default 1 (un dígito, sin padding)
            $rawMoneda = strtoupper(trim((string) ($r->IDMoneda ?? '')));
            $moneda = $codigosMoneda[$rawMoneda] ?? ($rawMoneda !== '' ? $rawMoneda : '1');
            $moneda = ltrim((string) $moneda, '0');
            if ($moneda === '') $moneda = '0';
            if (! in_array($moneda, ['1', '2'], true)) {
                // Si viene texto como "PESOS" o ID numérico desconocido, fallback 1
                $moneda = $codigosMoneda[$rawMoneda] ?? '1';
                $moneda = ltrim((string) $moneda, '0');
                if ($moneda === '' || ! in_array($moneda, ['1', '2'], true)) $moneda = '1';
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

            // Ocupacion: clave CVE_GIRO. Si el reporte la guardó vacía, se toma del cliente.
            $ocupacion = $this->claveOcupacion($r->Ocupacion, $ocupacionesPorId, $ocupacionesUpper);
            if ($ocupacion === '' && $cliente) {
                $ocupacion = $this->claveOcupacion($cliente->IDOcupacionGiro, $ocupacionesPorId);
            }

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

            // Para PF la fecha se toma de la CURP (aunque el snapshot tenga otra);
            // para PM se respeta la fecha del reporte y se completa si viene vacía.
            if ($isPF) {
                $fechaNacimiento = $this->fechaNacimientoCliente($cliente, '1') ?: $r->FechaNacimiento;
            } else {
                $fechaNacimiento = $r->FechaNacimiento ?: $this->fechaNacimientoCliente($cliente, $tipoPersonaVal);
            }
            $domicilioTexto = $r->Domicilio ?: $this->domicilioTexto($domicilio);
            $colonia = $r->Colonia ?: $domicilio?->Colonia;
            $ciudad = $this->claveLocalidad($domicilio) ?: $r->Ciudad;
            $telefono = $r->Telefono ?: $domicilio?->Telefono;

            // Col.14 FechaDeteccion vacía solo en Monto (IDTipoReporte 1)
            $esRelevante = ((int) ($r->IDTipoReporte ?? 0) === 1);
            $fechaDeteccion = $esRelevante ? '' : $this->formatearFecha($r->FechaDeteccion);

            // Cols.29-31: si viene nombre completo sin desglose, fraccionar
            [$nomAg, $patAg, $matAg] = $this->fraccionarAgente(
                $r->NombreAgente ?? null, null, $r->APaternoAgente ?? null, $r->AMaternoAgente ?? null, null
            );

            // Col.34 consecutivo: operación principal sin relacionados = 00
            $cuenta = trim((string) ($r->Cuenta ?? ''));
            if ($cuenta === '') $cuenta = '00';

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
                $this->limpiar($fechaDeteccion),
                $this->limpiar($nacionalidad),
                $this->limpiar($tipoPersonaVal),
                $this->limpiar($razonSocial),
                $this->limpiar($nombre),
                $this->limpiar($aPaterno),
                $this->limpiar($aMaterno),
                $this->limpiar($r->RFC),
                $this->limpiar($curp),
                $this->limpiar($this->formatearFecha($fechaNacimiento)),
                $this->limpiar($domicilioTexto),
                $this->limpiar($colonia),
                $this->limpiar($ciudad),
                $this->limpiar($telefono),
                $this->limpiar($ocupacion),
                $this->limpiar($nomAg),
                $this->limpiar($patAg),
                $this->limpiar($matAg),
                $this->limpiar($r->RFCAgente),
                $this->limpiar($r->CURPAgente),
                $this->limpiar($cuenta),
                $this->limpiar($r->NoPolizaCuenta),
                $this->limpiar($r->CveSujetoObl),
                $this->limpiar($r->NombreTitular),
                $this->limpiar($r->APaternoTitular),
                $this->limpiar($r->AMaternoTitular),
                $this->limpiar($r->Descripcion),
                $this->limpiar($r->Razon),
            ];
        })->all();
    }

    private function periodoReporte($r): string
    {
        // El periodo del reporte corresponde a la fecha de generación (hoy),
        // no a la fecha de operación/detección. Es el mismo para todos los registros.
        return $this->periodoDesdeFechaGeneracion($r->IDTipoReporte ?? 1);
    }

    private function formatearFecha($fecha): string
    {
        if (empty($fecha)) {
            return '';
        }

        $timestamp = strtotime((string) $fecha);

        return $timestamp ? date('Ymd', $timestamp) : '';
    }

    /**
     * Indexa alertas y clientes para completar datos faltantes del reporte.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function contextoClientes($alertas): array
    {
        $alertasPorRegistro = collect($alertas)->keyBy('IDRegistroAlerta');
        $idsClientes = $alertasPorRegistro->pluck('IDCliente')->filter()->unique()->values()->all();

        $clientes = empty($idsClientes)
            ? collect()
            : TbClientes::with('domicilios')->whereIn('IDCliente', $idsClientes)->get()->keyBy('IDCliente');

        return [$alertasPorRegistro, $clientes];
    }

    /**
     * Periodo del reporte con la fecha de generación (hoy).
     * Monto usa YYYYMM; Monto Inusual/Nuevo usan YYYYMMDD.
     */
    private function periodoDesdeFechaGeneracion($tipoReporte): string
    {
        return (int) $tipoReporte === 1
            ? now()->format('Ym')
            : now()->format('Ymd');
    }

    /**
     * Fecha de nacimiento (PF) o de constitución (PM). Para personas físicas
     * se obtiene de la CURP y, si no es válida, se usan las fechas capturadas.
     */
    private function fechaNacimientoCliente(?TbClientes $cliente, string $tipoPersona)
    {
        if (! $cliente) {
            return null;
        }

        if ($tipoPersona === '1') {
            return Curp::fechaNacimiento($cliente->CURP)
                ?? $cliente->FechaNacimiento
                ?? $cliente->FechaConstitucion;
        }

        if ($tipoPersona === '2') {
            return $cliente->FechaConstitucion ?? $cliente->FechaNacimiento;
        }

        return $cliente->FechaNacimiento ?? $cliente->FechaConstitucion;
    }

    private function domicilioTexto($domicilio): string
    {
        if (! $domicilio) {
            return '';
        }

        return trim(implode(' ', array_filter([
            $domicilio->Calle ?? '',
            $domicilio->NoExterior ?? '',
            $domicilio->NoInterior ?? '',
            $domicilio->CP ? 'CP: '.$domicilio->CP : '',
        ])));
    }

    /**
     * Resuelve la clave de ocupación/giro (CVE_GIRO). Acepta el ID del catálogo,
     * el nombre de la ocupación o directamente la clave numérica.
     */
    private function claveOcupacion($valor, $porId = [], $porNombre = []): string
    {
        $raw = trim((string) ($valor ?? ''));
        if ($raw === '') {
            return '';
        }

        if (isset($porId[$raw])) {
            return (string) $porId[$raw];
        }

        $upper = mb_strtoupper($raw);
        if (isset($porNombre[$upper])) {
            return (string) $porNombre[$upper];
        }

        // Si no está en el catálogo pero es numérico, se considera clave de giro.
        if (is_numeric($raw)) {
            return str_pad($raw, 7, '0', STR_PAD_LEFT);
        }

        return $raw;
    }

    /**
     * Resuelve la clave de localidad de 8 digitos (EE MM LLLL) a partir del
     * domicilio del cliente. Compara el texto libre de
     * tbClientesDomicilio.Municipio/Localidad contra el catalogo de
     * localidades y se queda con la coincidencia mas semejante.
     */
    private function claveLocalidad($domicilio): string
    {
        $municipio = trim((string) ($domicilio->Municipio ?? ''));
        $localidad = trim((string) ($domicilio->Localidad ?? ''));
        $objetivo  = $municipio !== '' ? $municipio : $localidad;
        if ($objetivo === '') {
            return '';
        }

        $catalogo = $this->catalogoLocalidades();

        $mejorClave = '';
        $mejorSim   = -1;
        foreach ($catalogo as $clave => $nombreNorm) {
            $sim = similar_text($this->normalizarTexto($objetivo), $nombreNorm);
            if ($sim > $mejorSim) {
                $mejorSim = $sim;
                $mejorClave = $clave;
            }
        }

        return $mejorClave !== '' ? (string) $mejorClave : '';
    }

    /**
     * Devuelve el catalogo de localidades indexado por cveLocalidad con el
     * nombre ya normalizado (sin sufijos de estado ni tildes).
     */
    private function catalogoLocalidades(): array
    {
        static $catalogo = null;
        if ($catalogo !== null) {
            return $catalogo;
        }

        $catalogo = [];
        foreach (CatLocalidad::pluck('Localidad', 'IDLocalidad') as $cve => $nombre) {
            $catalogo[(string) $cve] = $this->normalizarNombreLocalidad((string) $nombre);
        }

        return $catalogo;
    }

    /**
     * Normaliza un texto para comparacion: minusculas, sin tildes, sin
     * caracteres no alfanumericos y espacios colapsados.
     */
    private function normalizarTexto($texto): string
    {
        $texto = mb_strtolower((string) $texto);
        $texto = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto) ?? $texto;
        $texto = preg_replace('/[^a-z0-9ñ ]/u', '', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);

        return trim($texto);
    }

    /**
     * Normaliza el nombre de una localidad del catalogo quitando especificadores
     * de municipio/localidad y sufijos de estado (p. ej. " MEX", " OAX").
     */
    private function normalizarNombreLocalidad($nombre): string
    {
        $nombre = $this->normalizarTexto($nombre);

        $nombre = preg_replace('/\b(municipio|localidad) de /', ' ', $nombre);

        $estados = [
            'ags', 'bc', 'bcs', 'camp', 'coah', 'col', 'chis', 'chih', 'df',
            'dur', 'gto', 'gro', 'hgo', 'jal', 'mex', 'mich', 'mor', 'nay',
            'nl', 'oax', 'pue', 'qro', 'qroo', 'slp', 'sin', 'son', 'tab',
            'tamps', 'tlax', 'ver', 'yuc', 'zac',
        ];
        foreach ($estados as $estado) {
            $sufijo = ' '.$estado;
            if (str_ends_with($nombre, $sufijo)) {
                $nombre = rtrim(substr($nombre, 0, -strlen($sufijo)));
            }
        }

        return trim($nombre);
    }

    /**
     * Fracciona nombre completo del agente en [Nombre, APaterno, AMaterno].
     * Prioridad: desglose ya capturado (nombre/paterno/materno/razón social).
     * Fallback: split del string completo (>=3 tokens: Nombre=tokens[0..n-3]).
     * Regla layout PF: sin paterno => XXXX.
     */
    private function fraccionarAgente($full = null, $nombre = null, $paterno = null, $materno = null, $razonSocial = null): array
    {
        $razonSocial = trim((string) ($razonSocial ?? ''));
        $nombre = trim((string) ($nombre ?? ''));
        $paterno = trim((string) ($paterno ?? ''));
        $materno = trim((string) ($materno ?? ''));
        $full = trim(preg_replace('/\s+/', ' ', (string) ($full ?? '')));

        // Si hay razón social de agente moral, va completa en Nombre y vacíos en apellidos
        if ($razonSocial !== '') {
            return [$razonSocial, '', ''];
        }

        if ($nombre !== '' && ($paterno !== '' || $materno !== '')) {
            if ($nombre !== '' && $paterno === '' && $materno !== '') {
                $paterno = 'XXXX';
            }
            return [$nombre, $paterno, $materno];
        }

        $base = $nombre !== '' ? $nombre : $full;
        if ($base === '') {
            return ['', '', ''];
        }

        $tokens = preg_split('/\s+/', $base, -1, PREG_SPLIT_NO_EMPTY);
        if (count($tokens) >= 3) {
            $mat = array_pop($tokens);
            $pat = array_pop($tokens);
            return [implode(' ', $tokens), $pat, $mat];
        }
        if (count($tokens) === 2) {
            return [$tokens[0], $tokens[1], ''];
        }
        return [$tokens[0], 'XXXX', ''];
    }

    private function limpiar($value): string
    {
        $value = (string) ($value ?? '');
        $value = str_replace(';', ' ', $value);
        $value = preg_replace('/[\r\n]+/', ' ', $value);

        return mb_strtoupper(trim($value));
    }
}
