<?php

namespace App\Services\PLD;

use Illuminate\Support\Collection;

/**
 * Calcula los indicadores económicos (primas) de un cliente a partir de sus
 * operaciones, separando emisiones de endosos de aumento, disminución y
 * cancelación.
 *
 * Reglas de negocio aplicadas:
 *  - Un endoso de aumento trae PrimaTotal positiva y uno de disminución la trae
 *    negativa, por lo que ambos suman (o restan) al balance de la póliza.
 *  - El endoso de cancelación se autocorreige en el API con la prima espejo
 *    (-Σ historial previo de la póliza), por lo que una póliza cancelada queda
 *    con balance final en 0.
 *  - PrimaTotal depende de IDMoneda y tbOperaciones no guarda tipo de cambio,
 *    por lo que los indicadores se calculan POR MONEDA para no sumar MXN con USD.
 *
 * Las categorías (emisión / aumento / disminución / cancelación / sin cambio)
 * son excluyentes entre sí: su suma es el total de operaciones.
 */
class IndicadoresOperacionesCliente
{
    public const TIPO_EMISION = 'Emisión';

    public const TIPO_AUMENTO = 'Endoso Aumento';

    public const TIPO_DISMINUCION = 'Endoso Disminución';

    public const TIPO_SIN_CAMBIO = 'Endoso Sin Cambio';

    public const TIPO_CANCELACION = 'Cancelación';

    /**
     * Calcula los indicadores del cliente.
     *
     * @param  Collection<int, \App\Models\TbOperaciones>  $operaciones
     * @return array<string, mixed>
     */
    public function calcular(Collection $operaciones): array
    {
        $porMoneda = [];

        foreach ($operaciones->groupBy(fn ($op) => $this->moneda($op)) as $moneda => $ops) {
            $porMoneda[$moneda] = $this->calcularPorMoneda($moneda, $ops->values());
        }

        uksort($porMoneda, fn ($a, $b) => $this->ordenarMonedas($a, $b));

        return [
            'total_operaciones' => $operaciones->count(),
            'moneda_principal' => $this->monedaPrincipal($porMoneda),
            'por_moneda' => $porMoneda,
        ];
    }

    /**
     * Clasifica una operación según el tipo de movimiento que representa.
     */
    public function tipoOperacion($op): string
    {
        if ($this->esCancelacion($op)) {
            return self::TIPO_CANCELACION;
        }

        if ($this->esEmision($op)) {
            return self::TIPO_EMISION;
        }

        $prima = $this->prima($op);

        if ($prima > 0) {
            return self::TIPO_AUMENTO;
        }

        if ($prima < 0) {
            return self::TIPO_DISMINUCION;
        }

        return self::TIPO_SIN_CAMBIO;
    }

    /**
     * Indica si la operación es una póliza cancelada (o endoso de cancelación).
     */
    public function esCancelacion($op): bool
    {
        return $this->booleano($op->cancelaPoliza ?? null)
            || $this->booleano($op->operacionCancelada ?? null)
            || $this->booleano($op->EsEndosoCancelacion ?? null);
    }

    /**
     * Indica si la operación es una emisión (no tiene folio de endoso).
     */
    public function esEmision($op): bool
    {
        return trim((string) ($op->FolioEndoso ?? '')) === '';
    }

    /**
     * Prima total de la operación (0 cuando no viene informada).
     */
    public function prima($op): float
    {
        return round((float) ($op->PrimaTotal ?? 0), 2);
    }

