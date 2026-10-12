<?php

use App\Services\PLD\PrimaCancelacionService;

test('espejo exacto con signo pasa la validación', function () {
    expect(PrimaCancelacionService::esExacta(-171067.38, -171067.38))->toBeTrue()
        ->and(PrimaCancelacionService::necesitaCorreccion(-171067.38, -171067.38))->toBeFalse();
});

test('prima de cancelación distinta a la base requiere corrección (caso alerta 87)', function () {
    // Emisión 171067.38 vs cancelación recibida -147421.88: diferencia 23645.50
    expect(PrimaCancelacionService::esExacta(-147421.88, -171067.38))->toBeFalse()
        ->and(PrimaCancelacionService::necesitaCorreccion(-147421.88, -171067.38))->toBeTrue();
});

test('el signo es obligatorio: valor positivo no es espejo válido', function () {
    expect(PrimaCancelacionService::esExacta(171067.38, -171067.38))->toBeFalse()
        ->and(PrimaCancelacionService::necesitaCorreccion(171067.38, -171067.38))->toBeTrue();
});

test('la comparación es exacta a 2 decimales', function () {
    // Diferencia de 1 centavo ya exige corrección
    expect(PrimaCancelacionService::necesitaCorreccion(-171067.37, -171067.38))->toBeTrue()
        // Ruido de float más allá del segundo decimal se normaliza
        ->and(PrimaCancelacionService::esExacta(-171067.379, -171067.38))->toBeTrue();
});

test('cero y menos cero se consideran iguales', function () {
    expect(PrimaCancelacionService::esExacta(0, -0.0))->toBeTrue()
        ->and(PrimaCancelacionService::esExacta('0.00', '-0.00'))->toBeTrue()
        ->and(PrimaCancelacionService::necesitaCorreccion(0, -0.0))->toBeFalse();
});

test('acepta strings y numericos indistintamente', function () {
    expect(PrimaCancelacionService::esExacta('-171067.38', -171067.38))->toBeTrue()
        ->and(PrimaCancelacionService::esExacta(-3750.0, '-3750'))->toBeTrue();
});
