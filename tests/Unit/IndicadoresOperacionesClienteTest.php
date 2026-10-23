<?php

use App\Services\PLD\IndicadoresOperacionesCliente;
use Illuminate\Support\Collection;

/**
 * Construye una operación fake con los campos que usa el servicio.
 */
function operacionFake(array $attrs = []): object
{
    return (object) array_merge([
        'IDOperacion' => 1,
        'FolioPoliza' => 'P-001',
        'FolioEndoso' => null,
        'PrimaTotal' => 1000.00,
        'GastosEmision' => 100.00,
        'IDMoneda' => 'MXN',
        'cancelaPoliza' => 0,
        'operacionCancelada' => 0,
        'EsEndosoCancelacion' => 0,
        'pagos' => collect(),
    ], $attrs);
}

test('el promedio neto de primas contempla los endosos de aumento y disminución', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1, 'PrimaTotal' => 1000.00]),
        operacionFake(['IDOperacion' => 2, 'FolioEndoso' => 'E-1', 'PrimaTotal' => 300.00]),
        operacionFake(['IDOperacion' => 3, 'FolioEndoso' => 'E-2', 'PrimaTotal' => -200.00]),
    ]);

    $indicadores = (new IndicadoresOperacionesCliente)->calcular($operaciones);
    $mxn = $indicadores['por_moneda']['MXN'];

    expect($mxn['total_operaciones'])->toBe(3)
        ->and($mxn['total_emisiones'])->toBe(1)
        ->and($mxn['total_endosos'])->toBe(2)
        ->and($mxn['total_aumentos'])->toBe(1)
        ->and($mxn['total_disminuciones'])->toBe(1)
        // 1000 + 300 - 200 = 1100
        ->and($mxn['suma_prima_total'])->toBe(1100.00)
        ->and($mxn['promedio_prima_total'])->toBe(366.67)
        ->and($mxn['promedio_prima_emisiones'])->toBe(1000.00)
        ->and($mxn['suma_aumentos'])->toBe(300.00)
        ->and($mxn['promedio_aumentos'])->toBe(300.00)
        ->and($mxn['suma_disminuciones'])->toBe(-200.00)
        ->and($mxn['promedio_disminuciones'])->toBe(-200.00);
});

test('el endoso de cancelación deja el balance de la póliza en cero', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1, 'PrimaTotal' => 5000.00]),
        operacionFake([
            'IDOperacion' => 2,
            'FolioEndoso' => 'E-1',
            'PrimaTotal' => -5000.00,
            'EsEndosoCancelacion' => true,
        ]),
    ]);

    $indicadores = (new IndicadoresOperacionesCliente)->calcular($operaciones);
    $mxn = $indicadores['por_moneda']['MXN'];

    expect($mxn['total_cancelaciones'])->toBe(1)
        ->and($mxn['total_operaciones_canceladas'])->toBe(1)
        ->and($mxn['suma_prima_total'])->toBe(0.00)
        ->and($mxn['suma_balance_polizas'])->toBe(0.00)
        ->and($mxn['promedio_balance_por_poliza'])->toBe(0.00)
        // La cancelación no se cuenta como disminución "normal".
        ->and($mxn['total_disminuciones'])->toBe(0)
        ->and($mxn['suma_prima_cancelaciones'])->toBe(-5000.00);
});

test('las categorías de operación son excluyentes entre sí', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1]),
        operacionFake(['IDOperacion' => 2, 'FolioEndoso' => 'E-1', 'PrimaTotal' => 50]),
        operacionFake(['IDOperacion' => 3, 'FolioEndoso' => 'E-2', 'PrimaTotal' => -50]),
        operacionFake(['IDOperacion' => 4, 'FolioEndoso' => 'E-3', 'PrimaTotal' => 0]),
        operacionFake(['IDOperacion' => 5, 'FolioEndoso' => 'E-4', 'PrimaTotal' => -10, 'cancelaPoliza' => true]),
    ]);

    $mxn = (new IndicadoresOperacionesCliente)->calcular($operaciones)['por_moneda']['MXN'];

    expect($mxn['total_emisiones'] + $mxn['total_aumentos'] + $mxn['total_disminuciones']
        + $mxn['total_cancelaciones'] + $mxn['total_sin_cambio'])
        ->toBe($mxn['total_operaciones'])
        ->and($mxn['total_sin_cambio'])->toBe(1);
});

test('los indicadores se calculan por moneda para no mezclar MXN y USD', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1, 'PrimaTotal' => 1000.00, 'IDMoneda' => 'MXN']),
        operacionFake(['IDOperacion' => 2, 'PrimaTotal' => 250.00, 'IDMoneda' => 'USD']),
    ]);

    $indicadores = (new IndicadoresOperacionesCliente)->calcular($operaciones);

    expect($indicadores['por_moneda'])->toHaveKeys(['MXN', 'USD'])
        ->and($indicadores['por_moneda']['MXN']['suma_prima_total'])->toBe(1000.00)
        ->and($indicadores['por_moneda']['USD']['suma_prima_total'])->toBe(250.00)
        ->and($indicadores['por_moneda']['MXN']['total_operaciones'])->toBe(1)
        ->and($indicadores['por_moneda']['USD']['total_operaciones'])->toBe(1)
        ->and($indicadores['moneda_principal'])->toBe('MXN');
});

