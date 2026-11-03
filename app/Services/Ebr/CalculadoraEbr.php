<?php

namespace App\Services\Ebr;

use App\Models\Ebr\EbrCatActividad;
use App\Models\Ebr\EbrCatEstado;
use App\Models\Ebr\EbrCatPais;
use App\Models\Ebr\EbrCatProducto;
use App\Models\Ebr\EbrEjercicio;
use Carbon\Carbon;

class CalculadoraEbr
{
    private array $config;

    private array $advertencias = [];

    private array $estados = [];

    private array $productos = [];

    private array $actividades = [];

    private array $paises = [];

    private Carbon $fechaCorte;

    public function __construct()
    {
        $this->config = config('ebr');
    }

    public function calcular(EbrEjercicio $ejercicio, ?array $resultadoAnterior = null): array
    {
        $this->advertencias = [];

        if ($ejercicio->fecha_inicio && $ejercicio->fecha_fin) {
            $inicio = $ejercicio->fecha_inicio->copy()->startOfDay();
            $fin = $ejercicio->fecha_fin->copy()->startOfDay();

            if (! $inicio->isSameDay($inicio->copy()->startOfYear())
                || ! $fin->isSameDay($fin->copy()->endOfYear()->startOfDay())) {
                $this->advertencia('El periodo del ejercicio no cubre doce meses completos; el informe anual requiere información del 1 de enero al 31 de diciembre.');
            }
        }

        $this->fechaCorte = $ejercicio->fecha_fin
            ? Carbon::parse($ejercicio->fecha_fin)->endOfDay()
            : Carbon::create((int) $ejercicio->anio, 12, 31)->endOfDay();
        $this->cargarCatalogos();

        $polizas = $ejercicio->polizas()->get()->map(fn ($p) => $p->toArray())->all();
        $siniestrosImportados = $ejercicio->siniestros()->get()->map(fn ($s) => $s->toArray())->all();
        $alertasImportadas = $ejercicio->alertas()->get()->map(fn ($a) => $a->toArray())->all();

        $incluidas = array_values(array_filter($polizas, fn (array $p) => ! (bool) ($p['excluida'] ?? false)));
        $siniestros = array_values(array_filter(
            array_map(fn (array $s) => $this->enriquecerSiniestro($s, $incluidas), $siniestrosImportados),
            fn (array $s) => (bool) ($s['__incluida'] ?? false)
        ));
        $alertas = array_values(array_filter(
            array_map(fn (array $a) => $this->vincularAlerta($this->normalizarAlerta($a), $incluidas), $alertasImportadas),
            fn (array $a) => (bool) ($a['__vinculada'] ?? false)
        ));

        $mitigantes = $this->normalizarMitigantes($ejercicio->mitigantes);
        if (count($incluidas) === 0) {
            $this->advertencia('No se encontraron pólizas con FechaEmision dentro del periodo del ejercicio; no es posible calcular el EBR.');
        }
        if (count($siniestrosImportados) === 0) {
            $this->advertencia('No se importaron siniestros; las operaciones de salida se calcularon en cero.');
        } elseif (count($siniestros) < count($siniestrosImportados)) {
            $excluidos = count($siniestrosImportados) - count($siniestros);
            $this->advertencia("{$excluidos} siniestro(s) no tienen una póliza con operación en el periodo; no se incluyeron.");
        }
        if (count($alertasImportadas) === 0) {
            $this->advertencia('No se importaron alertas; el elemento Transacciones se calculó en cero.');
        } elseif (count($alertas) < count($alertasImportadas)) {
            $excluidas = count($alertasImportadas) - count($alertas);
            $this->advertencia("{$excluidas} alerta(s) no están vinculadas a una póliza o cliente del periodo; no se incluyeron.");
        }

        $alertasProcedentes = array_values(array_filter($alertas, fn ($a) => (bool) ($a['procedio'] ?? false)));
        if (count($alertas) > 0 && count($alertasProcedentes) === 0) {
            $this->advertencia('No hay alertas procedentes vinculadas al periodo; el elemento Transacciones se calculó en cero.');
        }

        $periodoInicio = $ejercicio->fecha_inicio
            ? Carbon::parse($ejercicio->fecha_inicio)->startOfDay()
            : Carbon::create((int) $ejercicio->anio, 1, 1)->startOfDay();
        $periodoFin = $ejercicio->fecha_fin
            ? Carbon::parse($ejercicio->fecha_fin)->endOfDay()
            : Carbon::create((int) $ejercicio->anio, 12, 31)->endOfDay();
        $enPeriodo = function (array $alerta) use ($periodoInicio, $periodoFin): bool {
            if (empty($alerta['fecha_deteccion'])) {
                return false;
            }

            try {
                $fecha = Carbon::parse($alerta['fecha_deteccion']);

                return $fecha->gte($periodoInicio) && $fecha->lte($periodoFin);
            } catch (\Throwable) {
                return false;
            }
        };

        $alertasPeriodo = array_values(array_filter($alertasImportadas, $enPeriodo));
        $alertasProcedentesPeriodo = array_values(array_filter($alertasPeriodo, fn ($a) => (bool) ($a['procedio'] ?? false)));
        $indicadorRelevante = collect($this->config['transacciones']['indicadores'])->firstWhere('codigo', '5.4');
        $patronesRelevantes = $indicadorRelevante['patrones'] ?? ['Monto', 'Acumulado Efectivo'];
        $patronesRelevantes = array_map(fn ($p) => $this->normalizar($p), $patronesRelevantes);
        $alertasRelevantesPeriodo = array_values(array_filter(
            $alertasProcedentesPeriodo,
            fn ($a) => in_array($this->normalizar($a['patron'] ?? ''), $patronesRelevantes, true)
        ));
        $alertasRelevantes = array_values(array_filter(
            $alertasProcedentes,
            fn ($a) => in_array($this->normalizar($a['patron'] ?? ''), $patronesRelevantes, true)
        ));

        $productos = $this->calcularProductos($incluidas, $siniestros, $mitigantes);
        $clientes = $this->calcularClientes($incluidas, $siniestros, $mitigantes);
        $geografia = $this->calcularGeografia($incluidas, $siniestros, $mitigantes);
        $canales = $this->calcularCanales($incluidas, $siniestros, $mitigantes);
        $transacciones = $this->calcularTransacciones($incluidas, $alertas, $mitigantes);

        $institucion = $this->calcularInstitucion(compact('productos', 'clientes', 'geografia', 'canales', 'transacciones'), $mitigantes);
        $comparativo = $this->comparar($resultadoAnterior, compact('productos', 'clientes', 'geografia', 'canales', 'transacciones', 'institucion'));

        $resultados = [
            'anio' => (int) $ejercicio->anio,
            'periodo' => [
                'inicio' => $ejercicio->fecha_inicio?->format('Y-m-d'),
                'fin' => $ejercicio->fecha_fin?->format('Y-m-d'),
            ],
            'version_metodologia' => $ejercicio->version_metodologia,
            'resumen_datos' => [
                'polizas' => count($this->polizasUnicas($incluidas)),
                'clientes' => count($this->clientesUnicos($incluidas)),
                'operaciones_entrada' => array_sum(array_map(fn ($p) => (int) ($p['num_operaciones_entrada'] ?? 1), $incluidas)),
                'monto_entrada' => round(array_sum(array_map(fn ($p) => $this->montoMxn($p, 'prima_total'), $incluidas)), 2),
                'siniestros' => count($siniestros),
                'siniestros_importados' => count($siniestrosImportados),
                'operaciones_salida' => array_sum(array_map(fn ($s) => (int) ($s['num_operaciones_salida'] ?? 1), $siniestros)),
                'monto_salida' => round(array_sum(array_map(fn ($s) => $this->montoMxn($s, 'monto_pagado'), $siniestros)), 2),
                'alertas' => count($alertasPeriodo),
                'alertas_importadas' => count($alertasImportadas),
                'alertas_vinculadas' => count($alertas),
                'alertas_procedentes' => count($alertasProcedentesPeriodo),
                'alertas_procedentes_vinculadas' => count($alertasProcedentes),
                'alertas_relevantes' => count($alertasRelevantesPeriodo),
                'alertas_relevantes_vinculadas' => count($alertasRelevantes),
            ],
            'elementos' => [
                'productos' => $productos,
                'clientes' => $clientes,
                'geografia' => $geografia,
                'canales' => $canales,
                'transacciones' => $transacciones,
            ],
            'institucion' => $institucion,
            'comparativo' => $comparativo,
            'advertencias' => array_values(array_unique($this->advertencias)),
        ];

        $totales = [
            'anio' => (int) $ejercicio->anio,
            'inherente' => $institucion['inherente'],
            'residual' => $institucion['residual'],
            'residual_final' => $institucion['residual_final'],
            'nivel' => $institucion['nivel'],
        ];

        return ['resultados' => $resultados, 'totales' => $totales, 'advertencias' => $resultados['advertencias']];
    }

