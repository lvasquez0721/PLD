<?php

namespace App\Services\PLD;

use App\Models\CatFormaPagos;
use App\Models\CatParametriaPLD;
use App\Models\State\ResultadoAnalisisPago;
use App\Models\Clientes\TbClientes;
use App\Models\TbOperaciones;
use GuzzleHttp\Client;

class AnalisisPagosService
{
    // Constantes para patrones de alerta
    const PATRON_FRACCIONADO = 'Fraccionado';

    const PATRON_ACUMULADO_EFECTIVO = 'AcumuladoEfectivo';

    const PATRON_MONTO_RELEVANTE = 'MontoRelevante';

    const PATRON_PPE = 'PPE';

    const PATRON_MONTO_INUSUAL = 'MontoInusual';

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

        // Análisis 3: Monto relevante
        $this->analizarMontoRelevante($operacion, $pagos, $resultado);

        // Análisis 4: PPE
        $this->analizarPPE($operacion, $resultado, $cliente);

        // Análisis 5: Monto inusual
        $this->analizarMontoInusual($operacion, $resultado);

        // Determinar estatus final
        $this->determinarEstatusFinal($resultado);

        return $resultado;
    }

    private function analizarPagosFraccionados(TbOperaciones $operacion, array $pagos, ResultadoAnalisisPago $resultado): void
    {
        if (count($pagos) <= 1 || ! $resultado->operacionTotalmentePagada) {
            return;
        }

        $resultado->esFraccionado = true;

        // Obtener tolerancia
        $toleranciaConfig = CatParametriaPLD::where('IDParametro', 15)->first();
        $toleranciaPorcentaje = $toleranciaConfig ? $toleranciaConfig->Valor : 0;

        // Validar pagos individuales contra gastos + prima
        $montoEsperadoPago = $operacion->PrimaTotal + $operacion->GastosEmision;
        $tolerancia = $montoEsperadoPago * ($toleranciaPorcentaje / 100);
        $limiteInferior = $montoEsperadoPago - $tolerancia;
        $limiteSuperior = $montoEsperadoPago + $tolerancia;

        $pagosDentroTolerancia = 0;
        $detalles = [];

        foreach ($pagos as $pago) {
            $montoPago = (float) $pago['Monto'];
            $dentroTolerancia = ($montoPago >= $limiteInferior && $montoPago <= $limiteSuperior);

            if ($dentroTolerancia) {
                $pagosDentroTolerancia++;
            }

            $detalles[] = [
                'monto_pago' => $montoPago,
                'esperado' => $montoEsperadoPago,
                'dentro_tolerance' => $dentroTolerancia,
                'diferencia' => abs($montoPago - $montoEsperadoPago),
            ];
        }

        $resultado->detallesFraccionado = $detalles;

        // Si todos los pagos están dentro de tolerancia, se descarta la alerta
        if ($pagosDentroTolerancia === count($pagos)) {
            $resultado->esFraccionadoDescartado = true;
            $resultado->alertasDescartar[] = self::PATRON_FRACCIONADO;
            $resultado->motivos[] = 'Pagos fraccionados dentro de tolerancia permitida';
        } else {
            $resultado->alertasGenerar[] = [
                'patron' => self::PATRON_FRACCIONADO,
                'descripcion' => 'Pagos fraccionados detectados',
                'razones' => 'La operación tiene múltiples pagos que exceden la tolerancia permitida',
            ];
        }
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
            $resultado->alertasGenerar[] = [
                'patron' => self::PATRON_ACUMULADO_EFECTIVO,
                'descripcion' => 'Pagos acumulados en efectivo',
                'razones' => 'Operación totalmente pagada con múltiples pagos en efectivo',
            ];
        }
    }

    private function analizarMontoRelevante(TbOperaciones $operacion, array $pagos, ResultadoAnalisisPago $resultado): void
    {
        // Solo pagos en efectivo
        $pagosEfectivo = collect($pagos)->filter(fn ($pago) => $pago['IDFormaPago'] == 1);

        foreach ($pagosEfectivo as $pago) {
            $montoEnUSD = $this->convertirAUSD($pago['Monto'], $operacion->IDMoneda);

            if ($montoEnUSD >= 10000) { // Umbral PLD estándar (configurable)
                $resultado->esMontoRelevante = true;
                $resultado->montoRelevanteUSD = $montoEnUSD;

                $resultado->alertasGenerar[] = [
                    'patron' => self::PATRON_MONTO_RELEVANTE,
                    'descripcion' => 'Operación por monto relevante',
                    'razones' => "Monto en efectivo excede umbral PLD: USD {$montoEnUSD}",
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
                'razones' => 'Operación realizada por cliente PPE',
            ];
        }
    }

    private function analizarMontoInusual(TbOperaciones $operacion, ResultadoAnalisisPago $resultado): void
    {
        // Aquí se debería implementar la lógica de detección de montos inusuales
        // Por ahora, simulamos una detección basada en límites predefinidos

        // En el sistema real, esto vendría de catálculo histórico por cliente
        $limiteInferior = 50000; // Simulado
        $limiteSuperior = 500000; // Simulado

        if ($operacion->PrimaTotal < $limiteInferior || $operacion->PrimaTotal > $limiteSuperior) {
            $resultado->esMontoInusual = true;
            $resultado->limiteInferior = $limiteInferior;
            $resultado->limiteSuperior = $limiteSuperior;

            if ($resultado->operacionTotalmentePagada) {
                $resultado->alertasGenerar[] = [
                    'patron' => self::PATRON_MONTO_INUSUAL,
                    'descripcion' => 'Prima inusual',
                    'razones' => "Prima {$operacion->PrimaTotal} fuera de rango histórico [{$limiteInferior}, {$limiteSuperior}]",
                ];
            }
        }
    }

    private function convertirAUSD(float $monto, int $idMoneda): float
    {
        if ($idMoneda == 2) { // USD
            return $monto;
        }

        if ($idMoneda == 1) { // MXN - convertir a USD
            try {
                $client = new Client;

                // Obtener tipo de cambio del día hábil anterior
                $fecha = now()->subDay();
                while ($fecha->isWeekend()) {
                    $fecha->subDay();
                }

                $fechaFormateada = $fecha->format('Y-m-d');
                $url = "https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718/datos/{$fechaFormateada}/{$fechaFormateada}?token=dafb7f16f4ec83af4c269688bde5bab13903be80138f5d19773a7e83346c5aae";

                $response = $client->request('GET', $url);
                $data = json_decode($response->getBody(), true);

                $tipoCambio = $data['bmx']['series'][0]['datos'][0]['dato'] ?? 20.0;

                return $monto / (float) $tipoCambio;
            } catch (\Exception $e) {
                // Fallback a tipo de cambio fijo
                return $monto / 20.0;
            }
        }

        return $monto; // Otras monedas, sin conversión por ahora
    }

    private function determinarEstatusFinal(ResultadoAnalisisPago $resultado): void
    {
        // Si hay reportes regulatorios, el estatus final es 'Reportado'
        if (! empty($resultado->reportesRegulatorios)) {
            $resultado->estatusFinal = self::ESTATUS_REPORTADO;

            return;
        }

        // Si hay alertas para generar
        if (! empty($resultado->alertasGenerar)) {
            // Verificar si alguna alerta se debe cerrar automáticamente
            $montoMinimoPLD = 10000; // Umbral configurable

            $alertasSobreUmbral = collect($resultado->alertasGenerar)
                ->filter(fn ($alerta) => isset($alerta['monto_usd']) && $alerta['monto_usd'] >= $montoMinimoPLD);

            if ($alertasSobreUmbral->isEmpty() && $resultado->operacionTotalmentePagada) {
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

        $resultado->evidencias = $evidencias;

        return $evidencias;
    }
}