    /**
     * Indicadores de una sola moneda.
     *
     * @param  Collection<int, \App\Models\TbOperaciones>  $ops
     * @return array<string, mixed>
     */
    private function calcularPorMoneda(string $moneda, Collection $ops): array
    {
        $emisiones = $ops->filter(fn ($op) => $this->esEmision($op))->values();
        $endosos = $ops->filter(fn ($op) => ! $this->esEmision($op))->values();

        // Categorías excluyentes dentro de los endosos.
        $cancelaciones = $endosos->filter(fn ($op) => $this->esCancelacion($op))->values();
        $aumentos = $endosos->filter(fn ($op) => ! $this->esCancelacion($op) && $this->prima($op) > 0)->values();
        $disminuciones = $endosos->filter(fn ($op) => ! $this->esCancelacion($op) && $this->prima($op) < 0)->values();
        $sinCambio = $endosos->filter(fn ($op) => ! $this->esCancelacion($op) && $this->prima($op) === 0.0)->values();

        // Operaciones canceladas en general (emisiones canceladas + endosos).
        $operacionesCanceladas = $ops->filter(fn ($op) => $this->esCancelacion($op))->values();

        $sumaPrimaTotal = $this->sumaPrima($ops);
        $sumaPrimaEmisiones = $this->sumaPrima($emisiones);
        $sumaAumentos = $this->sumaPrima($aumentos);
        $sumaDisminuciones = $this->sumaPrima($disminuciones);
        $sumaCancelaciones = $this->sumaPrima($cancelaciones);
        $sumaGastosEmision = $this->sumaGastosEmision($ops);

        // Pagos del cliente en esta misma moneda.
        $totalPagado = 0.0;
        $numPagos = 0;
        foreach ($ops as $op) {
            foreach ($this->pagos($op) as $pago) {
                if ($this->monedaPago($pago) !== $moneda) {
                    continue;
                }
                $totalPagado += round((float) ($pago->Monto ?? 0), 2);
                $numPagos++;
            }
        }

        // Balance por póliza (emisión + endosos) dentro de la moneda.
        $balances = [];
        foreach ($ops as $op) {
            $folio = trim((string) ($op->FolioPoliza ?? ''));
            $folio = $folio === '' ? '(sin folio de póliza)' : $folio;
            $balances[$folio] = ($balances[$folio] ?? 0.0) + $this->prima($op);
        }
        $numPolizas = count($balances);
        $sumaBalances = round(array_sum($balances), 2);

        $primas = $ops->map(fn ($op) => $this->prima($op))->values();
        $primasEmision = $emisiones->map(fn ($op) => $this->prima($op))->values();
        $modaPrimaTotal = $this->modaPrimaTotal($ops);

        return [
            'moneda' => $moneda,
            'total_operaciones' => $ops->count(),
            'total_emisiones' => $emisiones->count(),
            'total_endosos' => $endosos->count(),
            'total_aumentos' => $aumentos->count(),
            'total_disminuciones' => $disminuciones->count(),
            'total_cancelaciones' => $cancelaciones->count(),
            'total_sin_cambio' => $sinCambio->count(),
            'total_operaciones_canceladas' => $operacionesCanceladas->count(),
            'num_polizas' => $numPolizas,

            // Primas
            'suma_prima_total' => $sumaPrimaTotal,
            'promedio_prima_total' => $this->promedio($sumaPrimaTotal, $ops->count()),
            'moda_prima_total' => $modaPrimaTotal['valor'],
            'moda_prima_total_frecuencia' => $modaPrimaTotal['frecuencia'],
            'suma_prima_emisiones' => $sumaPrimaEmisiones,
            'promedio_prima_emisiones' => $this->promedio($sumaPrimaEmisiones, $emisiones->count()),
            'suma_aumentos' => $sumaAumentos,
            'promedio_aumentos' => $this->promedio($sumaAumentos, $aumentos->count()),
            'suma_disminuciones' => $sumaDisminuciones,
            'promedio_disminuciones' => $this->promedio($sumaDisminuciones, $disminuciones->count()),
            'suma_prima_cancelaciones' => $sumaCancelaciones,
            'prima_max' => $primas->isEmpty() ? 0.0 : (float) $primas->max(),
            'prima_min' => $primas->isEmpty() ? 0.0 : (float) $primas->min(),
            'prima_max_emision' => $primasEmision->isEmpty() ? 0.0 : (float) $primasEmision->max(),
            'prima_min_emision' => $primasEmision->isEmpty() ? 0.0 : (float) $primasEmision->min(),
            'suma_gastos_emision' => $sumaGastosEmision,
            'promedio_gastos_emision' => $this->promedio($sumaGastosEmision, $ops->count()),

            // Balance por póliza
            'suma_balance_polizas' => $sumaBalances,
            'promedio_balance_por_poliza' => $this->promedio($sumaBalances, $numPolizas),

            // Pagos
            'num_pagos' => $numPagos,
            'total_pagado' => $totalPagado,
            'saldo_pendiente' => round($sumaPrimaTotal - $totalPagado, 2),
        ];
    }