    private function calcularProductos(array $polizas, array $siniestros, array $mitigantes): array
    {
        $grupos = $this->config['productos']['grupos'];
        $base = [];
        foreach ($grupos as $grupo) {
            $base[$grupo] = ['polizas' => [], 'ops_in' => 0, 'ops_out' => 0, 'monto_in' => 0.0, 'monto_out' => 0.0];
        }

        $totalPolizas = count($this->polizasUnicas($polizas));
        $totalOpsIn = array_sum(array_map(fn ($p) => (int) ($p['num_operaciones_entrada'] ?? 1), $polizas));
        $totalMontoIn = array_sum(array_map(fn ($p) => $this->montoMxn($p, 'prima_total'), $polizas));
        $totalOpsOut = array_sum(array_map(fn ($s) => (int) ($s['num_operaciones_salida'] ?? 1), $siniestros));
        $totalMontoOut = array_sum(array_map(fn ($s) => $this->montoMxn($s, 'monto_pagado'), $siniestros));

        foreach ($polizas as $poliza) {
            $grupo = $this->grupoProducto($poliza);
            $base[$grupo]['polizas'][(string) ($poliza['no_poliza'] ?? '')] = true;
            $base[$grupo]['ops_in'] += (int) ($poliza['num_operaciones_entrada'] ?? 1);
            $base[$grupo]['monto_in'] += $this->montoMxn($poliza, 'prima_total');
        }
        foreach ($grupos as $grupo) {
            $base[$grupo]['contratos'] = count($base[$grupo]['polizas']);
            unset($base[$grupo]['polizas']);
        }

        foreach ($siniestros as $siniestro) {
            $grupo = $siniestro['grupo_producto'] ?? 'Otro';
            if (! isset($base[$grupo])) {
                $grupo = 'Otro';
            }
            $base[$grupo]['ops_out'] += (int) ($siniestro['num_operaciones_salida'] ?? 1);
            $base[$grupo]['monto_out'] += $this->montoMxn($siniestro, 'monto_pagado');
        }

        $totales = [
            'contratos' => $totalPolizas,
            'ops_in' => $totalOpsIn,
            'ops_out' => $totalOpsOut,
            'monto_in' => $totalMontoIn,
            'monto_out' => $totalMontoOut,
        ];

        $distribuciones = [];
        foreach ($grupos as $grupo) {
            foreach (array_keys($totales) as $metrica) {
                $distribuciones[$grupo][$metrica] = $this->dividir($base[$grupo][$metrica], $totales[$metrica]);
            }
        }

        $indicadores = $this->config['productos']['indicadores'];
        $puntajes = $this->config['productos']['puntajes'];
        $offset = (float) $this->config['productos']['offset'];
        $mitiganteIndicador = array_fill(0, count($indicadores), 0.0);
        $mitiganteIndicador[1] = $this->efectoMitigante($mitigantes, 'producto_1_2');

        $detalle = [];
        foreach ($grupos as $grupo) {
            $puntaje = $puntajes[$grupo] ?? $puntajes['Otro'];
            $inherente = 0.0;
            $residual = 0.0;
            foreach ($indicadores as $i => $indicador) {
                $valor = (float) ($puntaje[$i] ?? 0);
                $inherente += (float) $indicador['peso'] * $valor;
                $residual += (float) $indicador['peso'] * $valor * (1 - $mitiganteIndicador[$i]);
            }
            $inherente = $this->limitar5(max(0, $inherente - $offset));
            $residual = $this->limitar5(max(0, $residual - $offset));

            $detalle[$grupo] = [
                'grupo' => $grupo,
                'puntajes' => $puntaje,
                'inherente' => round($inherente, 6),
                'nivel' => $this->nivel($inherente),
                'residual' => round($residual, 6),
                'nivel_residual' => $this->nivel($residual),
                'distribuciones' => $distribuciones[$grupo],
            ];
        }

        $elementoInherente = 0.0;
        foreach (array_keys($totales) as $metrica) {
            $score = 0.0;
            foreach ($grupos as $grupo) {
                $score += $detalle[$grupo]['inherente'] * $distribuciones[$grupo][$metrica];
            }
            $elementoInherente = max($elementoInherente, $score);
        }

        $elementoResidual = 0.0;
        foreach (['ops_in', 'ops_out', 'monto_in', 'monto_out'] as $metrica) {
            $score = 0.0;
            foreach ($grupos as $grupo) {
                $score += $detalle[$grupo]['residual'] * $distribuciones[$grupo][$metrica];
            }
            $elementoResidual = max($elementoResidual, $score);
        }

        return [
            'elemento' => 'Productos y Servicios',
            'indicadores' => $indicadores,
            'detalle' => array_values($detalle),
            'totales' => $totales,
            'inherente' => round($elementoInherente, 6),
            'nivel' => $this->nivel($elementoInherente),
            'residual' => round($elementoResidual, 6),
            'nivel_residual' => $this->nivel($elementoResidual),
            'mitigante' => 0.0,
        ];
    }

