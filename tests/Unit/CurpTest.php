<?php

use App\Support\Curp;

test('obtiene la fecha de nacimiento de una CURP de los 90', function () {
    expect(Curp::fechaNacimiento('MAOR920629MPLDFC03'))->toBe('1992-06-29');
    expect(Curp::fechaNacimiento('GUTJ910213HMNLMH04'))->toBe('1991-02-13');
});

test('obtiene la fecha de nacimiento de una CURP del 2000 en adelante', function () {
    expect(Curp::fechaNacimiento('GARC000101HDFXXXA1'))->toBe('2000-01-01');
});

test('corrige CURP con homoclave que produciria un año futuro', function () {
    expect(Curp::fechaNacimiento('MOGN700308HMNRNZO7'))->toBe('1970-03-08');
});

test('normaliza espacios y minusculas', function () {
    expect(Curp::fechaNacimiento(' maor920629mpldfc03 '))->toBe('1992-06-29');
});

test('regresa null para CURP vacia o invalida', function () {
    expect(Curp::fechaNacimiento(null))->toBeNull();
    expect(Curp::fechaNacimiento(''))->toBeNull();
    expect(Curp::fechaNacimiento('ABC'))->toBeNull();
    expect(Curp::fechaNacimiento('GARC991301HDFXXX01'))->toBeNull();
});
