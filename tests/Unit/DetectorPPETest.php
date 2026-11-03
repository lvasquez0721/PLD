<?php

use App\Services\ListasNegras\DetectorPPE;

beforeEach(function () {
    $this->detector = new DetectorPPE;
});

function respuestaPPEDe(array $registros): array
{
    return ['success' => true, 'data' => $registros];
}

test('ignora coincidencias de listas que no son PPE aunque tengan alta coincidencia', function () {
    $data = respuestaPPEDe([
        ['LISTA' => 'SAT69', 'NOMBRECOMP' => 'Carlos Omar Torres Santaella', 'COINCIDENCIA' => 95, 'ESTATUS' => 'Activo'],
        ['LISTA' => 'SANC', 'NOMBRECOMP' => 'Carlos Omar Badillo Lugo', 'COINCIDENCIA' => 99, 'ESTATUS' => 'Activo'],
        ['LISTA' => 'PEPINT', 'NOMBRECOMP' => 'German Eduardo Ayala Amaya', 'COINCIDENCIA' => 95, 'ESTATUS' => 'Activo'],
    ]);

    expect($this->detector->filtrar($data))->toBe([])
        ->and($this->detector->esPPE($data))->toBeFalse();
});

test('descarta homonimos PPE por debajo del umbral', function () {
    $data = respuestaPPEDe([
        ['LISTA' => 'PPE', 'NOMBRECOMP' => 'Carlos Omar Zurita Alejandre', 'COINCIDENCIA' => 81, 'ESTATUS' => 'Activo'],
        ['LISTA' => 'PPE', 'NOMBRECOMP' => 'Anel Martinez Rosado', 'COINCIDENCIA' => 83, 'ESTATUS' => 'Activo'],
        ['LISTA' => 'PPE', 'NOMBRECOMP' => 'Rigoberto Uriegas Garcia', 'COINCIDENCIA' => 89, 'ESTATUS' => 'Activo'],
    ]);

    expect($this->detector->filtrar($data))->toBe([])
        ->and($this->detector->esPPE($data))->toBeFalse();
});

test('detecta PPE con coincidencia suficiente y marca el estatus activo', function () {
    $registro = ['LISTA' => 'PPE', 'NOMBRECOMP' => 'Juan Perez Lopez', 'COINCIDENCIA' => 91, 'ESTATUS' => 'Activo'];
    $data = respuestaPPEDe([$registro]);

    expect($this->detector->filtrar($data))->toHaveCount(1)
        ->and($this->detector->esPPE($data))->toBeTrue()
        ->and($this->detector->estaActivo($registro))->toBeTrue();
});

test('una PPE inactiva se detecta pero no se marca como activa', function () {
    $registro = ['LISTA' => 'PPE', 'NOMBRECOMP' => 'Juan Perez Lopez', 'COINCIDENCIA' => 95, 'ESTATUS' => 'Inactivo'];
    $data = respuestaPPEDe([$registro]);

    expect($this->detector->esPPE($data))->toBeTrue()
        ->and($this->detector->estaActivo($registro))->toBeFalse();
});
