<?php

use App\Services\ListasNegras\Nombres\NormalizadorNombre;

test('normaliza acentos separadores y espacios', function () {
    $normalizador = new NormalizadorNombre;

    expect($normalizador->normalizar('  José   Pérez-García  '))->toBe('JOSE PEREZ GARCIA');
});

test('elimina palabras vacias y sufijos societarios', function () {
    $normalizador = new NormalizadorNombre;

    expect($normalizador->tokens('Comercializadora Copado SA de CV'))
        ->toBe(['COMERCIALIZADORA', 'COPADO'])
        ->and($normalizador->tokens('MARTHA MARISSA VILLALOBOS DE LA TORRE'))
        ->toBe(['MARTHA', 'MARISSA', 'VILLALOBOS', 'TORRE']);
});

test('genera claves foneticas equivalentes para variantes comunes', function () {
    $normalizador = new NormalizadorNombre;

    expect($normalizador->claveFonetica('PEREZ'))
        ->toBe($normalizador->claveFonetica('PERES'))
        ->and($normalizador->claveFonetica('GONZALEZ'))
        ->toBe($normalizador->claveFonetica('GONSALES'))
        ->and($normalizador->claveFonetica('HERNANDEZ'))
        ->toBe($normalizador->claveFonetica('ERNANDEZ'));
});

test('separa nombres con alias aka y fka', function () {
    $normalizador = new NormalizadorNombre;

    expect($normalizador->variantes('SHUQIB a.k.a. BAHAR'))
        ->toContain('SHUQIB')
        ->toContain('BAHAR')
        ->and($normalizador->variantes('LU TA FA a.k.a. CHA'))
        ->toContain('CHA');
});