    /**
     * @param  Collection<int, \App\Models\TbOperaciones>  $ops
     */
    private function sumaPrima(Collection $ops): float
    {
        return round($ops->sum(fn ($op) => $this->prima($op)), 2);
    }

    /**
     * @param  Collection<int, \App\Models\TbOperaciones>  $ops
     */
    private function sumaGastosEmision(Collection $ops): float
    {
        return round($ops->sum(fn ($op) => round((float) ($op->GastosEmision ?? 0), 2)), 2);
    }

    /**
     * Moda de la prima total considerando todas las operaciones de la moneda.
     * En caso de empate se devuelve el importe menor; si ningún valor se repite,
     * el valor es null y la frecuencia es 0.
     *
     * @param  Collection<int, \App\Models\TbOperaciones>  $ops
     * @return array{valor: float|null, frecuencia: int}
     */
    private function modaPrimaTotal(Collection $ops): array
    {
        $frecuencias = [];

        foreach ($ops as $op) {
            $clave = number_format($this->prima($op), 2, '.', '');
            $frecuencias[$clave] = ($frecuencias[$clave] ?? 0) + 1;
        }

        if ($frecuencias === []) {
            return ['valor' => null, 'frecuencia' => 0];
        }

        $frecuenciaMaxima = max($frecuencias);

        if ($frecuenciaMaxima < 2) {
            return ['valor' => null, 'frecuencia' => 0];
        }

        $valoresModales = array_keys(array_filter(
            $frecuencias,
            fn (int $frecuencia) => $frecuencia === $frecuenciaMaxima
        ));

        usort($valoresModales, fn (string $a, string $b) => (float) $a <=> (float) $b);

        return [
            'valor' => round((float) $valoresModales[0], 2),
            'frecuencia' => $frecuenciaMaxima,
        ];
    }

    private function promedio(float $suma, int $cantidad): float
    {
        if ($cantidad <= 0) {
            return 0.0;
        }

        return round($suma / $cantidad, 2);
    }

    /**
     * @return iterable<int, mixed>
     */
    private function pagos($op): iterable
    {
        $pagos = $op->pagos ?? null;

        if ($pagos === null) {
            return [];
        }

        return $pagos;
    }

    private function moneda($op): string
    {
        return $this->normalizarMoneda($op->IDMoneda ?? null);
    }

    private function monedaPago($pago): string
    {
        return $this->normalizarMoneda($pago->IDMoneda ?? null);
    }

    private function normalizarMoneda($moneda): string
    {
        $moneda = strtoupper(trim((string) $moneda));

        return $moneda === '' ? 'SIN MONEDA' : $moneda;
    }

    private function ordenarMonedas(string $a, string $b): int
    {
        $prioridad = ['MXN' => 0, 'USD' => 1, 'SIN MONEDA' => 9];

        $pa = $prioridad[$a] ?? 5;
        $pb = $prioridad[$b] ?? 5;

        if ($pa === $pb) {
            return strcmp($a, $b);
        }

        return $pa <=> $pb;
    }

    /**
     * Moneda principal: la que concentra más operaciones; desempate por monto.
     *
     * @param  array<string, array<string, mixed>>  $porMoneda
     */
    private function monedaPrincipal(array $porMoneda): ?string
    {
        if ($porMoneda === []) {
            return null;
        }

        uasort($porMoneda, function ($a, $b) {
            if ($a['total_operaciones'] === $b['total_operaciones']) {
                return $b['suma_prima_total'] <=> $a['suma_prima_total'];
            }

            return $b['total_operaciones'] <=> $a['total_operaciones'];
        });

        return (string) array_key_first($porMoneda);
    }

    private function booleano($valor): bool
    {
        if (is_bool($valor)) {
            return $valor;
        }

        if ($valor === null) {
            return false;
        }

        if (is_string($valor)) {
            return in_array(strtolower(trim($valor)), ['1', 'true', 't', 'si', 'sí', 'yes'], true);
        }

        return (int) $valor === 1;
    }
}