test('el total pagado y el saldo pendiente solo cuentan pagos de la misma moneda', function () {
    $operaciones = collect([
        operacionFake([
            'IDOperacion' => 1,
            'PrimaTotal' => 1000.00,
            'IDMoneda' => 'MXN',
            'pagos' => collect([
                (object) ['Monto' => 400.00, 'IDMoneda' => 'MXN'],
                (object) ['Monto' => 999.00, 'IDMoneda' => 'USD'],
            ]),
        ]),
    ]);

    $mxn = (new IndicadoresOperacionesCliente)->calcular($operaciones)['por_moneda']['MXN'];

    expect($mxn['num_pagos'])->toBe(1)
        ->and($mxn['total_pagado'])->toBe(400.00)
        ->and($mxn['saldo_pendiente'])->toBe(600.00);
});

test('clasifica el tipo de operación según folio de endoso, signo de la prima y cancelación', function () {
    $servicio = new IndicadoresOperacionesCliente;

    expect($servicio->tipoOperacion(operacionFake()))->toBe(IndicadoresOperacionesCliente::TIPO_EMISION)
        ->and($servicio->tipoOperacion(operacionFake(['FolioEndoso' => 'E-1', 'PrimaTotal' => 10])))
        ->toBe(IndicadoresOperacionesCliente::TIPO_AUMENTO)
        ->and($servicio->tipoOperacion(operacionFake(['FolioEndoso' => 'E-1', 'PrimaTotal' => -10])))
        ->toBe(IndicadoresOperacionesCliente::TIPO_DISMINUCION)
        ->and($servicio->tipoOperacion(operacionFake(['FolioEndoso' => 'E-1', 'PrimaTotal' => 0])))
        ->toBe(IndicadoresOperacionesCliente::TIPO_SIN_CAMBIO)
        ->and($servicio->tipoOperacion(operacionFake(['operacionCancelada' => 1])))
        ->toBe(IndicadoresOperacionesCliente::TIPO_CANCELACION);
});

test('sin operaciones los indicadores vienen en ceros y sin moneda principal', function () {
    $indicadores = (new IndicadoresOperacionesCliente)->calcular(collect());

    expect($indicadores['total_operaciones'])->toBe(0)
        ->and($indicadores['por_moneda'])->toBe([])
        ->and($indicadores['moneda_principal'])->toBeNull();
});

test('la moda de la prima total usa el importe más frecuente de todas las operaciones', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1, 'PrimaTotal' => 500.00]),
        operacionFake(['IDOperacion' => 2, 'PrimaTotal' => 500.00]),
        operacionFake(['IDOperacion' => 3, 'FolioEndoso' => 'E-1', 'PrimaTotal' => 100.00]),
        operacionFake(['IDOperacion' => 4, 'FolioEndoso' => 'E-2', 'PrimaTotal' => -50.00]),
        operacionFake(['IDOperacion' => 5, 'FolioEndoso' => 'E-3', 'PrimaTotal' => -500.00, 'EsEndosoCancelacion' => true]),
    ]);

    $mxn = (new IndicadoresOperacionesCliente)->calcular($operaciones)['por_moneda']['MXN'];

    expect($mxn['moda_prima_total'])->toBe(500.00)
        ->and($mxn['moda_prima_total_frecuencia'])->toBe(2);
});

test('en caso de empate la moda toma el importe menor', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1, 'PrimaTotal' => 200.00]),
        operacionFake(['IDOperacion' => 2, 'PrimaTotal' => 100.00]),
        operacionFake(['IDOperacion' => 3, 'FolioEndoso' => 'E-1', 'PrimaTotal' => 200.00]),
        operacionFake(['IDOperacion' => 4, 'FolioEndoso' => 'E-2', 'PrimaTotal' => 100.00]),
    ]);

    $mxn = (new IndicadoresOperacionesCliente)->calcular($operaciones)['por_moneda']['MXN'];

    expect($mxn['moda_prima_total'])->toBe(100.00)
        ->and($mxn['moda_prima_total_frecuencia'])->toBe(2);
});

test('si ningún importe de prima se repite no hay moda', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1, 'PrimaTotal' => 100.00]),
        operacionFake(['IDOperacion' => 2, 'PrimaTotal' => 200.00]),
        operacionFake(['IDOperacion' => 3, 'FolioEndoso' => 'E-1', 'PrimaTotal' => 300.00]),
    ]);

    $mxn = (new IndicadoresOperacionesCliente)->calcular($operaciones)['por_moneda']['MXN'];

    expect($mxn['moda_prima_total'])->toBeNull()
        ->and($mxn['moda_prima_total_frecuencia'])->toBe(0);
});

test('la moda se calcula de forma independiente por moneda', function () {
    $operaciones = collect([
        operacionFake(['IDOperacion' => 1, 'PrimaTotal' => 100.00, 'IDMoneda' => 'MXN']),
        operacionFake(['IDOperacion' => 2, 'PrimaTotal' => 100.00, 'IDMoneda' => 'MXN']),
        operacionFake(['IDOperacion' => 3, 'PrimaTotal' => 250.00, 'IDMoneda' => 'USD']),
        operacionFake(['IDOperacion' => 4, 'PrimaTotal' => 300.00, 'IDMoneda' => 'USD']),
    ]);

    $indicadores = (new IndicadoresOperacionesCliente)->calcular($operaciones);

    expect($indicadores['por_moneda']['MXN']['moda_prima_total'])->toBe(100.00)
        ->and($indicadores['por_moneda']['MXN']['moda_prima_total_frecuencia'])->toBe(2)
        ->and($indicadores['por_moneda']['USD']['moda_prima_total'])->toBeNull()
        ->and($indicadores['por_moneda']['USD']['moda_prima_total_frecuencia'])->toBe(0);
});