    private function calcularClientes(array $polizas, array $siniestros, array $mitigantes): array
    {
        $porTipo = [];
        foreach (['Persona Física', 'Persona Moral'] as $tipo) {
            $porTipo[$tipo] = array_values(array_filter($polizas, fn ($p) => ($p['tipo_persona'] ?? '') === $tipo));
        }

        $siniestrosPorTipo = [];
        foreach (['Persona Física', 'Persona Moral'] as $tipo) {
            $siniestrosPorTipo[$tipo] = array_values(array_filter($siniestros, fn ($s) => ($s['tipo_persona'] ?? '') === $tipo));
        }

        $tipos = [
            'pf' => ['tipo' => 'Persona Física', 'config' => $this->config['clientes']['pf']],
            'pm' => ['tipo' => 'Persona Moral', 'config' => $this->config['clientes']['pm']],
        ];

        $resultadoTipos = [];
        foreach ($tipos as $clave => $tipoInfo) {
            $tipo = $tipoInfo['tipo'];
            $cfg = $tipoInfo['config'];
            $polizasTipo = $porTipo[$tipo];
            $siniestrosTipo = $siniestrosPorTipo[$tipo];

            $metricas = $this->metricasCliente($polizasTipo, $siniestrosTipo, $cfg);
            $indicadores = [];

            foreach ($cfg['indicadores'] as $indicador) {
                $buckets = $metricas['buckets'][$indicador['clave']] ?? [];
                $escala = $indicador['escala'];
                $scoresPorMetrica = [];
                foreach (array_keys($this->metricasClienteBase()) as $metrica) {
                    $score = 0.0;
                    foreach ($escala as $i => $valor) {
                        $score += (float) $valor * (float) ($buckets[$i][$metrica] ?? 0);
                    }
                    $scoresPorMetrica[$metrica] = $score;
                }

                $referencia = $cfg['referencia_metrica'] ?? $clave;
                if ($referencia !== $clave && isset($tipos[$referencia])) {
                    $refCfg = $tipos[$referencia]['config'];
                    $refMetricas = $this->metricasCliente($porTipo[$tipos[$referencia]['tipo']], $siniestrosPorTipo[$tipos[$referencia]['tipo']], $refCfg);
                    $refBuckets = $refMetricas['buckets'][$indicador['clave']] ?? [];
                    $refScores = [];
                    foreach (array_keys($this->metricasClienteBase()) as $metrica) {
                        $score = 0.0;
                        foreach ($escala as $i => $valor) {
                            $score += (float) $valor * (float) ($refBuckets[$i][$metrica] ?? 0);
                        }
                        $refScores[$metrica] = $score;
                    }
                    $metricaDominante = $this->metricaMayor($refScores);
                } else {
                    $metricaDominante = $this->metricaMayor($scoresPorMetrica);
                }

                $inherente = min(5, (float) ($scoresPorMetrica[$metricaDominante] ?? 0));
                $mitigante = $indicador['clave'] === 'actividad' ? $this->efectoMitigante($mitigantes, 'cliente_actividad') : 0.0;
                $residual = $inherente * (1 - $mitigante);

                $indicadores[] = [
                    'clave' => $indicador['clave'],
                    'codigo' => $indicador['codigo'],
                    'nombre' => $indicador['nombre'],
                    'peso' => (float) $indicador['peso'],
                    'metrica_dominante' => $metricaDominante,
                    'inherente' => round($inherente, 6),
                    'nivel' => $this->nivel($inherente),
                    'mitigante' => $mitigante,
                    'residual' => round($residual, 6),
                    'nivel_residual' => $this->nivel($residual),
                    'buckets' => array_map(function ($bucket, $i) use ($indicador, $escala) {
                        return [
                            'etiqueta' => $indicador['etiquetas'][$i] ?? ('Escala '.($i + 1)),
                            'puntaje' => (float) $escala[$i],
                            'distribuciones' => $bucket,
                        ];
                    }, $buckets, array_keys($buckets)),
                ];
            }

            $inherente = 0.0;
            $residual = 0.0;
            foreach ($indicadores as $indicador) {
                $inherente += $indicador['peso'] * $indicador['inherente'];
                $residual += $indicador['peso'] * $indicador['residual'];
            }
            $inherente = $this->limitar5(max(0, $inherente - (float) $cfg['offset']));
            $residual = $this->limitar5(max(0, $residual - (float) $cfg['offset']));

            $resultadoTipos[$clave] = [
                'tipo' => $tipo,
                'inherente' => round($inherente, 6),
                'nivel' => $this->nivel($inherente),
                'residual' => round($residual, 6),
                'nivel_residual' => $this->nivel($residual),
                'indicadores' => $indicadores,
                'metricas' => $metricas['totales'],
                'distribuciones_tipo' => [],
            ];
        }

        $totalesGlobales = [];
        foreach (array_keys($this->metricasClienteBase()) as $metrica) {
            $totalesGlobales[$metrica] = 0;
            foreach ($resultadoTipos as $tipo) {
                $totalesGlobales[$metrica] += (float) ($tipo['metricas'][$metrica] ?? 0);
            }
        }
        foreach ($resultadoTipos as $claveTipo => $tipo) {
            $distribuciones = [];
            foreach ($totalesGlobales as $metrica => $totalGlobal) {
                $distribuciones[$metrica] = $this->dividir((float) ($tipo['metricas'][$metrica] ?? 0), $totalGlobal);
            }
            $resultadoTipos[$claveTipo]['distribuciones_tipo'] = $distribuciones;
        }

        $metricasElemento = array_keys($this->metricasClienteBase());
        $inherenteElemento = 0.0;
        $residualElemento = 0.0;
        foreach ($metricasElemento as $metrica) {
            $scoreInh = 0.0;
            $scoreRes = 0.0;
            foreach ($resultadoTipos as $tipo) {
                $dist = $tipo['distribuciones_tipo'][$metrica] ?? 0;
                $scoreInh += $tipo['inherente'] * $dist;
                $scoreRes += $tipo['residual'] * $dist;
            }
            $inherenteElemento = max($inherenteElemento, $scoreInh);
            $residualElemento = max($residualElemento, $scoreRes);
        }
        $mitiganteElemento = $this->efectoMitigante($mitigantes, 'cliente_elemento');
        $residualElemento *= (1 - $mitiganteElemento);

        return [
            'elemento' => 'Clientes',
            'pf' => $resultadoTipos['pf'],
            'pm' => $resultadoTipos['pm'],
            'inherente' => round($inherenteElemento, 6),
            'nivel' => $this->nivel($inherenteElemento),
            'residual' => round($residualElemento, 6),
            'nivel_residual' => $this->nivel($residualElemento),
            'mitigante' => $mitiganteElemento,
        ];
    }

