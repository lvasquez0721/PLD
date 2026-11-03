<?php

use App\Models\Ebr\EbrEjercicio;
use App\Models\Ebr\EbrPoliza;
use App\Models\Ebr\EbrSiniestro;
use App\Services\Ebr\ImportadorEbr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function ebrSiniestrosCsv(array $filas): string
{
    $headers = [
        'no_siniestro', 'no_poliza', 'no_cliente', 'fecha_pago', 'monto_pagado',
        'moneda', 'tipo_cambio', 'num_operaciones_salida', 'estado', 'producto',
        'grupo_producto', 'canal',
    ];

    return implode("\n", [implode(',', $headers), ...array_map(fn ($fila) => implode(',', $fila), $filas)]);
}

function ebrSiniestroFila(array $overrides = []): array
{
    $fila = [
        'SIN-2025-000001', 'POL-2025-000001', '1001', '2025-06-15', '25000.00',
        'MXN', '1', '1', 'Querétaro', 'Elemental', 'Agrícola', 'Interno',
    ];

    foreach ($overrides as $indice => $valor) {
        $fila[$indice] = $valor;
    }

    return $fila;
}

function ebrEjercicioSiniestros(): EbrEjercicio
{
    return EbrEjercicio::create([
        'anio' => 2025,
        'nombre' => 'EBR 2025',
        'fecha_inicio' => '2025-01-01',
        'fecha_fin' => '2025-12-31',
        'estado' => 'borrador',
        'version_metodologia' => '2025',
        'mitigantes' => config('ebr.mitigantes'),
    ]);
}

function ebrPolizaPrueba(EbrEjercicio $ejercicio, string $noPoliza = 'POL-2025-000001'): EbrPoliza
{
    return EbrPoliza::create([
        'ejercicio_id' => $ejercicio->id,
        'no_poliza' => $noPoliza,
        'no_cliente' => '1001',
        'tipo_persona' => 'Persona Física',
        'ocupacion_giro' => 'Agricultor',
        'actividad_nivel_riesgo' => 1,
        'estado' => 'Querétaro',
        'categoria_pld' => 'Sin coincidencia en listas',
        'lista_riesgo' => 1,
        'canal' => 'Interno',
        'grupo_producto' => 'Agrícola',
        'prima_total' => 15000,
        'tipo_cambio' => 1,
        'fecha_emision' => '2025-01-10',
        'num_operaciones_entrada' => 1,
    ]);
}

test('acepta siniestros con fecha de pago fuera del periodo', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioSiniestros();
    ebrPolizaPrueba($ejercicio);

    $importacion = app(ImportadorEbr::class)->prevalidar(
        $ejercicio,
        'siniestros',
        UploadedFile::fake()->createWithContent('siniestros.csv', ebrSiniestrosCsv([
            ebrSiniestroFila([3 => '2026-01-15']),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(1)
        ->and($importacion->filas_error)->toBe(0);
});

test('omite siniestros de polizas sin operacion en el periodo', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioSiniestros();
    ebrPolizaPrueba($ejercicio);

    $importador = app(ImportadorEbr::class);
    $importacion = $importador->prevalidar(
        $ejercicio,
        'siniestros',
        UploadedFile::fake()->createWithContent('siniestros.csv', ebrSiniestrosCsv([
            ebrSiniestroFila([1 => 'POL-OTRA']),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(0)
        ->and($importacion->resumen['omitidas']['siniestros'])->toBe(1)
        ->and($importacion->resumen['advertencias_total'])->toBe(1);

    $importador->importar($ejercicio, $importacion);

    expect(EbrSiniestro::count())->toBe(0);
});

test('rechaza siniestros duplicados por no_siniestro', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioSiniestros();
    ebrPolizaPrueba($ejercicio);

    $importacion = app(ImportadorEbr::class)->prevalidar(
        $ejercicio,
        'siniestros',
        UploadedFile::fake()->createWithContent('siniestros.csv', ebrSiniestrosCsv([
            ebrSiniestroFila(),
            ebrSiniestroFila(),
        ]))
    );

    expect($importacion->estado)->toBe('error')
        ->and($importacion->filas_validas)->toBe(1)
        ->and($importacion->filas_error)->toBe(1)
        ->and($importacion->errores[0]['campo'])->toBe('no_siniestro');
});
test('omite siniestros sin fecha de pago', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioSiniestros();
    ebrPolizaPrueba($ejercicio);

    $importador = app(ImportadorEbr::class);
    $importacion = $importador->prevalidar(
        $ejercicio,
        'siniestros',
        UploadedFile::fake()->createWithContent('siniestros.csv', ebrSiniestrosCsv([
            ebrSiniestroFila(),
            ebrSiniestroFila([0 => 'SIN-SIN-FECHA', 3 => '']),
            ebrSiniestroFila([0 => 'SIN-NULL', 3 => 'NULL']),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(1)
        ->and($importacion->filas_error)->toBe(0)
        ->and($importacion->resumen['omitidas']['siniestros'])->toBe(2);

    $importador->importar($ejercicio, $importacion);

    expect(EbrSiniestro::count())->toBe(1);
});
