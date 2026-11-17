<?php

namespace App\Services\PLD;

use App\Models\CatFormaPagos;
use App\Models\CatParametriaPLD;
use App\Models\Clientes\TbClientes;
use App\Models\State\ResultadoAnalisisPago;
use App\Models\TbOperaciones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalisisPagosService
{
    public function __construct(private ?TipoCambioService $tipoCambioService = null) {}

    private function tipoCambio(): TipoCambioService
    {
        return $this->tipoCambioService ??= app(TipoCambioService::class);
    }

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
        // Spec Patrón 2 Monto: cualquier pago individual en efectivo por monto
        // >= 7,500 USD o equivalente en MN. La operación puede tener uno o varios
        // pagos: basta con que uno de los pagos en efectivo alcance el umbral.
        $pagosEfectivo = collect($pagos)->filter(
            fn ($pago) => (int) ($pago['IDFormaPago'] ?? 0) === 1
        );

        if ($pagosEfectivo->isEmpty()) {
            return;
        }

        // Umbral ID 1 (Operaciones relevantes) en USD vía Banxico.
        $umbralRelevante = (float) CatParametriaPLD::getOperacionesRelevantes();
        $pagosCumplen = [];
        $pagosEvaluados = [];

        foreach ($pagosEfectivo as $pago) {
            $monedaPago = $pago['IDMoneda'] ?? $operacion->IDMoneda;
            $fechaPago = $pago['FechaPago'] ?? $operacion->FechaEmision;
            $tcReal = $this->resolverTipoCambioReal($pago, $monedaPago, $fechaPago);
            $montoEnUSD = $this->convertirAUSD((float) $pago['Monto'], $monedaPago, $tcReal, $fechaPago);

            if ($montoEnUSD === null) {
                $resultado->motivos[] = 'Monto relevante no evaluado para el pago '.($pago['IDOperacionPago'] ?? 'sin ID').': sin tipo de cambio real (TC no disponible)';
                continue;
            }

            $detalle = [
                'IDOperacionPago' => $pago['IDOperacionPago'] ?? null,
                'monto' => (float) $pago['Monto'],
                'moneda' => $monedaPago,
                'monto_usd' => $montoEnUSD,
                'tipo_cambio' => $tcReal,
                'fecha_pago' => $fechaPago,
                'cumple_umbral' => $montoEnUSD >= $umbralRelevante,
            ];

            $pagosEvaluados[] = $detalle;

            if ($detalle['cumple_umbral']) {
                $pagosCumplen[] = $detalle;
            }
        }

        $resultado->detallesMontoRelevante = $pagosEvaluados;

        if ($pagosCumplen === []) {
            return;
        }

        // Una sola alerta por operación: se toma el pago que cumple con el mayor
        // equivalente en USD para el monto relevante reportado.
        usort($pagosCumplen, fn ($a, $b) => $b['monto_usd'] <=> $a['monto_usd']);
        $principal = $pagosCumplen[0];

        $resultado->esMontoRelevante = true;
        $resultado->montoRelevanteUSD = $principal['monto_usd'];
        $resultado->tipoCambioUSD = (float) ($principal['tipo_cambio'] ?? 0);

        $totalPagos = count($pagos);
        $totalCumplen = count($pagosCumplen);

        if ($totalPagos === 1) {
            $descripcionPago = 'Pago único en efectivo por '.number_format($principal['monto'], 2).' '.$principal['moneda'];
        } elseif ($totalCumplen === 1) {
            $descripcionPago = 'Pago en efectivo por '.number_format($principal['monto'], 2).' '.$principal['moneda'].' (uno de '.$totalPagos.' pagos de la operación)';
        } else {
            $descripcionPago = $totalCumplen.' pagos en efectivo alcanzaron el umbral; el mayor fue de '.number_format($principal['monto'], 2).' '.$principal['moneda'].' (operación con '.$totalPagos.' pagos)';
        }

        $resultado->alertasGenerar[] = [
            'patron' => self::PATRON_MONTO_RELEVANTE,
            'descripcion' => 'Operaciones por monto',
            'razones' => $descripcionPago.' (equivale a '.number_format($principal['monto_usd'], 2).' USD con la FIX publicada el día hábil anterior), igual o mayor al umbral de '.number_format($umbralRelevante, 2).' USD.',
            'monto_usd' => $principal['monto_usd'],
            'genera_reporte' => true,
        ];

        $resultado->reportesRegulatorios[] = [
            'patron' => self::PATRON_MONTO_RELEVANTE,
            'monto_usd' => $principal['monto_usd'],
            'forma_pago' => 'Efectivo',
        ];
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
     * Resuelve el tipo de cambio aplicable a un pago conforme a la regla del
     * DOF: FIX publicada el día hábil anterior a la fecha del pago.
     *
     * El TipoCambio capturado en tbOperacionesPagos se conserva como dato de
     * captura, pero no se usa para conversiones legales.
     */
    private function resolverTipoCambioReal(array $pago, $monedaPago, $fechaPago): ?float
    {
        $moneda = strtoupper(trim((string) $monedaPago));

        if ($moneda === 'USD') {
            // La comparación del patrón Monto es en USD: no requiere FIX.
            return null;
        }

        if ($moneda === 'MXN') {
            return $this->tipoCambio()->obtenerTipoCambioParaOperacion($fechaPago);
        }

        Log::error('[PLD] Moneda no soportada para conversión: '.$moneda.'. No se evalúa monto.', [
            'pago' => $pago['IDOperacionPago'] ?? null,
        ]);

        return null;
    }

    public function convertirAUSD(float $monto, $idMoneda, ?float $tipoCambioReal = null, $fechaPago = null): ?float
    {
        return $this->tipoCambio()->convertirAUSD($monto, $idMoneda, $tipoCambioReal, $fechaPago);
    }

    private function convertirAMXN(float $monto, $idMoneda, ?float $tipoCambioReal = null, $fechaPago = null): ?float
    {
        return $this->tipoCambio()->convertirAMXN($monto, $idMoneda, $tipoCambioReal, $fechaPago);
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

            // Convertir PrimaTotal a USD con la FIX aplicable a la fecha de emisión.
            // Sin TC real no se cierra automáticamente.
            $primaTotalUSD = $this->convertirAUSD(
                (float) $operacion->PrimaTotal,
                $operacion->IDMoneda,
                null,
                $operacion->FechaEmision
            );

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
            $evidencias['detalle_monto_relevante'] = $resultado->detallesMontoRelevante;
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