    private function calcularGeografia(array $polizas, array $siniestros, array $mitigantes): array
    {
        $paises = [];
        $estados = [];

        foreach ($polizas as $poliza) {
            $paisNombre = $this->paisNombre($poliza['pais_nacionalidad'] ?? 'México');
            if (! isset($paises[$paisNombre])) {
                $paises[$paisNombre] = [
                    'pais' => $paisNombre,
                    'score' => $this->scorePais($paisNombre),
                    'ops_in' => 0,
                    'ops_out' => 0,
                    'monto_in' => 0.0,
                    'monto_out' => 0.0,
                    'clientes' => [],
                ];
            }
            $paises[$paisNombre]['ops_in'] += (int) ($poliza['num_operaciones_entrada'] ?? 1);
            $paises[$paisNombre]['monto_in'] += $this->montoMxn($poliza, 'prima_total');
            $paises[$paisNombre]['clientes'][(string) ($poliza['no_cliente'] ?? $poliza['no_poliza'])] = true;

            $estado = $this->estadoNombre($poliza['estado'] ?? '');
            if (! isset($estados[$estado])) {
                $estados[$estado] = ['estado' => $estado, 'ops_in' => 0, 'ops_out' => 0, 'monto_in' => 0.0, 'monto_out' => 0.0, 'clientes' => []];
            }
            $estados[$estado]['ops_in'] += (int) ($poliza['num_operaciones_entrada'] ?? 1);
            $estados[$estado]['monto_in'] += $this->montoMxn($poliza, 'prima_total');
            $estados[$estado]['clientes'][(string) ($poliza['no_cliente'] ?? $poliza['no_poliza'])] = true;
        }

        foreach ($siniestros as $siniestro) {
            $paisNombre = $this->paisNombre($siniestro['pais_nacionalidad'] ?? 'México');
            if (! isset($paises[$paisNombre])) {
                $paises[$paisNombre] = [
                    'pais' => $paisNombre,
                    'score' => $this->scorePais($paisNombre),
                    'ops_in' => 0,
                    'ops_out' => 0,
                    'monto_in' => 0.0,
                    'monto_out' => 0.0,
                    'clientes' => [],
                ];
            }
            $paises[$paisNombre]['ops_out'] += (int) ($siniestro['num_operaciones_salida'] ?? 1);
            $paises[$paisNombre]['monto_out'] += $this->montoMxn($siniestro, 'monto_pagado');

            $estado = $this->estadoNombre($siniestro['estado'] ?? '');
            if (! isset($estados[$estado])) {
                $estados[$estado] = ['estado' => $estado, 'ops_in' => 0, 'ops_out' => 0, 'monto_in' => 0.0, 'monto_out' => 0.0, 'clientes' => []];
            }
            $estados[$estado]['ops_out'] += (int) ($siniestro['num_operaciones_salida'] ?? 1);
            $estados[$estado]['monto_out'] += $this->montoMxn($siniestro, 'monto_pagado');
        }

        foreach ($paises as $nombre => $data) {
            $paises[$nombre]['clientes'] = count($data['clientes']);
        }

        $totalOpsIn = array_sum(array_column($paises, 'ops_in'));
        $totalOpsOut = array_sum(array_column($paises, 'ops_out'));
        $totalMontoIn = array_sum(array_column($paises, 'monto_in'));
        $totalMontoOut = array_sum(array_column($paises, 'monto_out'));
        $totalClientes = count($this->clientesUnicos($polizas));

        $distribucionesPais = [];
        foreach ($paises as $nombre => $data) {
            $distribucionesPais[$nombre] = [
                'ops_in' => $this->dividir($data['ops_in'], $totalOpsIn),
                'ops_out' => $this->dividir($data['ops_out'], $totalOpsOut),
                'monto_in' => $this->dividir($data['monto_in'], $totalMontoIn),
                'monto_out' => $this->dividir($data['monto_out'], $totalMontoOut),
                'clientes' => $this->dividir($data['clientes'], $totalClientes),
            ];
        }

        $detallePaises = [];
        foreach ($paises as $nombre => $data) {
            $detallePaises[$nombre] = [
                'pais' => $nombre,
                'score' => $data['score'],
                'ops_in' => $data['ops_in'],
                'ops_out' => $data['ops_out'],
                'monto_in' => round($data['monto_in'], 2),
                'monto_out' => round($data['monto_out'], 2),
                'clientes' => $data['clientes'],
                'inherente' => round($data['score'], 6),
                'nivel' => $this->nivel($data['score']),
                'residual' => round($data['score'], 6),
                'nivel_residual' => $this->nivel($data['score']),
                'distribuciones' => $distribucionesPais[$nombre],
            ];
        }

        $elementoPaisInh = 0.0;
        foreach (['ops_out', 'monto_in', 'monto_out', 'clientes'] as $metrica) {
            $score = 0.0;
            foreach ($paises as $nombre => $data) {
                $score += $data['score'] * ($distribucionesPais[$nombre][$metrica] ?? 0);
            }
            $elementoPaisInh = max($elementoPaisInh, $score);
        }
        $elementoPaisRes = $this->limitar5($elementoPaisInh);

        $totalOpsInEstados = array_sum(array_map(fn ($e) => $e['ops_in'], $estados));
        $totalOpsOutEstados = array_sum(array_map(fn ($e) => $e['ops_out'], $estados));
        $totalMontoInEstados = array_sum(array_map(fn ($e) => $e['monto_in'], $estados));
        $totalMontoOutEstados = array_sum(array_map(fn ($e) => $e['monto_out'], $estados));

        $indicadoresEstado = $this->config['geografia']['estados']['indicadores'];
        $detalleEstados = [];
        $estadosSinCatalogo = [];
        foreach ($estados as $estado => $data) {
            $catalogo = $this->estados[$estado] ?? $this->estados['Nacional'] ?? null;
            if (! isset($this->estados[$estado]) && $estado !== 'Nacional' && ! in_array($estado, $estadosSinCatalogo, true)) {
                $estadosSinCatalogo[] = $estado;
            }
            $scorePaz = (float) ($catalogo['score_paz'] ?? 3);
            $scoreFrontera = (float) ($catalogo['score_frontera'] ?? 3);
            $inherente = $this->limitar5(($scorePaz * (float) $indicadoresEstado[0]['peso']) + ($scoreFrontera * (float) $indicadoresEstado[1]['peso']));
            $residual = $this->limitar5(($scorePaz * (float) $indicadoresEstado[0]['peso']) + ($scoreFrontera * (float) $indicadoresEstado[1]['peso']));
            $detalleEstados[$estado] = [
                'estado' => $estado,
                'score_paz' => $scorePaz,
                'score_frontera' => $scoreFrontera,
                'inherente' => round($inherente, 6),
                'nivel' => $this->nivel($inherente),
                'residual' => round($residual, 6),
                'nivel_residual' => $this->nivel($residual),
                'ops_in' => $data['ops_in'],
                'ops_out' => $data['ops_out'],
                'monto_in' => round($data['monto_in'], 2),
                'monto_out' => round($data['monto_out'], 2),
                'distribuciones' => [
                    'ops_in' => $this->dividir($data['ops_in'], $totalOpsInEstados),
                    'ops_out' => $this->dividir($data['ops_out'], $totalOpsOutEstados),
                    'monto_in' => $this->dividir($data['monto_in'], $totalMontoInEstados),
                    'monto_out' => $this->dividir($data['monto_out'], $totalMontoOutEstados),
                ],
            ];
        }

        foreach ($estadosSinCatalogo as $estadoSinCatalogo) {
            $this->advertencia("El estado {$estadoSinCatalogo} no está en el catálogo EBR; se usó el score Nacional (3.0).");
        }

        $elementoEstadosInh = 0.0;
        $elementoEstadosRes = 0.0;
        foreach (['ops_out', 'monto_in', 'monto_out', 'ops_in'] as $metrica) {
            $scoreInh = 0.0;
            $scoreRes = 0.0;
            foreach ($detalleEstados as $estado) {
                $scoreInh += $estado['inherente'] * ($estado['distribuciones'][$metrica] ?? 0);
                $scoreRes += $estado['residual'] * ($estado['distribuciones'][$metrica] ?? 0);
            }
            $elementoEstadosInh = max($elementoEstadosInh, $scoreInh);
            $elementoEstadosRes = max($elementoEstadosRes, $scoreRes);
        }

        $inherente = max($elementoPaisInh, $elementoEstadosInh);
        $residual = max($elementoPaisRes, $elementoEstadosRes);

        usort($detalleEstados, fn ($a, $b) => $b['inherente'] <=> $a['inherente']);
        usort($detallePaises, fn ($a, $b) => $b['score'] <=> $a['score']);

        $paisPrincipal = $detallePaises[0] ?? [
            'pais' => 'México',
            'score' => 3.0,
            'inherente' => 0.0,
            'nivel' => $this->nivel(0),
            'residual' => 0.0,
            'nivel_residual' => $this->nivel(0),
            'distribuciones' => [],
        ];
        $paisPrincipal['elemento_inherente'] = round($elementoPaisInh, 6);
        $paisPrincipal['elemento_residual'] = round($elementoPaisRes, 6);

        return [
            'elemento' => 'Países y Áreas Geográficas',
            'pais' => $paisPrincipal,
            'paises' => array_values($detallePaises),
            'estados' => array_values($detalleEstados),
            'inherente' => round($inherente, 6),
            'nivel' => $this->nivel($inherente),
            'residual' => round($residual, 6),
            'nivel_residual' => $this->nivel($residual),
            'mitigante' => 0.0,
        ];
    }

