<?php

it('expone la configuracion minima del modulo EBR', function () {
    $config = config('ebr');

    expect($config)->toBeArray()
        ->and($config['productos']['grupos'])->toHaveCount(4)
        ->and($config['clientes']['pf']['indicadores'])->toHaveCount(6)
        ->and($config['clientes']['pm']['indicadores'])->toHaveCount(6)
        ->and($config['csv']['clientes']['headers'])->toContain('no_poliza', 'id_operacion', 'folio_endoso', 'no_cliente', 'prima_total')
        ->and($config['csv']['siniestros']['headers'])->toContain('no_siniestro', 'no_poliza', 'monto_pagado')
        ->and($config['csv']['siniestros']['required'])->not->toContain('fecha_pago')
        ->and($config['csv']['alertas']['headers'])->toContain('patron', 'id_operacion', 'procedio', 'estatus')
        ->and($config['csv']['alertas']['headers'])->not->toContain('folio', 'resultado')
        ->and($config['csv']['alertas']['required'])->toContain('patron', 'fecha_deteccion')
        ->and($config['csv']['alertas']['required'])->not->toContain('procedio')
        ->and($config['transacciones']['estatus_procedentes'])->toContain('Dictaminado', 'Concluido')
        ->and($config['mitigantes'])->toHaveCount(9)
        ->and(array_sum(array_column($config['productos']['indicadores'], 'peso')))->toBeGreaterThan(1.0);
});
