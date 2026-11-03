<?php

use App\Models\Ebr\EbrAlerta;
use App\Models\Ebr\EbrEjercicio;
use App\Services\Ebr\ImportadorEbr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function ebrAlertasCsv(array $filas): string
{
    $headers = [
        'patron', 'no_cliente', 'no_poliza', 'fecha_deteccion', 'monto',
        'instrumento_monetario', 'agente', 'estatus', 'procedio',
        'num_operaciones', 'descripcion', 'razones',
    ];

    return implode("\n", [implode(',', $headers), ...array_map(fn ($fila) => implode(',', $fila), $filas)]);
}

function ebrEjercicioPrueba(): EbrEjercicio
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

function ebrAlertaFila(string $procedio = 'Sí', string $estatus = 'Enviado', string $fecha = '2025-06-15'): array
{
    return [
        'Fraccionado', '1001', 'POL-2025-000001', $fecha, '50000.00',
        'Transferencia', '0-Agente Directo', $estatus, $procedio,
        '2', 'Pago fraccionado', 'Pago en dos exhibiciones',
    ];
}

test('importa alertas sin las columnas folio y resultado', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioPrueba();
    $importador = app(ImportadorEbr::class);

    $importacion = $importador->prevalidar(
        $ejercicio,
        'alertas',
        UploadedFile::fake()->createWithContent('alertas.csv', ebrAlertasCsv([ebrAlertaFila()]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(1)
        ->and($importacion->filas_error)->toBe(0);

    $importacion = $importador->importar($ejercicio, $importacion);

    expect($importacion->estado)->toBe('importado')
        ->and(EbrAlerta::count())->toBe(1);

    $alerta = EbrAlerta::first();

    expect($alerta->patron)->toBe('Fraccionado')
        ->and($alerta->procedio)->toBeTrue()
        ->and(Schema::hasColumn('ebr_alertas', 'folio'))->toBeFalse()
        ->and(Schema::hasColumn('ebr_alertas', 'resultado'))->toBeFalse();
});

test('rechaza alertas sin procedio ni estatus', function () {
    Storage::fake('local');

    $importacion = app(ImportadorEbr::class)->prevalidar(
        ebrEjercicioPrueba(),
        'alertas',
        UploadedFile::fake()->createWithContent('alertas.csv', ebrAlertasCsv([ebrAlertaFila('', '')]))
    );

    expect($importacion->estado)->toBe('error')
        ->and($importacion->errores[0]['campo'])->toBe('procedio');
});

test('deriva procedio desde el estatus y no filtra por fecha de deteccion', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioPrueba();
    $importador = app(ImportadorEbr::class);

    $importacion = $importador->prevalidar(
        $ejercicio,
        'alertas',
        UploadedFile::fake()->createWithContent('alertas.csv', ebrAlertasCsv([
            ebrAlertaFila('', 'Concluido', '2026-01-15'),
            ebrAlertaFila('Sí', 'Descartado', '2025-07-15'),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(2);

    $importador->importar($ejercicio, $importacion);

    $alertas = EbrAlerta::orderBy('id')->get();
    expect($alertas[0]->procedio)->toBeTrue()
        ->and($alertas[1]->procedio)->toBeFalse();
});

test('detecta filas duplicadas por su contenido', function () {
    Storage::fake('local');

    $importacion = app(ImportadorEbr::class)->prevalidar(
        ebrEjercicioPrueba(),
        'alertas',
        UploadedFile::fake()->createWithContent('alertas.csv', ebrAlertasCsv([ebrAlertaFila(), ebrAlertaFila()]))
    );

    expect($importacion->estado)->toBe('error')
        ->and($importacion->filas_validas)->toBe(1)
        ->and($importacion->filas_error)->toBe(1);
});

test('importa id_operacion y moneda de la alerta', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioPrueba();
    $headers = config('ebr.csv.alertas.headers');
    $fila = config('ebr.csv.alertas.ejemplo');
    $fila[array_search('id_operacion', $headers, true)] = 'OP-2025-000001';
    $fila[array_search('moneda', $headers, true)] = 'Dolares Americanos';
    $fila[array_search('tipo_cambio', $headers, true)] = '19.50';

    $csv = implode("\n", [implode(',', $headers), implode(',', $fila)]);

    $importador = app(ImportadorEbr::class);
    $importacion = $importador->prevalidar(
        $ejercicio,
        'alertas',
        UploadedFile::fake()->createWithContent('alertas.csv', $csv)
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(1);

    $importador->importar($ejercicio, $importacion);

    $alerta = EbrAlerta::first();

    expect($alerta->id_operacion)->toBe('OP-2025-000001')
        ->and($alerta->moneda)->toBe('USD')
        ->and(round((float) $alerta->tipo_cambio, 2))->toBe(19.5);
});