    private function paisNombre(?string $pais): string
    {
        $pais = trim((string) $pais);
        if ($pais === '') {
            return 'México';
        }
        foreach ($this->paises as $nombre => $data) {
            if ($this->normalizar($nombre) === $this->normalizar($pais)) {
                return $nombre;
            }
        }

        return $pais;
    }

    private function calcularCanales(array $polizas, array $siniestros, array $mitigantes): array
    {
        $grupos = $this->config['canales']['grupos'];
        $base = [];
        foreach ($grupos as $grupo) {
            $base[$grupo] = ['ops_in' => 0, 'ops_out' => 0, 'monto_in' => 0.0, 'monto_out' => 0.0];
        }

        foreach ($polizas as $poliza) {
            $canal = $this->canalNormalizado($poliza);
            $base[$canal]['ops_in'] += (int) ($poliza['num_operaciones_entrada'] ?? 1);
            $base[$canal]['monto_in'] += $this->montoMxn($poliza, 'prima_total');
        }
        foreach ($siniestros as $siniestro) {
            $canal = $this->canalNormalizado($siniestro);
            $base[$canal]['ops_out'] += (int) ($siniestro['num_operaciones_salida'] ?? 1);
            $base[$canal]['monto_out'] += $this->montoMxn($siniestro, 'monto_pagado');
        }

        $totales = [];
        foreach (['ops_in', 'ops_out', 'monto_in', 'monto_out'] as $metrica) {
            $totales[$metrica] = array_sum(array_map(fn ($g) => $g[$metrica], $base));
        }
        $distribuciones = [];
        foreach ($grupos as $grupo) {
            foreach ($totales as $metrica => $total) {
                $distribuciones[$grupo][$metrica] = $this->dividir($base[$grupo][$metrica], $total);
            }
        }

        $indicadores = $this->config['canales']['indicadores'];
        $puntajes = $this->config['canales']['puntajes'];
        $offset = (float) $this->config['canales']['offset'];
        $mitiganteIndicador = array_fill(0, count($indicadores), 0.0);
        $detalle = [];
        foreach ($grupos as $grupo) {
            $puntaje = $puntajes[$grupo] ?? $puntajes['Interno'];
            $inherente = 0.0;
            $residual = 0.0;
            foreach ($indicadores as $i => $indicador) {
                $valor = (float) ($puntaje[$i] ?? 0);
                $inherente += (float) $indicador['peso'] * $valor;
                $residual += (float) $indicador['peso'] * $valor * (1 - $mitiganteIndicador[$i]);
            }
            $inherente = $this->limitar5(max(0, $inherente - $offset));
            $residual = $this->limitar5(max(0, $residual - $offset));
            $detalle[$grupo] = [
                'grupo' => $grupo,
                'puntajes' => $puntaje,
                'inherente' => round($inherente, 6),
                'nivel' => $this->nivel($inherente),
                'residual' => round($residual, 6),
                'nivel_residual' => $this->nivel($residual),
                'distribuciones' => $distribuciones[$grupo],
            ];
        }

        $elementoInherente = 0.0;
        $elementoResidual = 0.0;
        foreach (['ops_in', 'ops_out', 'monto_in', 'monto_out'] as $metrica) {
            $scoreInh = 0.0;
            $scoreRes = 0.0;
            foreach ($grupos as $grupo) {
                $scoreInh += $detalle[$grupo]['inherente'] * $distribuciones[$grupo][$metrica];
                $scoreRes += $detalle[$grupo]['residual'] * $distribuciones[$grupo][$metrica];
            }
            $elementoInherente = max($elementoInherente, $scoreInh);
            $elementoResidual = max($elementoResidual, $scoreRes);
        }

        return [
            'elemento' => 'Canales de Envío y Distribución',
            'indicadores' => $indicadores,
            'detalle' => array_values($detalle),
            'totales' => $totales,
            'inherente' => round($elementoInherente, 6),
            'nivel' => $this->nivel($elementoInherente),
            'residual' => round($elementoResidual, 6),
            'nivel_residual' => $this->nivel($elementoResidual),
            'mitigante' => 0.0,
        ];
    }

    private function calcularTransacciones(array $polizas, array $alertas, array $mitigantes): array
    {
        $totalPolizas = count($this->polizasUnicas($polizas));
        $totalClientes = count($this->clientesUnicos($polizas));
        $totalOpsIn = array_sum(array_map(fn ($p) => (int) ($p['num_operaciones_entrada'] ?? 1), $polizas));
        $totalMontoIn = array_sum(array_map(fn ($p) => $this->montoMxn($p, 'prima_total'), $polizas));

        $procedentes = array_values(array_filter($alertas, fn ($a) => (bool) ($a['procedio'] ?? false)));
        $indicadores = [];
        $inherente = 0.0;
        $residual = 0.0;
        $alertasRelevantes = 0;

        foreach ($this->config['transacciones']['indicadores'] as $indicador) {
            $patrones = $indicador['patrones'] ?? [$indicador['patron'] ?? ''];
            $patronesNormalizados = array_map(fn ($p) => $this->normalizar($p), $patrones);
            $filtradas = array_values(array_filter(
                $procedentes,
                fn ($a) => in_array($this->normalizar($a['patron'] ?? ''), $patronesNormalizados, true)
            ));

            $alertasCount = count($filtradas);
            if (($indicador['codigo'] ?? '') === '5.4') {
                $alertasRelevantes = $alertasCount;
            }

            $contratos = [];
            $clientes = [];
            $operaciones = 0;
            $monto = 0.0;
            foreach ($filtradas as $alerta) {
                if (! empty($alerta['no_poliza'])) {
                    $contratos[(string) $alerta['no_poliza']] = true;
                }
                if (! empty($alerta['no_cliente'])) {
                    $clientes[(string) $alerta['no_cliente']] = true;
                }
                $operaciones += (int) ($alerta['num_operaciones'] ?? 1);
                $monto += $this->montoMxn($alerta, 'monto');
            }

            $metricas = [
                'contratos' => $this->dividir(count($contratos), $totalPolizas),
                'clientes' => $this->dividir(count($clientes), $totalClientes),
                'operaciones' => $this->dividir($operaciones, $totalOpsIn),
                'monto' => $this->dividir($monto, $totalMontoIn),
            ];
            $impacto = min(1, max($metricas) / (float) $this->config['transacciones']['denominador_alertas']);
            $score = $this->limitar5((float) $indicador['puntaje'] * $impacto);
            $inherente += $score;
            $residual += $score;

            $indicadores[] = [
                'codigo' => $indicador['codigo'],
                'nombre' => $indicador['nombre'],
                'patron' => implode(' / ', $patrones),
                'patrones' => $patrones,
                'puntaje' => (float) $indicador['puntaje'],
                'alertas' => $alertasCount,
                'contratos' => count($contratos),
                'clientes' => count($clientes),
                'operaciones' => $operaciones,
                'monto' => round($monto, 2),
                'metricas' => $metricas,
                'impacto' => round($impacto, 6),
                'inherente' => round($score, 6),
                'nivel' => $this->nivel($score),
                'residual' => round($score, 6),
                'nivel_residual' => $this->nivel($score),
            ];
        }

        $inherente = $this->limitar5($inherente);
        $residual = $this->limitar5($residual);

        return [
            'elemento' => 'Transacciones',
            'indicadores' => $indicadores,
            'totales' => [
                'polizas' => $totalPolizas,
                'clientes' => $totalClientes,
                'operaciones_entrada' => $totalOpsIn,
                'monto_entrada' => round($totalMontoIn, 2),
                'alertas_procedentes' => count($procedentes),
                'alertas_relevantes' => $alertasRelevantes,
            ],
            'inherente' => round($inherente, 6),
            'nivel' => $this->nivel($inherente),
            'residual' => round($residual, 6),
            'nivel_residual' => $this->nivel($residual),
            'mitigante' => 0.0,
        ];
    }

