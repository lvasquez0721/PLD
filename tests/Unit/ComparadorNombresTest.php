<?php

use App\Services\ListasNegras\Nombres\ComparadorNombres;
use App\Services\ListasNegras\Nombres\NormalizadorNombre;

beforeEach(function () {
    $this->comparador = new ComparadorNombres(new NormalizadorNombre);
});

test('detecta coincidencia exacta de persona fisica', function () {
    $resultado = $this->comparador->comparar(
        1,
        'JOSE',
        'PEREZ',
        'GARCIA',
        null,
        'JOSE PEREZ GARCIA'
    );

    expect($resultado)->not->toBeNull()
        ->and($resultado['score'])->toBeGreaterThan(0.90);
});

test('tolera acentos y errores foneticos', function () {
    $resultado = $this->comparador->comparar(
        1,
        'JOSE',
        'PERES',
        'GARCIA',
        null,
        'JOSÉ PÉREZ GARCÍA'
    );

    expect($resultado)->not->toBeNull();
});

test('tolera el orden de los apellidos y el nombre', function () {
    $resultado = $this->comparador->comparar(
        1,
        'JUAN',
        'PEREZ',
        'LOPEZ',
        null,
        'PEREZ LOPEZ JUAN'
    );

    expect($resultado)->not->toBeNull();
});

test('no confunde personas con el mismo apellido pero distinto nombre', function () {
    $resultado = $this->comparador->comparar(
        1,
        'JUAN',
        'PEREZ',
        null,
        null,
        'PEDRO PEREZ'
    );

    expect($resultado)->toBeNull();
});

test('no confunde personas con apellido materno distinto', function () {
    $resultado = $this->comparador->comparar(
        1,
        'JUAN',
        'PEREZ',
        'GOMEZ',
        null,
        'JUAN PEREZ LOPEZ'
    );

    expect($resultado)->toBeNull();
});

test('detecta razon social aunque la lista incluya sufijo societario', function () {
    $resultado = $this->comparador->comparar(
        2,
        null,
        null,
        null,
        'COMERCIALIZADORA COPADO',
        'COMERCIALIZADORA COPADO SA DE CV'
    );

    expect($resultado)->not->toBeNull();
});

test('detecta alias de listas cnsf', function () {
    $resultado = $this->comparador->comparar(
        1,
        'BAHAR',
        null,
        null,
        null,
        'SHUQIB a.k.a. BAHAR'
    );

    expect($resultado)->not->toBeNull();
});
