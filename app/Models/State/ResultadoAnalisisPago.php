<?php

namespace App\Models\State;

class ResultadoAnalisisPago
{
    // Alerta 1: Pagos fraccionados
    public bool $esFraccionado = false;

    public bool $esFraccionadoDescartado = false;

    public array $detallesFraccionado = [];

    // Alerta 2: Pagos acumulados en efectivo
    public bool $esAcumuladoEfectivo = false;

    public bool $todosEfectivo = false;

    // Alerta 3: Monto relevante (operación relevante)
    public bool $esMontoRelevante = false;

    public float $montoRelevanteUSD = 0;

    public float $tipoCambioUSD = 0;

    // Alerta 4: PPE
    public bool $esPPE = false;

    public ?string $tipoRelacionPPE = null;

    // Alerta 5: Monto inusual (prima inusual)
    public bool $esMontoInusual = false;

    public float $limiteInferior = 0;

    public float $limiteSuperior = 0;

    // Estado general
    public bool $operacionTotalmentePagada = false;

    public float $saldoPendiente = 0;

    public float $totalPagado = 0;

    // Resumen de alertas
    public array $alertasGenerar = [];

    public array $alertasDescartar = [];

    public array $reportesRegulatorios = [];

    // Propiedades para persistencia
    public ?string $estatusFinal = null; // 'Generado', 'Cerrado', 'Reportado'

    public array $evidencias = [];

    public array $motivos = [];
}