    private function calcularInstitucion(array $elementos, array $mitigantes): array
    {
        $pesos = $this->config['institucion']['pesos'];
        $mapa = [
            'productos' => $elementos['productos'],
            'clientes' => $elementos['clientes'],
            'geografia' => $elementos['geografia'],
            'canales' => $elementos['canales'],
            'transacciones' => $elementos['transacciones'],
        ];

        $inherente = 0.0;
        $residual = 0.0;
        $detalle = [];
        foreach ($mapa as $clave => $elemento) {
            $peso = (float) ($pesos[$clave] ?? 0.2);
            $inherente += $peso * $elemento['inherente'];
            $residual += $peso * $elemento['residual'];
            $detalle[] = [
                'clave' => $clave,
                'elemento' => $elemento['elemento'],
                'peso' => $peso,
                'inherente' => $elemento['inherente'],
                'nivel' => $elemento['nivel'],
                'residual' => $elemento['residual'],
                'nivel_residual' => $elemento['nivel_residual'],
            ];
        }

        $mitiganteEntidad = $this->efectoMitigante($mitigantes, 'entidad');
        $residualFinal = max(0, $residual * (1 - $mitiganteEntidad));

        return [
            'elemento' => 'Institución',
            'detalle' => $detalle,
            'inherente' => round($inherente, 6),
            'nivel_inherente' => $this->nivel($inherente),
            'residual' => round($residual, 6),
            'nivel_residual' => $this->nivel($residual),
            'mitigante_entidad' => $mitiganteEntidad,
            'residual_final' => round($residualFinal, 6),
            'nivel' => $this->nivel($residualFinal),
        ];
    }

    private function comparar(?array $anterior, array $actual): ?array
    {
        if (! $anterior) {
            return null;
        }

        $comparativo = ['anio_anterior' => $anterior['anio'] ?? null, 'elementos' => []];
        $mapa = [
            'productos' => 'productos',
            'clientes' => 'clientes',
            'geografia' => 'geografia',
            'canales' => 'canales',
            'transacciones' => 'transacciones',
        ];
        foreach ($mapa as $clave => $llave) {
            $prev = $anterior['resultados']['elementos'][$llave] ?? null;
            if (! $prev) {
                continue;
            }
            $comparativo['elementos'][$clave] = [
                'inherente_anterior' => $prev['inherente'],
                'inherente_actual' => $actual[$llave]['inherente'],
                'variacion_inherente' => round($actual[$llave]['inherente'] - $prev['inherente'], 6),
                'residual_anterior' => $prev['residual'],
                'residual_actual' => $actual[$llave]['residual'],
                'variacion_residual' => round($actual[$llave]['residual'] - $prev['residual'], 6),
            ];
        }
        $prevInst = $anterior['resultados']['institucion'] ?? null;
        if ($prevInst) {
            $comparativo['institucion'] = [
                'inherente_anterior' => $prevInst['inherente'],
                'inherente_actual' => $actual['institucion']['inherente'],
                'variacion_inherente' => round($actual['institucion']['inherente'] - $prevInst['inherente'], 6),
                'residual_final_anterior' => $prevInst['residual_final'],
                'residual_final_actual' => $actual['institucion']['residual_final'],
                'variacion_residual_final' => round($actual['institucion']['residual_final'] - $prevInst['residual_final'], 6),
            ];
        }

        return $comparativo;
    }

    private function metricasCliente(array $polizas, array $siniestros, array $cfg): array
    {
        $base = $this->metricasClienteBase();
        $buckets = [];
        foreach ($cfg['indicadores'] as $indicador) {
            $buckets[$indicador['clave']] = [];
            foreach ($indicador['escala'] as $i => $valor) {
                $buckets[$indicador['clave']][$i] = array_fill_keys(array_keys($base), 0);
            }
        }

        $totales = array_fill_keys(array_keys($base), 0);
        $clientes = [];
        $clientesBuckets = [];
        foreach ($cfg['indicadores'] as $indicador) {
            $clave = $indicador['clave'];
            foreach ($indicador['escala'] as $i => $valor) {
                $clientesBuckets[$clave][$i] = [];
            }
        }
        foreach ($polizas as $poliza) {
            $clienteId = (string) ($poliza['no_cliente'] ?? $poliza['no_poliza']);
            $clientes[$clienteId] = true;
            $idx = $this->indicesCliente($poliza);
            $totales['clientes']++;
            $totales['ops_in'] += (int) ($poliza['num_operaciones_entrada'] ?? 1);
            $totales['monto_in'] += $this->montoMxn($poliza, 'prima_total');
            foreach ($cfg['indicadores'] as $indicador) {
                $clave = $indicador['clave'];
                $i = $idx[$clave] ?? 0;
                if (! isset($buckets[$clave][$i])) {
                    $i = 0;
                }
                $clientesBuckets[$clave][$i][$clienteId] = true;
                $buckets[$clave][$i]['ops_in'] += (int) ($poliza['num_operaciones_entrada'] ?? 1);
                $buckets[$clave][$i]['monto_in'] += $this->montoMxn($poliza, 'prima_total');
            }
        }

        $totalClientes = count($clientes);
        foreach ($cfg['indicadores'] as $indicador) {
            $clave = $indicador['clave'];
            foreach ($buckets[$clave] as $i => $bucket) {
                $buckets[$clave][$i]['clientes'] = $this->dividir(count($clientesBuckets[$clave][$i] ?? []), $totalClientes);
            }
        }

        foreach ($siniestros as $siniestro) {
            $idx = $this->indicesCliente($siniestro);
            $totales['ops_out'] += (int) ($siniestro['num_operaciones_salida'] ?? 1);
            $totales['monto_out'] += $this->montoMxn($siniestro, 'monto_pagado');
            foreach ($cfg['indicadores'] as $indicador) {
                $clave = $indicador['clave'];
                $i = $idx[$clave] ?? 0;
                if (! isset($buckets[$clave][$i])) {
                    $i = 0;
                }
                $buckets[$clave][$i]['ops_out'] += (int) ($siniestro['num_operaciones_salida'] ?? 1);
                $buckets[$clave][$i]['monto_out'] += $this->montoMxn($siniestro, 'monto_pagado');
            }
        }

        foreach ($cfg['indicadores'] as $indicador) {
            $clave = $indicador['clave'];
            foreach ($buckets[$clave] as $i => $bucket) {
                $buckets[$clave][$i]['ops_out'] = $this->dividir($bucket['ops_out'], $totales['ops_out']);
                $buckets[$clave][$i]['monto_in'] = $this->dividir($bucket['monto_in'], $totales['monto_in']);
                $buckets[$clave][$i]['monto_out'] = $this->dividir($bucket['monto_out'], $totales['monto_out']);
                $buckets[$clave][$i]['ops_in'] = $this->dividir($bucket['ops_in'], $totales['ops_in']);
            }
        }

        $totales['clientes'] = $totalClientes;

        return [
            'buckets' => $buckets,
            'totales' => $totales,
        ];
    }

