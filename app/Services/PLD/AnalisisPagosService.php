<?php

namespace App\Services\PLD;

use App\Models\CatFormaPagos;
use App\Models\CatParametriaPLD;
use App\Models\Clientes\TbClientes;
use App\Models\State\ResultadoAnalisisPago;
use App\Models\TbOperaciones;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalisisPagosService
{
    // Constantes para patrones de alerta
    const PATRON_FRACCIONADO = 'Fraccionado';

    const PATRON_ACUMULADO_EFECTIVO = 'Acumulado Efectivo';

    const PATRON_MONTO_RELEVANTE = 'Monto';

    const PATRON_PPE = 'PPE';

    const PATRON_MONTO_INUSUAL = 'Monto Inusual';

    const PATRON_CANCELACION = 'Cancelacion';

    // Constantes para estatus
    const ESTATUS_GENERADO = 'Generado';

    const ESTATUS_CERRADO = 'Cerrado';

    const ESTATUS_REPORTADO = 'Reportado';

    public function analizarPagos(TbOperaciones $operacion, array $pagos, ?TbClientes $cliente): ResultadoAnalisisPago
    {
        $resultado = new ResultadoAnalisisPago;

        // Datos básicos
        $resultado->totalPagado = collect($pagos)->sum('Monto');
        $resultado->saldoPendiente = $operacion->PrimaTotal - $resultado->totalPagado;
        $resultado->operacionTotalmentePagada = $resultado->saldoPendiente <= 0;

        // Análisis 1: Pagos fraccionados
        $this->analizarPagosFraccionados($operacion, $pagos, $resultado);

        // Análisis 2: Pagos acumulados en efectivo
        $this->analizarPagosAcumuladosEfectivo($operacion, $pagos, $resultado);

        // Análisis 3: Monto
        $this->analizarMontoRelevante($operacion, $pagos, $resultado);

        // Análisis 4: PPE
        $this->analizarPPE($operacion, $resultado, $cliente);

        // Análisis 5: Monto inusual (patrón "Monto Inusual")
        $this->analizarMontoInusual($operacion, $resultado, $pagos, $cliente);

        // Determinar estatus final
        $this->determinarEstatusFinal($resultado, $operacion);

        return $resultado;
    }

    private function analizarPagosFraccionados(TbOperaciones $operacion, array $pagos, ResultadoAnalisisPago $resultado): void
    {
        // Spec Patrón 1 Fraccionado: múltiples pagos en cualquier forma para liquidar
        // la prima => alerta, EXCEPTO si uno de los pagos corresponde a gastos de
        // emisión con tolerancia ±10%.
        if (count($pagos) <= 1 || ! $resultado->operacionTotalmentePagada) {
            return;
        }

        $toleranciaPorcentaje = (float) CatParametriaPLD::getToleranciaPagosFraccionados();
        $gastosEmision = (float) ($operacion->GastosEmision ?? 0);
        $detalles = [];
        $coincideGastos = false;

        foreach ($pagos as $pago) {
            $montoPago = (float) $pago['Monto'];
            if ($gastosEmision > 0) {
                $tolerancia = abs($gastosEmision) * ($toleranciaPorcentaje / 100);
                $dentro = abs($montoPago - $gastosEmision) <= $tolerancia;
            } else {
                $tolerancia = 0;
                $dentro = false;
            }
            if ($dentro) {
                $coincideGastos = true;
            }
            $detalles[] = [
                'monto_pago' => $montoPago,
                'gastos_emision' => $gastosEmision,
                'tolerancia_pct' => $toleranciaPorcentaje,
                'es_gastos_emision' => $dentro,
                'diferencia' => abs($montoPago - $gastosEmision),
            ];
        }

        $resultado->detallesFraccionado = $detalles;

        // Si algún pago fue por gastos de emisión (±10%), la alerta NO se genera.
        if ($coincideGastos) {
            $resultado->esFraccionado = false;
            $resultado->esFraccionadoDescartado = true;
            $resultado->alertasDescartar[] = self::PATRON_FRACCIONADO;
            $resultado->motivos[] = 'Pagos fraccionados no alertados: uno de los pagos corresponde a gastos de emisión (±'.$toleranciaPorcentaje.'%)';

            return;
        }

        $resultado->esFraccionado = true;
        $montos = collect($pagos)->map(fn ($p) => number_format((float) $p['Monto'], 2).' '.($p['IDMoneda'] ?? $operacion->IDMoneda))->implode(' + ');
        $resultado->alertasGenerar[] = [
            'patron' => self::PATRON_FRACCIONADO,
            'descripcion' => 'Pagos fraccionados detectados',
            'razones' => 'Prima liquidada en '.count($pagos).' pagos ('.$montos.'). Ningún pago corresponde a gastos de emisión ('.number_format((float) ($operacion->GastosEmision ?? 0), 2).', margen 10%).',
        ];
    }

    private function analizarPagosAcumuladosEfectivo(TbOperaciones $operacion, array $pagos, ResultadoAnalisisPago $resultado): void
    {
        if (! $resultado->operacionTotalmentePagada) {
            return;
        }

        // Verificar si TODOS los pagos son en efectivo
        $pagosEfectivo = collect($pagos)->filter(fn ($pago) => $pago['IDFormaPago'] == 1);
        $resultado->todosEfectivo = $pagosEfectivo->count() === count($pagos);

        if ($resultado->todosEfectivo && count($pagos) > 1) {
            $resultado->esAcumuladoEfectivo = true;
            $montos = collect($pagos)->map(fn ($p) => number_format((float) $p['Monto'], 2).' '.($p['IDMoneda'] ?? $operacion->IDMoneda))->implode(' + ');
            $resultado->alertasGenerar[] = [
                'patron' => self::PATRON_ACUMULADO_EFECTIVO,
                'descripcion' => 'Acumulados',
                'razones' => 'Prima liquidada en '.count($pagos).' pagos, todos en efectivo ('.$montos.').',
            ];
        }
    }

    private function analizarMontoRelevante(TbOperaciones $operacion, array $pagos, ResultadoAnalisisPago $resultado): void
    {
        // Spec Patrón 2 Monto: pago en UNA SOLA EXHIBICIÓN de la prima, en efectivo,
        // por monto >= 7,500 USD o equivalente en MN.
        if (count($pagos) !== 1) {
            return;
        }
        $pago = $pagos[0];
        if ((int) ($pago['IDFormaPago'] ?? 0) !== 1) {
            return; // solo efectivo
        }
        // Solo pagos en efectivo. Umbral ID 1 (Operaciones relevantes) en USD vía Banxico.
        $pagosEfectivo = collect($pagos)->filter(fn ($pago) => $pago['IDFormaPago'] == 1);
        $umbralRelevante = CatParametriaPLD::getOperacionesRelevantes();

        foreach ($pagosEfectivo as $pago) {
            $monedaPago = $pago['IDMoneda'] ?? $operacion->IDMoneda;
            $fechaPago = $pago['FechaPago'] ?? $operacion->FechaEmision;
            $tcReal = $this->resolverTipoCambioReal($pago, $monedaPago, $fechaPago);
            $montoEnUSD = $this->convertirAUSD((float) $pago['Monto'], $monedaPago, $tcReal, $fechaPago);

            if ($montoEnUSD === null) {
                $resultado->motivos[] = 'Monto relevante no evaluado: sin tipo de cambio real (TC no disponible)';
                continue;
            }

            if ($montoEnUSD >= $umbralRelevante) {
                $resultado->esMontoRelevante = true;
                $resultado->montoRelevanteUSD = $montoEnUSD;

                $resultado->alertasGenerar[] = [
                    'patron' => self::PATRON_MONTO_RELEVANTE,
                    'descripcion' => 'Operaciones por monto',
                    'razones' => 'Pago único en efectivo por '.number_format((float) $pago['Monto'], 2).' '.$monedaPago.' (equivale a '.number_format($montoEnUSD, 2).' USD con tipo de cambio del día del pago), igual o mayor al umbral de '.number_format($umbralRelevante, 2).' USD.',
                    'monto_usd' => $montoEnUSD,
                    'genera_reporte' => true,
                ];

                $resultado->reportesRegulatorios[] = [
                    'patron' => self::PATRON_MONTO_RELEVANTE,
                    'monto_usd' => $montoEnUSD,
                    'forma_pago' => 'Efectivo',
                ];
                break; // Una alerta por pago es suficiente
            }
        }
    }

    private function analizarPPE(TbOperaciones $operacion, ResultadoAnalisisPago $resultado, ?TbClientes $cliente): void
    {
        // Aquí se debería implementar la lógica de detección PPE
        // Por ahora, simulamos una detección básica basada en el cliente
        if ($cliente && $cliente->EsPPEActivo) {
            $resultado->esPPE = true;
            $resultado->alertasGenerar[] = [
                'patron' => self::PATRON_PPE,
                'descripcion' => 'Persona Políticamente Expuesta',
                'razones' => 'Operación de cliente catalogado como persona políticamente expuesta.',
            ];
        }
    }

    private function analizarMontoInusual(TbOperaciones $operacion, ResultadoAnalisisPago $resultado, array $pagos, ?TbClientes $cliente): void
    {
        // Spec Patrón 7 Monto Inusual: al emitir una póliza se calcula promedio y
        // desviación estándar del historial del cliente; intervalo =
        // [promedio - 3σ, promedio + 3σ]. Requiere >= 3 pólizas emitidas y pagadas
        // y σ != 0.
        if (! $resultado->operacionTotalmentePagada) {
            return;
        }

        $idCliente = $cliente?->IDCliente ?? $operacion->IDCliente;
        if (empty($idCliente)) {
            return;
        }

        // Historial: operaciones previas del mismo cliente, excluyendo la actual,
        // consideradas emitidas y pagadas (total pagado >= PrimaTotal).
        $historial = TbOperaciones::where('IDCliente', $idCliente)
            ->where('IDOperacion', '!=', $operacion->IDOperacion ?? 0)
            ->get(['IDOperacion', 'PrimaTotal']);

        $primasHistoricas = [];
        foreach ($historial as $opHist) {
            $totalPagadoHist = (float) DB::table('tbOperacionesPagos')
                ->where('IDOperacion', $opHist->IDOperacion)
                ->sum('Monto');
            if ($totalPagadoHist >= (float) $opHist->PrimaTotal && (float) $opHist->PrimaTotal > 0) {
                $primasHistoricas[] = (float) $opHist->PrimaTotal;
            }
        }

        if (count($primasHistoricas) < 3) {
            $resultado->motivos[] = 'Monto inusual no evaluado: el cliente requiere al menos tres pólizas emitidas y pagadas ('.count($primasHistoricas).' encontradas)';

            return;
        }

        $n = count($primasHistoricas);
        $promedio = array_sum($primasHistoricas) / $n;
        $varianza = 0.0;
        foreach ($primasHistoricas as $v) {
            $varianza += pow($v - $promedio, 2);
        }
        $varianza /= $n; // desviación estándar poblacional
        $desviacion = sqrt($varianza);

        if ($desviacion == 0.0) {
            $resultado->motivos[] = 'Monto inusual no evaluado: desviación estándar igual a cero';

            return;
        }

        $limiteInferior = $promedio - 3 * $desviacion;
        $limiteSuperior = $promedio + 3 * $desviacion;

        $resultado->limiteInferior = $limiteInferior;
        $resultado->limiteSuperior = $limiteSuperior;

        $primaEmitida = (float) $operacion->PrimaTotal;

        if ($primaEmitida < $limiteInferior || $primaEmitida > $limiteSuperior) {
            $resultado->esMontoInusual = true;
            $moneda = trim((string) ($operacion->IDMoneda ?? ''));
            $resultado->alertasGenerar[] = [
                'patron' => self::PATRON_MONTO_INUSUAL,
                'descripcion' => 'Prima fuera del comportamiento histórico del cliente',
                'razones' => 'Prima de '.number_format($primaEmitida, 2).' '.$moneda.', fuera del comportamiento del cliente (habitual entre '.number_format($limiteInferior, 2).' '.$moneda.' y '.number_format($limiteSuperior, 2).' '.$moneda.' según sus últimas '.$n.' pólizas pagadas).',
                'promedio' => $promedio,
                'desviacion_estandar' => $desviacion,
                'n_historicas' => $n,
            ];
        }
    }

    /**
     * Obtiene el FIX Banxico REAL (MXN por USD) a una fecha dada.
     * Fuente 1: tabla histórica local catTipoCambio (Fecha, DOFDolar).
     * Fuente 2: API Banxico a esa misma fecha (retrocede a día hábil).
     * Si ambas fallan retorna null: NUNCA se inventa un tipo de cambio.
     */
    private function obtenerFixReal($fecha): ?float
    {
        try {
            $dia = $fecha ? \Carbon\Carbon::parse($fecha) : now();
        } catch (\Throwable $e) {
            $dia = now();
        }

        // Retroceder a día hábil (FIX no se publica fines de semana)
        $intentos = 0;
        while ($dia->isWeekend() && $intentos < 5) {
            $dia->subDay();
            $intentos++;
        }
        $fechaStr = $dia->format('Y-m-d');

        // 1) Tabla histórica local
        try {
            $local = DB::table('catTipoCambio')->where('Fecha', $fechaStr)->value('DOFDolar');
            if (is_numeric($local) && (float) $local > 0) {
                return (float) $local;
            }
        } catch (\Throwable $e) {
            Log::warning('[PLD] No se pudo leer catTipoCambio: '.$e->getMessage());
        }

        // 2) Banxico a esa fecha; se persiste en catTipoCambio para futuras consultas
        try {
            $token = config('services.banxico.token');
            if (empty($token)) {
                Log::error('[PLD] Sin token Banxico (BANXICO_TOKEN). No hay TC real disponible.');

                return null;
            }

            $client = new Client;
            $url = "https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/{$fechaStr}/{$fechaStr}?token={$token}";
            $response = $client->request('GET', $url, ['timeout' => 15]);
            $data = json_decode($response->getBody(), true);
            $dato = $data['bmx']['series'][0]['datos'][0]['dato'] ?? null;

            if (is_numeric($dato) && (float) $dato > 0) {
                try {
                    DB::table('catTipoCambio')->updateOrInsert(
                        ['Fecha' => $fechaStr],
                        ['DOFDolar' => (float) $dato]
                    );
                } catch (\Throwable $e) {
                    Log::warning('[PLD] No se pudo persistir FIX en catTipoCambio: '.$e->getMessage());
                }

                return (float) $dato;
            }

            Log::error("[PLD] Banxico sin dato FIX para {$fechaStr}. No se evalúa monto.");
        } catch (\Throwable $e) {
            Log::error("[PLD] Banxico no disponible para {$fechaStr}: ".$e->getMessage().'. No se evalúa monto.');
        }

        return null;
    }

    /**
     * Resuelve el tipo de cambio REAL (MXN por USD) para un pago.
     * - Pago en USD: se usa el TipoCambio capturado en el pago (obligatorio).
     * - Pago en MXN: se usa el FIX Banxico a FechaPago (histórico real).
     * Retorna null si no hay TC real (el llamador debe omitir la evaluación).
     */
    private function resolverTipoCambioReal(array $pago, $monedaPago, $fechaPago): ?float
    {
        $moneda = strtoupper(trim((string) $monedaPago));

        if ($moneda === 'USD') {
            $tc = isset($pago['TipoCambio']) ? (float) $pago['TipoCambio'] : 0;
            if ($tc > 0) {
                return $tc;
            }
            Log::error('[PLD] Pago en USD sin TipoCambio válido. No se evalúa monto.', ['pago' => $pago['IDOperacionPago'] ?? null]);

            return null;
        }

        if ($moneda === 'MXN') {
            return $this->obtenerFixReal($fechaPago);
        }

        Log::error('[PLD] Moneda no soportada para conversión: '.$moneda.'. No se evalúa monto.');

        return null;
    }

    public function convertirAUSD(float $monto, $idMoneda, ?float $tipoCambioReal = null, $fechaPago = null): ?float
    {
        // Normalizar a string mayúsculas
        $moneda = strtoupper(trim((string) $idMoneda));

        if ($moneda === 'USD') {
            return $monto;
        }

        if ($moneda === 'MXN') {
            $tc = $tipoCambioReal ?? $this->obtenerFixReal($fechaPago);

            return ($tc && $tc > 0) ? $monto / $tc : null;
        }

        // Otras monedas: sin TC real conocido, no se convierte (null, no inventar)
        return null;
    }

    private function convertirAMXN(float $monto, $idMoneda, ?float $tipoCambioReal = null, $fechaPago = null): ?float
    {
        // Normalizar a string mayúsculas
        $moneda = strtoupper(trim((string) $idMoneda));

        if ($moneda === 'MXN') {
            return $monto;
        }

        if ($moneda === 'USD') {
            $tc = $tipoCambioReal ?? $this->obtenerFixReal($fechaPago);

            return ($tc && $tc > 0) ? $monto * $tc : null;
        }

        // Otras monedas: sin TC real conocido, no se convierte (null, no inventar)
        return null;
    }

    private function determinarEstatusFinal(ResultadoAnalisisPago $resultado, TbOperaciones $operacion): void
    {
        // Si hay reportes regulatorios, el estatus final es 'Reportado'
        if (! empty($resultado->reportesRegulatorios)) {
            $resultado->estatusFinal = self::ESTATUS_REPORTADO;

            return;
        }

        // Si hay alertas para generar
        if (! empty($resultado->alertasGenerar)) {
            // Verificar si el monto total de la prima es menor al monto mínimo de alerta (USD vía Banxico)
            $montoMinimoAlertaUSD = CatParametriaPLD::getMontoMinimoAlerta();

            // Convertir PrimaTotal a USD con TC real (FIX a FechaEmision). Sin TC no se cierra.
            $monedaOp = $operacion->IDMoneda;
            $tcOp = (strtoupper(trim((string) $monedaOp)) === 'USD')
                ? null // Prima en USD no requiere TC
                : $this->obtenerFixReal($operacion->FechaEmision);
            $primaTotalUSD = $this->convertirAUSD((float) $operacion->PrimaTotal, $monedaOp, $tcOp, $operacion->FechaEmision);

            // Si la prima total es menor al umbral mínimo, se cierra automáticamente
            // donde el riesgo se mitiga si el monto es bajo. Sin TC real no se cierra.
            if ($primaTotalUSD === null) {
                $resultado->motivos[] = 'Cierre automático no aplicado: sin tipo de cambio real (TC no disponible)';
                $resultado->estatusFinal = self::ESTATUS_GENERADO;
            } elseif ($primaTotalUSD < $montoMinimoAlertaUSD) {
                $resultado->estatusFinal = self::ESTATUS_CERRADO;
            } else {
                $resultado->estatusFinal = self::ESTATUS_GENERADO;
            }

            return;
        }

        // Si todas las alertas se descartaron
        if (! empty($resultado->alertasDescartar) && empty($resultado->alertasGenerar)) {
            $resultado->estatusFinal = self::ESTATUS_CERRADO;

            return;
        }

        // Por defecto, generado
        $resultado->estatusFinal = self::ESTATUS_GENERADO;
    }

    public function generarEvidencias(ResultadoAnalisisPago $resultado, array $pagos): array
    {
        $evidencias = [
            'total_pagos' => count($pagos),
            'total_pagado' => $resultado->totalPagado,
            'saldo_pendiente' => $resultado->saldoPendiente,
            'operacion_pagada' => $resultado->operacionTotalmentePagada,
            'detalle_pagos' => [],
        ];

        foreach ($pagos as $pago) {
            $formaPago = CatFormaPagos::find($pago['IDFormaPago']);
            $evidencias['detalle_pagos'][] = [
                'monto' => $pago['Monto'],
                'moneda' => $pago['IDMoneda'],
                'forma_pago' => $formaPago->FormaPago ?? 'Desconocido',
                'fecha_pago' => $pago['FechaPago'],
            ];
        }

        if ($resultado->esFraccionado) {
            $evidencias['analisis_fraccionado'] = $resultado->detallesFraccionado;
        }

        if ($resultado->esMontoRelevante) {
            $evidencias['monto_relevante_usd'] = $resultado->montoRelevanteUSD;
            $evidencias['tipo_cambio_usd'] = $resultado->tipoCambioUSD;
        }

        if ($resultado->esMontoInusual) {
            $evidencias['monto_inusual'] = [
                'limite_inferior' => $resultado->limiteInferior,
                'limite_superior' => $resultado->limiteSuperior,
            ];
        }

        $resultado->evidencias = $evidencias;

        return $evidencias;
    }
}
