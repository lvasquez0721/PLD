<?php

namespace App\Services\Ebr;

use App\Models\Ebr\EbrAlerta;
use App\Models\Ebr\EbrCatActividad;
use App\Models\Ebr\EbrCatEstado;
use App\Models\Ebr\EbrCatProducto;
use App\Models\Ebr\EbrEjercicio;
use App\Models\Ebr\EbrImportacion;
use App\Models\Ebr\EbrPoliza;
use App\Models\Ebr\EbrSiniestro;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportadorEbr
{
    private array $config;

    private array $estados;

    private array $productos;

    private array $actividades;

    private ?Carbon $periodoInicio = null;

    private ?Carbon $periodoFin = null;

    private int $lineaActual = 0;

    /** @var array<int, array{linea:int, campo:?string, valor:mixed, mensaje:string}> */
    private array $advertencias = [];

    /** @var array<string, true>|null */
    private ?array $polizasEjercicio = null;

    /** @var array<string, true>|null */
    private ?array $clientesEjercicio = null;

    /** @var array{siniestros:int, alertas:int} */
    private array $omitidas = ['siniestros' => 0, 'alertas' => 0];

    public function __construct()
    {
        $this->config = config('ebr');
        $this->estados = EbrCatEstado::where('activo', true)->get()->keyBy('estado')->map(fn ($e) => $e->toArray())->all();
        $this->productos = EbrCatProducto::where('activo', true)->get()->keyBy('producto')->map(fn ($p) => $p->toArray())->all();
        $this->actividades = EbrCatActividad::where('activo', true)->get()->keyBy('ocupacion_giro')->map(fn ($a) => $a->toArray())->all();
    }

    public function plantilla(string $tipo): array
    {
        return $this->config['csv'][$tipo] ?? [];
    }

    public function prevalidar(EbrEjercicio $ejercicio, string $tipo, UploadedFile $archivo, ?int $usuarioId = null): EbrImportacion
    {
        $nombre = Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)).'.csv';
        $ruta = "ebr/{$ejercicio->anio}/{$tipo}_".now()->format('YmdHis')."_{$nombre}";
        Storage::disk('local')->put($ruta, $archivo->get());

        $this->periodoInicio = Carbon::parse($ejercicio->fecha_inicio)->startOfDay();
        $this->periodoFin = Carbon::parse($ejercicio->fecha_fin)->endOfDay();
        $parsed = $this->parsear(Storage::disk('local')->path($ruta), $tipo, $ejercicio);
        $errores = $parsed['errores'];
        $resumen = $parsed['resumen'];
        if ($parsed['total'] === 0 && $tipo === 'clientes') {
            $errores[] = ['linea' => null, 'campo' => null, 'valor' => null, 'mensaje' => 'El archivo no contiene filas de datos.'];
        }

        $importacion = EbrImportacion::create([
            'ejercicio_id' => $ejercicio->id,
            'tipo' => $tipo,
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'ruta' => $ruta,
            'hash' => hash_file('sha256', Storage::disk('local')->path($ruta)),
            'total_filas' => $parsed['total'],
            'filas_validas' => $parsed['validas'],
            'filas_error' => count($errores),
            'errores' => array_slice($errores, 0, 500),
            'resumen' => $resumen,
            'estado' => empty($errores) ? 'prevalidado' : 'error',
            'usuario_id' => $usuarioId,
        ]);

        return $importacion;
    }

    public function importar(EbrEjercicio $ejercicio, EbrImportacion $importacion): EbrImportacion
    {
        if ($importacion->estado === 'error') {
            return $importacion;
        }

        $this->periodoInicio = Carbon::parse($ejercicio->fecha_inicio)->startOfDay();
        $this->periodoFin = Carbon::parse($ejercicio->fecha_fin)->endOfDay();
        $parsed = $this->parsear(Storage::disk('local')->path($importacion->ruta), $importacion->tipo, $ejercicio);
        if (! empty($parsed['errores'])) {
            $importacion->update([
                'estado' => 'error',
                'errores' => array_slice($parsed['errores'], 0, 500),
                'filas_error' => count($parsed['errores']),
            ]);

            return $importacion;
        }

        DB::transaction(function () use ($ejercicio, $importacion, $parsed) {
            match ($importacion->tipo) {
                'clientes' => $this->guardarClientes($ejercicio, $parsed['rows']),
                'siniestros' => $this->guardarSiniestros($ejercicio, $parsed['rows']),
                'alertas' => $this->guardarAlertas($ejercicio, $parsed['rows']),
            };

            $importacion->update([
                'estado' => 'importado',
                'filas_validas' => $parsed['validas'],
                'filas_error' => 0,
                'errores' => [],
                'resumen' => $parsed['resumen'],
            ]);
        });

        if ($ejercicio->estado === 'calculado') {
            $ejercicio->update(['estado' => 'borrador', 'resultados' => null, 'totales' => null, 'calculado_at' => null]);
        }

        return $importacion->fresh();
    }

    private function parsear(string $path, string $tipo, ?EbrEjercicio $ejercicio = null): array
    {
        $contenido = (string) file_get_contents($path);
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;

        $primeraLinea = strtok($contenido, "\n") ?: '';
        $delimitador = $this->detectarDelimitador($primeraLinea);

        $tmp = tempnam(sys_get_temp_dir(), 'ebr_');
        file_put_contents($tmp, $contenido);
        $file = new \SplFileObject($tmp);
        $file->setCsvControl($delimitador, '"', '\\');

        $this->advertencias = [];
        $this->omitidas = ['siniestros' => 0, 'alertas' => 0];
        $this->cargarVinculos($ejercicio);
        if ($ejercicio && in_array($tipo, ['siniestros', 'alertas'], true) && $this->polizasEjercicio === null) {
            $this->advertir('vinculo', null, 'No hay pólizas del periodo importadas; las referencias de este archivo se validarán al calcular el EBR.');
        }

        $headers = [];
        $rows = [];
        $errores = [];
        $claves = [];
        $linea = 0;
        while (! $file->eof()) {
            $data = $file->fgetcsv();
            $linea++;
            if ($data === false || $data === [null] || (count($data) === 1 && trim((string) $data[0]) === '')) {
                continue;
            }
            if ($linea === 1) {
                $this->lineaActual = 1;
                $headers = array_map(fn ($h) => $this->normalizarHeader((string) $h), $data);
                $this->validarHeaders($tipo, $headers, $errores);

                continue;
            }

            $this->lineaActual = $linea;
            $fila = [];
            foreach ($headers as $i => $header) {
                $fila[$header] = trim((string) ($data[$i] ?? ''));
            }
            $fila['__linea'] = $linea;

            $resultado = match ($tipo) {
                'clientes' => $this->normalizarCliente($fila),
                'siniestros' => $this->normalizarSiniestro($fila),
                'alertas' => $this->normalizarAlerta($fila),
            };

            if (! empty($resultado['errores'])) {
                foreach ($resultado['errores'] as $error) {
                    $errores[] = ['linea' => $linea, 'campo' => $error['campo'] ?? null, 'valor' => $error['valor'] ?? null, 'mensaje' => $error['mensaje']];
                }

                continue;
            }

            if (! empty($resultado['omitida'])) {
                $this->omitidas[$tipo]++;

                continue;
            }

            $clave = match ($tipo) {
                'clientes' => $resultado['data']['clave_operacion'] ?? null,
                'siniestros' => $resultado['data']['no_siniestro'] ?? null,
                'alertas' => $resultado['data']['row_hash'] ?? null,
            };
            if ($clave !== null && isset($claves[$clave])) {
                $errores[] = [
                    'linea' => $linea,
                    'campo' => $tipo === 'clientes' ? 'id_operacion' : ($tipo === 'siniestros' ? 'no_siniestro' : null),
                    'valor' => $tipo === 'alertas' ? null : $clave,
                    'mensaje' => 'Registro duplicado en el archivo.',
                ];

                continue;
            }
            if ($clave !== null) {
                $claves[$clave] = true;
            }
            $rows[] = $resultado['data'];
        }
        @unlink($tmp);

        $resumen = $this->resumen($tipo, $rows, count($errores));
        $resumen['advertencias'] = array_slice($this->advertencias, 0, 100);
        $resumen['advertencias_total'] = count($this->advertencias);
        $resumen['omitidas'] = $this->omitidas;

        return [
            'total' => max(0, $linea - 1),
            'validas' => count($rows),
            'rows' => $rows,
            'errores' => $errores,
            'resumen' => $resumen,
            'advertencias' => $this->advertencias,
        ];
    }

    private function validarHeaders(string $tipo, array $headers, array &$errores): void
    {
        $esperados = $this->config['csv'][$tipo]['headers'] ?? [];
        $requeridos = $this->config['csv'][$tipo]['required'] ?? [];

        $faltantes = array_values(array_diff($esperados, $headers));
        $faltantesRequeridos = array_values(array_intersect($faltantes, $requeridos));

        if (! empty($faltantesRequeridos)) {
            $errores[] = [
                'linea' => 1,
                'campo' => implode(', ', $faltantesRequeridos),
                'valor' => null,
                'mensaje' => 'Faltan columnas obligatorias en el CSV: '.implode(', ', $faltantesRequeridos).'.',
            ];
        }

        $faltantesOpcionales = array_values(array_diff($faltantes, $faltantesRequeridos));
        if (! empty($faltantesOpcionales)) {
            $this->advertir('columnas', null, 'Columnas opcionales no encontradas: '.implode(', ', $faltantesOpcionales).'.');
        }
    }

    private function cargarVinculos(?EbrEjercicio $ejercicio): void
    {
        $this->polizasEjercicio = null;
        $this->clientesEjercicio = null;

        if (! $ejercicio) {
            return;
        }

        $base = EbrPoliza::where('ejercicio_id', $ejercicio->id);
        if (! (clone $base)->exists()) {
            return;
        }

        $this->polizasEjercicio = $base
            ->pluck('no_poliza')
            ->filter()
            ->map(fn ($valor) => (string) $valor)
            ->flip()
            ->all();

        $this->clientesEjercicio = EbrPoliza::where('ejercicio_id', $ejercicio->id)
            ->whereNotNull('no_cliente')
            ->pluck('no_cliente')
            ->filter()
            ->map(fn ($valor) => (string) $valor)
            ->flip()
            ->all();
    }

    private function advertir(string $campo, mixed $valor, string $mensaje): void
    {
        $this->advertencias[] = [
            'linea' => $this->lineaActual,
            'campo' => $campo,
            'valor' => $valor,
            'mensaje' => $mensaje,
        ];
    }

    private function normalizarCliente(array $fila): array
    {
        $errores = [];
        $requeridos = array_values(array_diff($this->config['csv']['clientes']['required'], ['prima_total']));
        foreach ($requeridos as $campo) {
            if ($this->esNulo($fila[$campo] ?? '')) {
                $errores[] = ['campo' => $campo, 'mensaje' => "El campo {$campo} es obligatorio."];
            }
        }
        if (! empty($errores)) {
            return ['errores' => $errores];
        }

        $tipo = $this->tipoPersona($fila['tipo_persona'] ?? '');
        if (! $tipo) {
            $errores[] = ['campo' => 'tipo_persona', 'valor' => $fila['tipo_persona'] ?? '', 'mensaje' => 'Use Persona Física o Persona Moral.'];

            return ['errores' => $errores];
        }

        $estadoRaw = $this->esNulo($fila['estado'] ?? '') ? '' : trim((string) ($fila['estado'] ?? ''));
        if ($estadoRaw === '') {
            $this->advertir('estado', null, 'Sin estado de residencia; se usará Nacional con score 3.');
        }
        $estado = $this->estadoCatalogo($estadoRaw);
        if (! $estado) {
            $this->advertir('estado', $estadoRaw, 'El estado no está en el catálogo EBR; se usará el score Nacional (3.0).');
            $estado = $estadoRaw;
        }

        $producto = $this->productoCatalogo($fila['producto'] ?? '', $fila['grupo_producto'] ?? '', $fila['ramo'] ?? '');
        $actividadRaw = $this->esNulo($fila['ocupacion_giro'] ?? '') ? '' : $this->limpiarActividad((string) ($fila['ocupacion_giro'] ?? ''));
        if ($actividadRaw === '') {
            $this->advertir('ocupacion_giro', null, 'Sin actividad económica; se usará nivel de riesgo 2.');
        }
        $actividad = $this->actividadCatalogo($actividadRaw);
        if (! ($actividad['encontrado'] ?? true)) {
            $this->advertir('ocupacion_giro', $actividadRaw, 'La actividad no está en el catálogo; se usará nivel de riesgo 2.');
        }

        $categoriaRaw = $this->esNulo($fila['categoria_pld'] ?? '') ? '' : trim((string) ($fila['categoria_pld'] ?? ''));
        if ($categoriaRaw === '') {
            $this->advertir('categoria_pld', null, 'Sin categoría PLD; se usará Sin coincidencia en listas.');
            $categoriaRaw = 'Sin coincidencia en listas';
        }
        $categoria = $categoriaRaw;
        $categoriasValidas = array_keys($this->config['categorias_pld']);
        if (! in_array($this->normalizar($categoria), $categoriasValidas, true)) {
            $errores[] = [
                'campo' => 'categoria_pld',
                'valor' => $categoria,
                'mensaje' => 'Categoría PLD inválida. Use: '.implode(', ', array_keys($this->config['categorias_pld'])).'.',
            ];
        }
        $lista = $this->listaRiesgo($categoria);
        $agenteRaw = $this->esNulo($fila['agente'] ?? '') ? '' : trim((string) ($fila['agente'] ?? ''));
        $canal = $this->canal($fila['canal'] ?? '', $agenteRaw);

        $fechaEmision = $this->fecha($fila['fecha_emision'] ?? '');
        if (! $fechaEmision) {
            $errores[] = ['campo' => 'fecha_emision', 'valor' => $fila['fecha_emision'] ?? '', 'mensaje' => 'Fecha de emisión inválida.'];
        }

        $idOperacion = $this->esNulo($fila['id_operacion'] ?? '') ? null : (trim((string) ($fila['id_operacion'] ?? '')) ?: null);
        $folioEndoso = $this->esNulo($fila['folio_endoso'] ?? '') ? null : (trim((string) ($fila['folio_endoso'] ?? '')) ?: null);
        $tipoMovimiento = $this->esNulo($fila['tipo_movimiento'] ?? '') ? 'Emisión' : (trim((string) ($fila['tipo_movimiento'] ?? '')) ?: 'Emisión');
        $esEndoso = $folioEndoso !== null || str_contains($this->normalizar($tipoMovimiento), 'endoso');

        $primaRaw = (string) ($fila['prima_total'] ?? '');
        $primaVacia = $this->esNulo($primaRaw);
        if ($primaVacia) {
            $prima = 0.0;
            $this->advertir(
                'prima_total',
                $primaRaw,
                $esEndoso
                    ? 'Endoso sin prima_total; se registró monto 0.'
                    : 'Sin prima_total; se registró monto 0.'
            );
        } else {
            $prima = $this->numero($primaRaw);
        }

        $moneda = $this->moneda($fila['moneda'] ?? '');
        $tipoCambio = $this->tipoCambio($fila['tipo_cambio'] ?? '', $moneda);
        if ($tipoCambio <= 0) {
            $errores[] = ['campo' => 'tipo_cambio', 'valor' => $fila['tipo_cambio'] ?? '', 'mensaje' => 'El tipo de cambio debe ser mayor a cero.'];
        }

        $fechaLimite = $this->periodoFin ? $this->periodoFin->copy() : Carbon::now()->endOfDay();
        $fechaMinima = Carbon::create(1900, 1, 1)->startOfDay();

        $fechaNacimientoRaw = (string) ($fila['fecha_nacimiento'] ?? '');
        $fechaNacimiento = $this->fechaEnRango($fechaNacimientoRaw, $fechaMinima, $fechaLimite);
        $fechaNacimientoInvalida = ! $this->esNulo($fechaNacimientoRaw) && $fechaNacimiento === null;
        if ($fechaNacimientoInvalida) {
            $this->advertir('fecha_nacimiento', $fechaNacimientoRaw, 'Fecha de nacimiento inválida o fuera de rango; se usará null.');
        }

        $fechaConstitucionRaw = (string) ($fila['fecha_constitucion'] ?? '');
        $fechaConstitucion = $this->fechaEnRango($fechaConstitucionRaw, $fechaMinima, $fechaLimite);
        $fechaConstitucionInvalida = ! $this->esNulo($fechaConstitucionRaw) && $fechaConstitucion === null;
        if ($fechaConstitucionInvalida) {
            $this->advertir('fecha_constitucion', $fechaConstitucionRaw, 'Fecha de constitución inválida o fuera de rango; se usará null.');
        }

        $fechaRelacionRaw = (string) ($fila['fecha_primera_relacion'] ?? '');
        $fechaRelacion = $this->fechaEnRango($fechaRelacionRaw, $fechaMinima, $fechaLimite);
        $fechaRelacionInvalida = ! $this->esNulo($fechaRelacionRaw) && $fechaRelacion === null;
        if ($fechaRelacionInvalida) {
            $this->advertir('fecha_primera_relacion', $fechaRelacionRaw, 'Fecha de primera relación inválida o fuera de rango; se usará null.');
        }

        $edadRaw = $this->numeroONull($fila['edad'] ?? '');
        $edad = null;
        if (! $fechaNacimientoInvalida) {
            $edad = $this->aniosHasta($fechaNacimiento, $fechaLimite);
            if ($edad !== null && ($edad < 0 || $edad > 120)) {
                $edad = null;
            }
            if ($edad === null && $edadRaw !== null && $edadRaw >= 0 && $edadRaw <= 120) {
                $edad = (float) $edadRaw;
            }
        }
        if ($edadRaw !== null && ($edadRaw < 0 || $edadRaw > 120)) {
            $this->advertir('edad', $edadRaw, 'Edad fuera de rango; se recalculó desde la fecha de nacimiento o se usará null.');
        }

        $antiguedadEmpresaRaw = $this->numeroONull($fila['antiguedad_empresa_anios'] ?? '');
        $antiguedadEmpresa = null;
        if (! $fechaConstitucionInvalida) {
            $antiguedadEmpresa = $this->aniosHasta($fechaConstitucion, $fechaLimite);
            if ($antiguedadEmpresa !== null && ($antiguedadEmpresa < 0 || $antiguedadEmpresa > 200)) {
                $antiguedadEmpresa = null;
            }
            if ($antiguedadEmpresa === null && $antiguedadEmpresaRaw !== null && $antiguedadEmpresaRaw >= 0 && $antiguedadEmpresaRaw <= 200) {
                $antiguedadEmpresa = (float) $antiguedadEmpresaRaw;
            }
        }
        if ($antiguedadEmpresaRaw !== null && ($antiguedadEmpresaRaw < 0 || $antiguedadEmpresaRaw > 200)) {
            $this->advertir('antiguedad_empresa_anios', $antiguedadEmpresaRaw, 'Antigüedad de la empresa fuera de rango; se recalculó o se usará null.');
        }

        $antiguedadRelacionRaw = $this->numeroONull($fila['antiguedad_relacion_anios'] ?? '');
        $antiguedadRelacion = null;
        if (! $fechaRelacionInvalida) {
            $antiguedadRelacion = $this->aniosHasta($fechaRelacion, $fechaLimite);
            if ($antiguedadRelacion !== null && ($antiguedadRelacion < 0 || $antiguedadRelacion > 200)) {
                $antiguedadRelacion = null;
            }
            if ($antiguedadRelacion === null && $antiguedadRelacionRaw !== null && $antiguedadRelacionRaw >= 0 && $antiguedadRelacionRaw <= 200) {
                $antiguedadRelacion = (float) $antiguedadRelacionRaw;
            }
        }
        if ($antiguedadRelacionRaw !== null && ($antiguedadRelacionRaw < 0 || $antiguedadRelacionRaw > 200)) {
            $this->advertir('antiguedad_relacion_anios', $antiguedadRelacionRaw, 'Antigüedad de la relación fuera de rango; se recalculó o se usará null.');
        }

        $excluida = false;
        $motivo = null;
        if ($fechaEmision && ! $this->dentroPeriodo($fechaEmision)) {
            $excluida = true;
            $motivo = 'Fecha de emisión fuera del periodo del ejercicio';
            $this->advertir('fecha_emision', $fechaEmision, 'La póliza se excluye porque su fecha de emisión está fuera del periodo.');
        }
        if ($prima < 0) {
            $excluida = true;
            $motivo = 'Prima total negativa';
            $this->advertir('prima_total', $prima, 'La póliza se excluye porque la prima total es negativa.');
        }
        if (! empty($errores)) {
            return ['errores' => $errores];
        }

        $data = [
            'no_poliza' => trim((string) ($fila['no_poliza'] ?? '')),
            'id_operacion' => $idOperacion,
            'folio_endoso' => $folioEndoso,
            'tipo_movimiento' => $tipoMovimiento,
            'no_cliente' => trim((string) ($fila['no_cliente'] ?? '')),
            'nombre_cliente' => $this->esNulo($fila['nombre_cliente'] ?? '') ? null : (trim((string) ($fila['nombre_cliente'] ?? '')) ?: null),
            'tipo_persona' => $tipo,
            'rfc' => $this->esNulo($fila['rfc'] ?? '') ? null : (trim((string) ($fila['rfc'] ?? '')) ?: null),
            'curp' => $this->esNulo($fila['curp'] ?? '') ? null : (trim((string) ($fila['curp'] ?? '')) ?: null),
            'fecha_nacimiento' => $fechaNacimiento,
            'fecha_constitucion' => $fechaConstitucion,
            'fecha_primera_relacion' => $fechaRelacion,
            'edad' => $edad,
            'antiguedad_empresa_anios' => $antiguedadEmpresa,
            'antiguedad_relacion_anios' => $antiguedadRelacion,
            'ocupacion_giro' => $actividad['ocupacion_giro'],
            'actividad_nivel_riesgo' => $actividad['nivel_riesgo'],
            'actividad_agrupada' => $actividad['ocupacion_agrupada'],
            'estado' => $estado,
            'pais_nacionalidad' => $this->esNulo($fila['pais_nacionalidad'] ?? '') ? 'México' : (trim((string) ($fila['pais_nacionalidad'] ?? '')) ?: 'México'),
            'pais_operacion' => $this->esNulo($fila['pais_operacion'] ?? '') ? 'México' : (trim((string) ($fila['pais_operacion'] ?? '')) ?: 'México'),
            'categoria_pld' => $categoria,
            'lista_riesgo' => $lista,
            'canal' => $canal,
            'agente' => $this->esNulo($fila['agente'] ?? '') ? null : (trim((string) ($fila['agente'] ?? '')) ?: null),
            'ramo' => $this->esNulo($fila['ramo'] ?? '') ? null : (trim((string) ($fila['ramo'] ?? '')) ?: null),
            'producto' => $this->esNulo($fila['producto'] ?? '') ? null : (trim((string) ($fila['producto'] ?? '')) ?: null),
            'grupo_producto' => $producto['grupo'],
            'prima_total' => $prima,
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'fecha_emision' => $fechaEmision,
            'num_operaciones_entrada' => max(1, (int) ($this->numeroONull($fila['num_operaciones_entrada'] ?? '') ?? 1)),
            'excluida' => $excluida,
            'motivo_exclusion' => $motivo,
        ];

        $data['clave_operacion'] = hash('sha256', implode('|', [
            $data['no_poliza'],
            $data['no_cliente'],
            $idOperacion ?? '',
            $folioEndoso ?? '',
            $tipoMovimiento,
            (string) ($fechaEmision ?? ''),
            (string) $prima,
        ]));
        $data['row_hash'] = hash('sha256', json_encode($data));

        return ['data' => $data];
    }

    private function normalizarSiniestro(array $fila): array
    {
        $fechaRaw = trim((string) ($fila['fecha_pago'] ?? ''));
        if ($this->esNulo($fechaRaw)) {
            $this->advertir('fecha_pago', $fechaRaw, 'El siniestro no tiene fecha de pago; se omitió.');

            return ['omitida' => true];
        }

        $errores = [];
        foreach ($this->config['csv']['siniestros']['required'] as $campo) {
            if ($this->esNulo($fila[$campo] ?? '')) {
                $errores[] = ['campo' => $campo, 'mensaje' => "El campo {$campo} es obligatorio."];
            }
        }
        $fecha = $this->fecha($fechaRaw);
        if (! $fecha) {
            $errores[] = ['campo' => 'fecha_pago', 'valor' => $fechaRaw, 'mensaje' => 'Fecha de pago inválida.'];
        }

        $monto = $this->numero($fila['monto_pagado'] ?? 0);
        if ($monto < 0) {
            $errores[] = ['campo' => 'monto_pagado', 'valor' => $fila['monto_pagado'] ?? '', 'mensaje' => 'El monto pagado no puede ser negativo.'];
        }

        $moneda = $this->moneda($fila['moneda'] ?? '');
        $tipoCambio = $this->tipoCambio($fila['tipo_cambio'] ?? '', $moneda);
        if ($tipoCambio <= 0) {
            $errores[] = ['campo' => 'tipo_cambio', 'valor' => $fila['tipo_cambio'] ?? '', 'mensaje' => 'El tipo de cambio debe ser mayor a cero.'];
        }

        if (! empty($errores)) {
            return ['errores' => $errores];
        }

        $noPoliza = $this->esNulo($fila['no_poliza'] ?? '') ? '' : trim((string) ($fila['no_poliza'] ?? ''));
        if ($this->polizasEjercicio !== null && $noPoliza !== '' && ! isset($this->polizasEjercicio[$noPoliza])) {
            $this->advertir('no_poliza', $noPoliza, 'La póliza no tiene operación en el periodo; el siniestro se omitió.');

            return ['omitida' => true];
        }

        $data = [
            'no_siniestro' => trim((string) ($fila['no_siniestro'] ?? '')),
            'no_poliza' => $noPoliza,
            'no_cliente' => $this->esNulo($fila['no_cliente'] ?? '') ? null : (trim((string) ($fila['no_cliente'] ?? '')) ?: null),
            'fecha_pago' => $fecha,
            'monto_pagado' => $monto,
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'num_operaciones_salida' => max(1, (int) ($this->numeroONull($fila['num_operaciones_salida'] ?? '') ?? 1)),
            'estado' => $this->esNulo($fila['estado'] ?? '') ? null : (trim((string) ($fila['estado'] ?? '')) ?: null),
            'producto' => $this->esNulo($fila['producto'] ?? '') ? null : (trim((string) ($fila['producto'] ?? '')) ?: null),
            'grupo_producto' => $this->esNulo($fila['grupo_producto'] ?? '') ? null : (trim((string) ($fila['grupo_producto'] ?? '')) ?: null),
            'canal' => $this->esNulo($fila['canal'] ?? '') ? null : (trim((string) ($fila['canal'] ?? '')) ?: null),
        ];
        $data['row_hash'] = hash('sha256', json_encode($data));

        return ['data' => $data];
    }

    private function normalizarAlerta(array $fila): array
    {
        $errores = [];
        foreach ($this->config['csv']['alertas']['required'] as $campo) {
            if ($this->esNulo($fila[$campo] ?? '')) {
                $errores[] = ['campo' => $campo, 'mensaje' => "El campo {$campo} es obligatorio."];
            }
        }
        $fecha = $this->fecha($fila['fecha_deteccion'] ?? '');
        if (! $fecha) {
            $errores[] = ['campo' => 'fecha_deteccion', 'valor' => $fila['fecha_deteccion'] ?? '', 'mensaje' => 'Fecha de detección inválida.'];
        }
        if (! empty($errores)) {
            return ['errores' => $errores];
        }

        $patron = trim((string) ($fila['patron'] ?? ''));
        $patronesValidos = collect($this->config['transacciones']['indicadores'])
            ->flatMap(fn ($indicador) => $indicador['patrones'] ?? [$indicador['patron'] ?? ''])
            ->map(fn ($p) => $this->normalizar($p))
            ->filter()
            ->all();
        if (! in_array($this->normalizar($patron), $patronesValidos, true)) {
            $this->advertir('patron', $patron, 'El patrón no está mapeado en Transacciones; la alerta se guarda solo para auditoría.');
        }

        $estatus = $this->esNulo($fila['estatus'] ?? '') ? '' : trim((string) ($fila['estatus'] ?? ''));
        $procedioRaw = $this->esNulo($fila['procedio'] ?? '') ? '' : trim((string) ($fila['procedio'] ?? ''));
        if ($estatus === '' && $procedioRaw === '') {
            $errores[] = ['campo' => 'procedio', 'valor' => '', 'mensaje' => 'Indique procedio o estatus de la alerta.'];
        }
        if (! empty($errores)) {
            return ['errores' => $errores];
        }

        $estatusProcedentes = array_map(fn ($e) => $this->normalizar($e), $this->config['transacciones']['estatus_procedentes'] ?? []);
        $procedio = $procedioRaw !== '' ? $this->booleano($procedioRaw) : in_array($this->normalizar($estatus), $estatusProcedentes, true);

        if ($estatus !== '') {
            $porEstatus = in_array($this->normalizar($estatus), $estatusProcedentes, true);
            if ($procedioRaw !== '' && $procedio !== $porEstatus) {
                $this->advertir('procedio', $procedioRaw, 'El procedio no coincide con el estatus; se usará el estatus.');
            }
            $procedio = $porEstatus;
        }

        $noPoliza = $this->esNulo($fila['no_poliza'] ?? '') ? '' : trim((string) ($fila['no_poliza'] ?? ''));
        $noCliente = $this->esNulo($fila['no_cliente'] ?? '') ? '' : trim((string) ($fila['no_cliente'] ?? ''));
        $idOperacion = $this->esNulo($fila['id_operacion'] ?? '') ? '' : trim((string) ($fila['id_operacion'] ?? ''));

        if ($this->polizasEjercicio !== null && $noPoliza !== '' && ! isset($this->polizasEjercicio[$noPoliza])) {
            $this->advertir('no_poliza', $noPoliza, 'La póliza de la alerta no tiene operación en el periodo; se conserva la referencia y no se vinculará por póliza.');
        }
        if ($this->clientesEjercicio !== null && $noCliente !== '' && ! isset($this->clientesEjercicio[$noCliente])) {
            $this->advertir('no_cliente', $noCliente, 'El cliente de la alerta no tiene operación en el periodo; se conserva la referencia y no se vinculará por cliente.');
        }
        if ($noPoliza === '' && $noCliente === '' && $idOperacion === '') {
            $this->advertir('vinculo', null, 'La alerta no está vinculada a una operación, póliza o cliente; se guarda para auditoría y no participa en el cálculo.');
        }

        $moneda = $this->moneda($fila['moneda'] ?? '');
        $tipoCambio = $this->tipoCambio($fila['tipo_cambio'] ?? '', $moneda);
        if ($tipoCambio <= 0) {
            $errores[] = ['campo' => 'tipo_cambio', 'valor' => $fila['tipo_cambio'] ?? '', 'mensaje' => 'El tipo de cambio debe ser mayor a cero.'];
        }
        if (! empty($errores)) {
            return ['errores' => $errores];
        }

        $data = [
            'patron' => $patron,
            'no_cliente' => $noCliente ?: null,
            'no_poliza' => $noPoliza ?: null,
            'id_operacion' => $idOperacion ?: null,
            'fecha_deteccion' => $fecha,
            'monto' => $this->numero($fila['monto'] ?? 0),
            'moneda' => $moneda,
            'tipo_cambio' => $tipoCambio,
            'instrumento_monetario' => $this->esNulo($fila['instrumento_monetario'] ?? '') ? null : (trim((string) ($fila['instrumento_monetario'] ?? '')) ?: null),
            'agente' => $this->esNulo($fila['agente'] ?? '') ? null : (trim((string) ($fila['agente'] ?? '')) ?: null),
            'estatus' => $estatus ?: null,
            'procedio' => $procedio,
            'num_operaciones' => max(1, (int) ($this->numeroONull($fila['num_operaciones'] ?? '') ?? 1)),
            'descripcion' => $this->esNulo($fila['descripcion'] ?? '') ? null : (trim((string) ($fila['descripcion'] ?? '')) ?: null),
            'razones' => $this->esNulo($fila['razones'] ?? '') ? null : (trim((string) ($fila['razones'] ?? '')) ?: null),
        ];
        $data['row_hash'] = hash('sha256', json_encode($data));

        return ['data' => $data];
    }

    private function guardarClientes(EbrEjercicio $ejercicio, array $rows): void
    {
        EbrPoliza::where('ejercicio_id', $ejercicio->id)->delete();
        $vistos = [];
        $data = [];
        $now = now();
        foreach ($rows as $row) {
            if (isset($vistos[$row['clave_operacion']])) {
                continue;
            }
            $vistos[$row['clave_operacion']] = true;
            $row['ejercicio_id'] = $ejercicio->id;
            $row['excluida'] = (bool) $row['excluida'];
            if (! empty($row['fecha_emision'])) {
                $fecha = Carbon::parse($row['fecha_emision'])->startOfDay();
                if ($fecha->lt($ejercicio->fecha_inicio) || $fecha->gt($ejercicio->fecha_fin)) {
                    $row['excluida'] = true;
                    $row['motivo_exclusion'] = 'Fecha de emisión fuera del periodo del ejercicio';
                }
            }
            $row['excluida'] = $row['excluida'] ? 1 : 0;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $data[] = $row;
        }
        foreach (array_chunk($data, 500) as $chunk) {
            EbrPoliza::insert($chunk);
        }
    }

    private function guardarSiniestros(EbrEjercicio $ejercicio, array $rows): void
    {
        EbrSiniestro::where('ejercicio_id', $ejercicio->id)->delete();
        $data = [];
        $now = now();
        foreach ($rows as $row) {
            $row['ejercicio_id'] = $ejercicio->id;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $data[] = $row;
        }
        foreach (array_chunk($data, 500) as $chunk) {
            EbrSiniestro::insert($chunk);
        }
    }

    private function guardarAlertas(EbrEjercicio $ejercicio, array $rows): void
    {
        EbrAlerta::where('ejercicio_id', $ejercicio->id)->delete();
        $vistos = [];
        $data = [];
        $now = now();
        foreach ($rows as $row) {
            if (isset($vistos[$row['row_hash']])) {
                continue;
            }
            $vistos[$row['row_hash']] = true;
            $row['ejercicio_id'] = $ejercicio->id;
            $row['procedio'] = $row['procedio'] ? 1 : 0;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $data[] = $row;
        }
        foreach (array_chunk($data, 500) as $chunk) {
            EbrAlerta::insert($chunk);
        }
    }

    private function resumen(string $tipo, array $rows, int $errores): array
    {
        $resumen = ['tipo' => $tipo, 'filas' => count($rows), 'errores' => $errores];
        if ($tipo === 'clientes') {
            $resumen['polizas'] = count(array_unique(array_column($rows, 'no_poliza')));
            $resumen['operaciones'] = array_sum(array_map(fn ($r) => (int) ($r['num_operaciones_entrada'] ?? 1), $rows));
            $resumen['clientes'] = count(array_unique(array_column($rows, 'no_cliente')));
            $resumen['primas'] = round(array_sum(array_map(fn ($r) => (float) $r['prima_total'] * (float) ($r['tipo_cambio'] ?: 1), $rows)), 2);
            $resumen['excluidas'] = count(array_filter($rows, fn ($r) => (bool) $r['excluida']));
        } elseif ($tipo === 'siniestros') {
            $resumen['monto'] = round(array_sum(array_map(fn ($r) => (float) $r['monto_pagado'] * (float) ($r['tipo_cambio'] ?: 1), $rows)), 2);
        } elseif ($tipo === 'alertas') {
            $procedentes = array_filter($rows, fn ($r) => (bool) $r['procedio']);
            $resumen['procedentes'] = count($procedentes);
            $resumen['vinculadas'] = count(array_filter($rows, fn ($r) => ! empty($r['no_poliza']) || ! empty($r['no_cliente'])));
            $resumen['sin_vinculo'] = count($rows) - $resumen['vinculadas'];
            $indicadorRelevante = collect($this->config['transacciones']['indicadores'])->firstWhere('codigo', '5.4');
            $patronesRelevantes = $indicadorRelevante['patrones'] ?? ['Monto', 'Acumulado Efectivo'];
            $patronesRelevantes = array_map(fn ($p) => $this->normalizar($p), $patronesRelevantes);
            $resumen['relevantes'] = count(array_filter(
                $procedentes,
                fn ($r) => in_array($this->normalizar($r['patron'] ?? ''), $patronesRelevantes, true)
            ));
        }

        return $resumen;
    }

    private function detectarDelimitador(string $linea): string
    {
        $opciones = [',' => substr_count($linea, ','), ';' => substr_count($linea, ';'), "\t" => substr_count($linea, "\t")];
        arsort($opciones);

        return (string) array_key_first($opciones) ?: ',';
    }

    private function normalizarHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
        $header = $this->normalizar($header);
        $header = str_replace([' ', '-', '.', '/'], '_', $header);
        $header = preg_replace('/_+/', '_', $header) ?? $header;

        $alias = [
            'poliza' => 'no_poliza', 'nopoliza' => 'no_poliza', 'numero_poliza' => 'no_poliza', 'no_de_poliza' => 'no_poliza',
            'cliente' => 'no_cliente', 'nocliente' => 'no_cliente', 'numero_cliente' => 'no_cliente', 'no_de_cliente' => 'no_cliente',
            'nombre' => 'nombre_cliente', 'razon_social' => 'nombre_cliente',
            'tipo_de_persona' => 'tipo_persona', 'tipopersona' => 'tipo_persona', 'rfc_cliente' => 'rfc', 'curp_cliente' => 'curp',
            'fecha_de_nacimiento' => 'fecha_nacimiento', 'fechanacimiento' => 'fecha_nacimiento',
            'fecha_de_constitucion' => 'fecha_constitucion', 'fechaconstitucion' => 'fecha_constitucion',
            'primera_relacion' => 'fecha_primera_relacion', 'fecha_relacion' => 'fecha_primera_relacion',
            'primerarelacion' => 'fecha_primera_relacion', 'primerarelacioncomercial' => 'fecha_primera_relacion',
            'ocupacion' => 'ocupacion_giro', 'giro' => 'ocupacion_giro', 'ocupaciongiro' => 'ocupacion_giro',
            'actividad_economica' => 'ocupacion_giro',
            'estado_cliente' => 'estado', 'domicilio_estado' => 'estado',
            'pais' => 'pais_nacionalidad', 'nacionalidad' => 'pais_nacionalidad',
            'categoria' => 'categoria_pld', 'lista' => 'categoria_pld', 'categoriapld' => 'categoria_pld',
            'categoria_pld_cliente' => 'categoria_pld',
            'agente_nombre' => 'agente', 'canal_distribucion' => 'canal',
            'producto_consolidado' => 'grupo_producto', 'grupo' => 'grupo_producto',
            'prima' => 'prima_total', 'prima_neta' => 'prima_total', 'prima_emitida' => 'prima_total',
            'fecha_de_emision' => 'fecha_emision', 'fecha_emision_poliza' => 'fecha_emision',
            'operaciones_entrada' => 'num_operaciones_entrada', 'num_ops_entrada' => 'num_operaciones_entrada',
            'siniestro' => 'no_siniestro', 'numero_siniestro' => 'no_siniestro', 'no_de_siniestro' => 'no_siniestro',
            'n_de_siniestro' => 'no_siniestro', 'n_siniestro' => 'no_siniestro',
            'fecha_de_pago' => 'fecha_pago', 'monto_pagado_siniestro' => 'monto_pagado',
            'estimacion' => 'monto_pagado', 'monto_estimado' => 'monto_pagado',
            'operaciones_salida' => 'num_operaciones_salida', 'num_ops_salida' => 'num_operaciones_salida',
            'patron_alerta' => 'patron', 'fecha_de_deteccion' => 'fecha_deteccion',
            'fechadeteccion' => 'fecha_deteccion', 'procedio_alerta' => 'procedio',
            'instrumento' => 'instrumento_monetario', 'instrumentomonetario' => 'instrumento_monetario',
            'razones_alerta' => 'razones',
        ];

        return $alias[$header] ?? $header;
    }

    private function tipoPersona(string $valor): ?string
    {
        $valor = $this->normalizar($valor);
        if (str_contains($valor, 'fisica') || $valor === 'pf' || str_contains($valor, 'física')) {
            return 'Persona Física';
        }
        if (str_contains($valor, 'moral') || $valor === 'pm') {
            return 'Persona Moral';
        }
        if (str_contains($valor, 'fideicomiso')) {
            return 'Persona Moral';
        }

        return null;
    }

    private function estadoCatalogo(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '' || $this->normalizar($valor) === 'null') {
            return 'Nacional';
        }
        foreach ($this->estados as $estado => $data) {
            if ($this->normalizar($estado) === $this->normalizar($valor)) {
                return $estado;
            }
        }
        $alias = ['cdmx' => 'Ciudad de México', 'df' => 'Ciudad de México', 'estado de mexico' => 'México', 'edomex' => 'México'];
        $clave = $this->normalizar($valor);
        if (isset($alias[$clave])) {
            return $alias[$clave];
        }

        return null;
    }

    private function productoCatalogo(string $producto, string $grupo, string $ramo): array
    {
        foreach ($this->productos as $nombre => $data) {
            if ($this->normalizar($nombre) === $this->normalizar($producto)) {
                return ['grupo' => $data['grupo'] === 'Otros' ? 'Otro' : $data['grupo'], 'producto' => $nombre];
            }
        }
        $grupo = $this->normalizar($grupo);
        foreach ($this->config['productos']['grupos'] as $permitido) {
            if ($this->normalizar($permitido) === $grupo) {
                return ['grupo' => $permitido, 'producto' => $producto];
            }
        }
        $ramo = $this->normalizar($ramo);
        if ($ramo === $this->normalizar('Agrícola')) {
            return ['grupo' => 'Agrícola', 'producto' => $producto];
        }
        if ($ramo === $this->normalizar('Ganadero')) {
            return ['grupo' => 'Ganadero', 'producto' => $producto];
        }
        if (str_contains($ramo, 'reaseguro')) {
            return ['grupo' => 'Reaseguro Tomado', 'producto' => $producto];
        }

        return ['grupo' => 'Otro', 'producto' => $producto];
    }

    private function actividadCatalogo(string $valor): array
    {
        $valor = $this->limpiarActividad($valor);
        $normalizado = $this->normalizar($valor);

        if (in_array($normalizado, ['no clasificada', 'no clasificado'], true)) {
            return [
                'ocupacion_giro' => 'No Clasificada',
                'nivel_riesgo' => 2,
                'ocupacion_agrupada' => 'No definido',
                'encontrado' => true,
            ];
        }

        foreach ($this->actividades as $ocupacion => $data) {
            if ($this->normalizar($ocupacion) === $normalizado) {
                return [
                    'ocupacion_giro' => $ocupacion,
                    'nivel_riesgo' => (int) $data['nivel_riesgo'],
                    'ocupacion_agrupada' => $data['ocupacion_agrupada'],
                    'encontrado' => true,
                ];
            }
        }

        return [
            'ocupacion_giro' => $valor ?: 'No definido',
            'nivel_riesgo' => 2,
            'ocupacion_agrupada' => 'No definido',
            'encontrado' => false,
        ];
    }

    private function limpiarActividad(string $valor): string
    {
        $valor = trim($valor);
        $valor = preg_replace('/\s*\(?\s*uso\s+exclusivo\s+cnbv\s*\)?\s*/iu', '', $valor) ?? $valor;

        return trim($valor);
    }

    private function listaRiesgo(string $categoria): int
    {
        $categoria = $this->normalizar($categoria);

        return (int) ($this->config['categorias_pld'][$categoria] ?? 1);
    }

    private function canal(string $canal, string $agente): string
    {
        $agente = trim($agente);
        if ($agente !== '' && ! str_starts_with($agente, '0')) {
            return 'Agente';
        }

        return $this->normalizar($canal) === 'agente' ? 'Agente' : 'Interno';
    }

    private function booleano(string $valor): bool
    {
        $valor = $this->normalizar($valor);

        return in_array($valor, ['1', 'si', 'sí', 'true', 'verdadero', 'x', 'yes'], true);
    }

    private function moneda(string $valor): string
    {
        $normalizado = $this->normalizar($valor);

        if ($normalizado === '' || in_array($normalizado, [
            'mxn', 'pesos mexicanos', 'peso mexicano', 'pesos', 'moneda nacional', 'm.n.', 'm.n',
            'pesos m.n.', 'pesos m.n',
        ], true)) {
            return 'MXN';
        }

        if (in_array($normalizado, [
            'usd', 'dolares americanos', 'dólares americanos', 'dolar americano', 'dólares americanos',
            'dolares', 'dólares', 'dolar', 'dólar', 'usd (dólares)', 'usd (dolares)',
        ], true)) {
            return 'USD';
        }

        return strtoupper(trim($valor)) ?: 'MXN';
    }

    private function tipoCambio(string $valor, string $moneda): float
    {
        if ($moneda === 'MXN') {
            $tipoCambio = $this->numeroONull($valor);
            if ($tipoCambio !== null && (float) $tipoCambio !== 1.0) {
                $this->advertir('tipo_cambio', $valor, 'Para moneda MXN el tipo de cambio debe ser 1; se ajustó a 1.');
            }

            return 1.0;
        }

        if ($this->esNulo($valor)) {
            $this->advertir('tipo_cambio', $valor, 'No se informó tipo de cambio para moneda extranjera; se usó 1.0.');

            return 1.0;
        }

        $tipoCambio = $this->numero($valor);

        return $tipoCambio > 0 ? $tipoCambio : 0.0;
    }

    private function esNulo(string|int|float|null $valor): bool
    {
        $normalizado = $this->normalizar((string) $valor);

        return $normalizado === '' || in_array($normalizado, [
            'null', 'n/a', 'na', '-', 'sin dato', 'sin fecha', 'no definido',
        ], true);
    }

    private function numero(string|int|float|null $valor): float
    {
        if ($this->esNulo($valor)) {
            return 0.0;
        }
        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0.0;
        }
        $valor = str_replace(['$', ' '], '', $valor);
        $tieneComa = str_contains($valor, ',');
        $tienePunto = str_contains($valor, '.');
        if ($tieneComa && $tienePunto) {
            if (strrpos($valor, ',') > strrpos($valor, '.')) {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            } else {
                $valor = str_replace(',', '', $valor);
            }
        } elseif ($tieneComa) {
            $valor = substr_count($valor, ',') === 1 ? str_replace(',', '.', $valor) : str_replace(',', '', $valor);
        }

        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    private function numeroONull(string|int|float|null $valor): ?float
    {
        if ($this->esNulo($valor)) {
            return null;
        }

        return $this->numero($valor);
    }

    private function fechaEnRango(string $valor, Carbon $min, Carbon $max): ?string
    {
        $fecha = $this->fecha($valor);
        if (! $fecha) {
            return null;
        }

        $carbon = Carbon::parse($fecha);
        if ($carbon->lt($min) || $carbon->gt($max)) {
            return null;
        }

        return $fecha;
    }

    private function aniosHasta(?string $fecha, Carbon $hasta): ?float
    {
        if (! $fecha) {
            return null;
        }

        try {
            return (float) Carbon::parse($fecha)->diffInYears($hasta);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fecha(string $valor): ?string
    {
        if ($this->esNulo($valor)) {
            return null;
        }
        $valor = trim($valor);
        if (is_numeric($valor) && (float) $valor > 20000 && (float) $valor < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $valor)->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'd/m/y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor);
                if ($fecha !== false) {
                    return $fecha->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }
        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function dentroPeriodo(string $fecha): bool
    {
        if (! $this->periodoInicio || ! $this->periodoFin) {
            return true;
        }
        $fecha = Carbon::parse($fecha);

        return $fecha->gte($this->periodoInicio) && $fecha->lte($this->periodoFin);
    }

    private function normalizar(?string $texto): string
    {
        $texto = trim((string) $texto);
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        $resultado = preg_replace('/\s+/', ' ', $texto);

        return $resultado === null ? '' : $resultado;
    }
}