    private function metricasClienteBase(): array
    {
        return ['clientes' => 0, 'ops_in' => 0, 'ops_out' => 0, 'monto_in' => 0, 'monto_out' => 0];
    }

    private function indicesCliente(array $fila): array
    {
        $tipo = $fila['tipo_persona'] ?? 'Persona Física';
        $edad = $this->valorFecha($fila, 'fecha_nacimiento', 'edad');
        $antiguedadEmpresa = $this->valorFecha($fila, 'fecha_constitucion', 'antiguedad_empresa_anios');
        $duracion = $this->valorFecha($fila, 'fecha_primera_relacion', 'antiguedad_relacion_anios');
        $estado = $this->estadoNombre($fila['estado'] ?? '');
        $sinDomicilio = trim((string) ($fila['estado'] ?? '')) === '' || ! isset($this->estados[$estado]);
        $riesgoEstado = (int) ($fila['riesgo_ubicacion'] ?? $this->estados[$estado]['riesgo_ubicacion'] ?? 3);
        $nivelActividad = (int) ($fila['actividad_nivel_riesgo'] ?? 0);
        $lista = (int) ($fila['lista_riesgo'] ?? $this->listaRiesgo($fila['categoria_pld'] ?? ''));

        $edadIdx = $edad === null ? 4 : ($edad <= 25 ? 0 : ($edad <= 40 ? 1 : ($edad <= 60 ? 2 : 3)));
        $antiguedadIdx = $antiguedadEmpresa === null ? 4 : ($antiguedadEmpresa < 3 ? 0 : ($antiguedadEmpresa <= 5 ? 1 : ($antiguedadEmpresa <= 10 ? 2 : 3)));
        $duracionIdx = $duracion === null ? 3 : ($duracion < 1 ? 0 : ($duracion < 4 ? 1 : 2));
        $domicilioIdx = $sinDomicilio ? 3 : ($riesgoEstado === 5 ? 0 : ($riesgoEstado === 3 ? 1 : ($riesgoEstado === 1 ? 2 : 3)));
        $actividadIdx = $nivelActividad === 3 ? 0 : ($nivelActividad === 2 ? 1 : ($nivelActividad === 1 ? 2 : 3));
        $listaIdx = $lista === 5 ? 0 : ($lista === 3 ? 1 : ($lista === 2 ? 2 : 3));

        if ($tipo === 'Persona Moral') {
            return [
                'pais' => $this->paisNacionalidadIdx($fila['pais_nacionalidad'] ?? 'México'),
                'antiguedad' => $antiguedadIdx,
                'duracion' => $duracionIdx,
                'domicilio' => $domicilioIdx,
                'actividad' => $actividadIdx,
                'listas' => $listaIdx,
            ];
        }

        return [
            'pais' => $this->paisNacionalidadIdx($fila['pais_nacionalidad'] ?? 'México'),
            'edad' => $edadIdx,
            'duracion' => $duracionIdx,
            'domicilio' => $domicilioIdx,
            'actividad' => $actividadIdx,
            'listas' => $listaIdx,
        ];
    }

    private function valorFecha(array $fila, string $fechaKey, string $numeroKey): ?float
    {
        if (! empty($fila[$fechaKey])) {
            try {
                return (float) Carbon::parse($fila[$fechaKey])->diffInYears($this->fechaCorte);
            } catch (\Throwable) {
                // Se usa el valor numérico como respaldo.
            }
        }
        if (isset($fila[$numeroKey]) && $fila[$numeroKey] !== null && $fila[$numeroKey] !== '') {
            return (float) $fila[$numeroKey];
        }

        return null;
    }

    private function paisNacionalidadIdx(string $pais): int
    {
        if (trim($pais) === '') {
            return 3;
        }

        $score = $this->scorePais($pais);

        return $score >= 5 ? 0 : ($score >= 3 ? 1 : ($score >= 1 ? 2 : 3));
    }

    private function scorePais(string $pais): float
    {
        foreach ($this->paises as $nombre => $data) {
            if ($this->normalizar($nombre) === $this->normalizar($pais)) {
                return (float) $data['score_bis'];
            }
        }

        return 3.0;
    }

    private function listaRiesgo(string $categoria): int
    {
        $categoria = $this->normalizar($categoria);

        return (int) ($this->config['categorias_pld'][$categoria] ?? 1);
    }

    private function enriquecerSiniestro(array $siniestro, array $polizas): array
    {
        $siniestro['__incluida'] = false;
        $poliza = $this->buscarPoliza($siniestro['no_poliza'] ?? '', $polizas);

        if (! $poliza) {
            return $siniestro;
        }

        $siniestro['tipo_persona'] = $poliza['tipo_persona'] ?? null;
        $siniestro['fecha_nacimiento'] = $poliza['fecha_nacimiento'] ?? null;
        $siniestro['fecha_constitucion'] = $poliza['fecha_constitucion'] ?? null;
        $siniestro['fecha_primera_relacion'] = $poliza['fecha_primera_relacion'] ?? null;
        $siniestro['edad'] = $poliza['edad'] ?? null;
        $siniestro['antiguedad_empresa_anios'] = $poliza['antiguedad_empresa_anios'] ?? null;
        $siniestro['antiguedad_relacion_anios'] = $poliza['antiguedad_relacion_anios'] ?? null;
        $siniestro['actividad_nivel_riesgo'] = $poliza['actividad_nivel_riesgo'] ?? null;
        $siniestro['categoria_pld'] = $poliza['categoria_pld'] ?? null;
        $siniestro['lista_riesgo'] = $poliza['lista_riesgo'] ?? null;
        $siniestro['pais_nacionalidad'] = $poliza['pais_nacionalidad'] ?? 'México';
        $siniestro['estado'] = $siniestro['estado'] ?: ($poliza['estado'] ?? null);
        $grupoCsv = $this->grupoProductoValido($siniestro['grupo_producto'] ?? null);
        $siniestro['grupo_producto'] = $grupoCsv ?? $this->grupoProducto($poliza);
        $siniestro['canal'] = $siniestro['canal'] ?: ($poliza['canal'] ?? null);
        $siniestro['no_cliente'] = $poliza['no_cliente'] ?? ($siniestro['no_cliente'] ?? null);
        $siniestro['__incluida'] = true;

        return $siniestro;
    }

