<?php

use App\Models\TbOperaciones;
use App\Services\PLD\AnalisisPagosService;
use Illuminate\Support\Facades\DB;

/**
 * Valida el patrón "Monto": se genera cuando al menos uno de los pagos en
 * efectivo de la operación alcanza el umbral de operaciones relevantes,
 * sin importar cuántos pagos tenga la operación.
 */
beforeEach(function () {
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');
});

function pagosMontoRelevanteTest(array $pagos): array
{
    $resultado = [];

    foreach ($pagos as $index => $pago) {
        $resultado[] = array_merge([
            'IDOperacionPago' => $index + 1,
            'IDFormaPago' => 1,
            'IDMoneda' => 'USD',
            'Monto' => 0,
            'TipoCambio' => 1,
            'FechaPago' => now()->toDateString(),
        ], $pago);
    }

    return $resultado;
}

function analizarMontoRelevanteTest(array $pagos)
{
    $operacion = new TbOperaciones([
        'IDCliente' => 0,
        'IDMoneda' => 'USD',
        'FechaEmision' => now()->toDateString(),
        'PrimaTotal' => array_sum(array_column($pagos, 'Monto')),
        'GastosEmision' => 0,
    ]);

    $resultado = app(AnalisisPagosService::class)->analizarPagos($operacion, $pagos, null);

    return [
        'resultado' => $resultado,
        'alertas_monto' => collect($resultado->alertasGenerar)
            ->where('patron', AnalisisPagosService::PATRON_MONTO_RELEVANTE)
            ->values()
            ->all(),
    ];
}

test('genera Monto con múltiples pagos cuando un pago en efectivo alcanza el umbral', function () {
    $pagos = pagosMontoRelevanteTest([
        ['IDFormaPago' => 3, 'Monto' => 20000],
        ['IDFormaPago' => 1, 'Monto' => 8000],
        ['IDFormaPago' => 3, 'Monto' => 1000],
    ]);

    ['resultado' => $resultado, 'alertas_monto' => $alertas] = analizarMontoRelevanteTest($pagos);

    expect(count($alertas))->toBe(1)
        ->and($resultado->esMontoRelevante)->toBeTrue()
        ->and($resultado->montoRelevanteUSD)->toBe(8000.0)
        ->and($alertas[0]['razones'])->toContain('uno de 3 pagos');
});

test('no genera Monto si ningún pago individual en efectivo alcanza el umbral', function () {
    $pagos = pagosMontoRelevanteTest([
        ['Monto' => 4000],
        ['Monto' => 4000],
        ['Monto' => 1000],
    ]);

    ['resultado' => $resultado, 'alertas_monto' => $alertas] = analizarMontoRelevanteTest($pagos);

    expect(count($alertas))->toBe(0)
        ->and($resultado->esMontoRelevante)->toBeFalse()
        ->and($resultado->detallesMontoRelevante)->toHaveCount(3);
});

test('genera una sola alerta Monto y toma el mayor pago cuando varios superan el umbral', function () {
    $pagos = pagosMontoRelevanteTest([
        ['Monto' => 8000],
        ['Monto' => 9000],
        ['Monto' => 2000],
    ]);

    ['resultado' => $resultado, 'alertas_monto' => $alertas] = analizarMontoRelevanteTest($pagos);

    expect(count($alertas))->toBe(1)
        ->and($resultado->montoRelevanteUSD)->toBe(9000.0)
        ->and($alertas[0]['razones'])->toContain('2 pagos en efectivo alcanzaron el umbral');
});

test('no genera Monto cuando ningún pago es en efectivo', function () {
    $pagos = pagosMontoRelevanteTest([
        ['IDFormaPago' => 3, 'Monto' => 8000],
        ['IDFormaPago' => 3, 'Monto' => 9000],
    ]);

    ['resultado' => $resultado, 'alertas_monto' => $alertas] = analizarMontoRelevanteTest($pagos);

    expect(count($alertas))->toBe(0)
        ->and($resultado->esMontoRelevante)->toBeFalse()
        ->and($resultado->detallesMontoRelevante)->toBe([]);
});

test('considera el umbral como alcanzado cuando el pago es exactamente 7500 USD', function () {
    $pagos = pagosMontoRelevanteTest([
        ['Monto' => 7500],
        ['Monto' => 100],
    ]);

    ['alertas_monto' => $alertas] = analizarMontoRelevanteTest($pagos);

    expect(count($alertas))->toBe(1)
        ->and($alertas[0]['monto_usd'])->toBe(7500.0);
});