    private function normalizarAlerta(array $alerta): array
    {
        $alerta['procedio'] = filter_var($alerta['procedio'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $alerta['num_operaciones'] = max(1, (int) ($alerta['num_operaciones'] ?? 1));
        $alerta['__vinculada'] = false;

        return $alerta;
    }

    private function vincularAlerta(array $alerta, array $polizas): array
    {
        $poliza = $this->buscarPolizaPorOperacion($alerta['id_operacion'] ?? null, $polizas)
            ?? $this->buscarPoliza($alerta['no_poliza'] ?? '', $polizas);
        $clienteValido = ! empty($alerta['no_cliente'])
            && $this->clienteEnPolizas((string) $alerta['no_cliente'], $polizas);

        if ($poliza) {
            $alerta['no_poliza'] = $poliza['no_poliza'] ?? $alerta['no_poliza'];
            $alerta['no_cliente'] = $poliza['no_cliente'] ?? ($alerta['no_cliente'] ?? null);
            $alerta['__vinculada'] = true;
        } else {
            $alerta['no_poliza'] = null;
        }

        if (! $poliza && ! $clienteValido) {
            $alerta['no_cliente'] = null;
        }

        if (! empty($alerta['no_cliente'])) {
            $alerta['__vinculada'] = true;
        }

        return $alerta;
    }

    private function buscarPolizaPorOperacion(mixed $idOperacion, array $polizas): ?array
    {
        $idOperacion = trim((string) $idOperacion);
        if ($idOperacion === '') {
            return null;
        }

        foreach ($polizas as $poliza) {
            if ((string) ($poliza['id_operacion'] ?? '') === $idOperacion) {
                return $poliza;
            }
        }

        return null;
    }

    private function clienteEnPolizas(string $noCliente, array $polizas): bool
    {
        foreach ($polizas as $poliza) {
            if ((string) ($poliza['no_cliente'] ?? '') === $noCliente) {
                return true;
            }
        }

        return false;
    }

    private function buscarPoliza(string $noPoliza, array $polizas): ?array
    {
        foreach ($polizas as $poliza) {
            if ((string) ($poliza['no_poliza'] ?? '') === $noPoliza) {
                return $poliza;
            }
        }

        return null;
    }

    private function grupoProductoValido(?string $grupo): ?string
    {
        $grupo = $this->normalizar($grupo);
        if ($grupo === '') {
            return null;
        }

        foreach ($this->config['productos']['grupos'] as $permitido) {
            if ($this->normalizar($permitido) === $grupo) {
                return $permitido;
            }
        }

        return null;
    }

    private function grupoProducto(array $poliza): string
    {
        $grupo = $poliza['grupo_producto'] ?? null;
        if ($grupo) {
            $grupo = $this->normalizar($grupo);
            foreach ($this->config['productos']['grupos'] as $permitido) {
                if ($this->normalizar($permitido) === $grupo) {
                    return $permitido;
                }
            }
        }

        $producto = (string) ($poliza['producto'] ?? '');
        foreach ($this->productos as $nombre => $data) {
            if ($this->normalizar($nombre) === $this->normalizar($producto)) {
                return $data['grupo'] === 'Otros' ? 'Otro' : $data['grupo'];
            }
        }

        $ramo = $this->normalizar($poliza['ramo'] ?? '');
        if ($ramo === $this->normalizar('Agrícola')) {
            return 'Agrícola';
        }
        if ($ramo === $this->normalizar('Ganadero')) {
            return 'Ganadero';
        }
        if ($ramo === $this->normalizar('Reaseguro tomado') || $ramo === $this->normalizar('Reaseguro')) {
            return 'Reaseguro Tomado';
        }

        return 'Otro';
    }

    private function canalNormalizado(array $fila): string
    {
        $agente = trim((string) ($fila['agente'] ?? ''));
        if ($agente !== '' && ! str_starts_with($agente, '0')) {
            return 'Agente';
        }

        return $this->normalizar($fila['canal'] ?? '') === 'agente' ? 'Agente' : 'Interno';
    }

    private function estadoNombre(?string $estado): string
    {
        $estado = trim((string) $estado);
        if ($estado === '') {
            return 'Nacional';
        }
        foreach ($this->estados as $nombre => $data) {
            if ($this->normalizar($nombre) === $this->normalizar($estado)) {
                return $nombre;
            }
        }

        return $estado;
    }

    private function cargarCatalogos(): void
    {
        $this->estados = EbrCatEstado::where('activo', true)->get()->keyBy('estado')->map(fn ($e) => $e->toArray())->all();
        $this->productos = EbrCatProducto::where('activo', true)->get()->keyBy('producto')->map(fn ($p) => $p->toArray())->all();
        $this->actividades = EbrCatActividad::where('activo', true)->get()->keyBy('ocupacion_giro')->map(fn ($a) => $a->toArray())->all();
        $this->paises = EbrCatPais::where('activo', true)->get()->keyBy('pais')->map(fn ($p) => $p->toArray())->all();
    }

    private function normalizarMitigantes(?array $guardados): array
    {
        $catalogo = $this->config['mitigantes'];
        $niveles = $this->config['niveles_efectividad'];

        $mitigantes = [];
        foreach ($catalogo as $base) {
            $nivel = $base['nivel'];

            if (! array_key_exists($nivel, $niveles)) {
                $nivel = 'Optimizado';
            }

            $efectividad = (float) ($niveles[$nivel] ?? 1);

            $mitigantes[] = [
                'id' => (int) $base['id'],
                'nombre' => $base['nombre'],
                'scope' => $base['scope'],
                'max' => (float) $base['max'],
                'nivel' => $nivel,
                'efectividad' => $efectividad,
                'efecto' => round((float) $base['max'] * $efectividad, 6),
            ];
        }

        return $mitigantes;
    }

    private function efectoMitigante(array $mitigantes, string $scope): float
    {
        $efecto = 0.0;
        foreach ($mitigantes as $mitigante) {
            if (($mitigante['scope'] ?? '') === $scope) {
                $efecto += (float) ($mitigante['efecto'] ?? 0);
            }
        }

        return min(1.0, max(0.0, $efecto));
    }

    private function clientesUnicos(array $polizas): array
    {
        $clientes = [];
        foreach ($polizas as $poliza) {
            $clientes[(string) ($poliza['no_cliente'] ?? $poliza['no_poliza'])] = true;
        }

        return $clientes;
    }

    private function polizasUnicas(array $polizas): array
    {
        $unicas = [];
        foreach ($polizas as $poliza) {
            $unicas[(string) ($poliza['no_poliza'] ?? '')] = true;
        }

        return $unicas;
    }

    private function montoMxn(array $fila, string $campo): float
    {
        $monto = (float) ($fila[$campo] ?? 0);
        $tipoCambio = (float) ($fila['tipo_cambio'] ?? 1);

        return $monto * ($tipoCambio > 0 ? $tipoCambio : 1);
    }

    private function metricaMayor(array $scores): string
    {
        $mayor = null;
        $valor = -1.0;
        foreach ($scores as $metrica => $score) {
            if ((float) $score > $valor) {
                $valor = (float) $score;
                $mayor = $metrica;
            }
        }

        return $mayor ?? 'clientes';
    }

    private function dividir(float|int $a, float|int $b): float
    {
        return $b > 0 ? (float) $a / (float) $b : 0.0;
    }

    private function limitar5(float $valor): float
    {
        return max(0, min(5, $valor));
    }

    private function nivel(float $score): string
    {
        $escala = $this->config['escala'];
        if ($score > $escala['muy_alto']) {
            return 'Muy Alto Riesgo';
        }
        if ($score > $escala['alto']) {
            return 'Alto Riesgo';
        }
        if ($score > $escala['medio']) {
            return 'Medio Riesgo';
        }

        return 'Bajo Riesgo';
    }

    private function normalizar(?string $texto): string
    {
        $texto = trim((string) $texto);
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        $resultado = preg_replace('/\s+/', ' ', $texto);

        return $resultado === null ? '' : $resultado;
    }

    private function advertencia(string $mensaje): void
    {
        $this->advertencias[] = $mensaje;
    }
}
