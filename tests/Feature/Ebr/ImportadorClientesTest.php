<?php

use App\Models\Ebr\EbrEjercicio;
use App\Models\Ebr\EbrPoliza;
use App\Services\Ebr\ImportadorEbr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function ebrClientesHeaders(): array
{
    return [
        'no_poliza', 'no_cliente', 'nombre_cliente', 'tipo_persona', 'rfc', 'curp',
        'fecha_nacimiento', 'fecha_constitucion', 'fecha_primera_relacion',
        'edad', 'antiguedad_empresa_anios', 'antiguedad_relacion_anios',
        'ocupacion_giro', 'estado', 'pais_nacionalidad', 'pais_operacion',
        'categoria_pld', 'canal', 'agente', 'ramo', 'producto', 'grupo_producto',
        'prima_total', 'moneda', 'tipo_cambio', 'fecha_emision', 'num_operaciones_entrada',
    ];
}

function ebrClienteFila(array $overrides = []): array
{
    $fila = [
        'POL-2025-000001', '1001', 'Cliente Ejemplo', 'Persona Física', '', '',
        '1980-05-10', '', '2020-01-15', '', '', '', 'Agricultor', 'Querétaro', 'México', 'México',
        'Sin coincidencia en listas', 'Interno', '0-Agente Directo', 'Agrícola', 'Elemental', 'Agrícola',
        '15000.00', 'MXN', '1', '2025-01-10', '1',
    ];

    foreach ($overrides as $indice => $valor) {
        $fila[$indice] = $valor;
    }

    return $fila;
}

function ebrClientesCsv(array $filas, ?array $headers = null): string
{
    $headers = $headers ?? ebrClientesHeaders();

    return implode("\n", [
        implode(',', $headers),
        ...array_map(fn ($fila) => implode(',', $fila), $filas),
    ]);
}

function ebrEjercicioClientes(): EbrEjercicio
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

test('aplica valores por defecto y advertencias a campos opcionales faltantes', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioClientes();
    $importador = app(ImportadorEbr::class);

    $importacion = $importador->prevalidar(
        $ejercicio,
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', ebrClientesCsv([
            ebrClienteFila([12 => '', 13 => '', 16 => '']),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->resumen['advertencias_total'])->toBeGreaterThanOrEqual(3);

    $importador->importar($ejercicio, $importacion);

    $poliza = EbrPoliza::first();
    expect($poliza->estado)->toBe('Nacional')
        ->and($poliza->actividad_nivel_riesgo)->toBe(2)
        ->and($poliza->categoria_pld)->toBe('Sin coincidencia en listas');
});

test('rechaza categorias PLD fuera del catalogo', function () {
    Storage::fake('local');

    $importacion = app(ImportadorEbr::class)->prevalidar(
        ebrEjercicioClientes(),
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', ebrClientesCsv([
            ebrClienteFila([16 => 'Categoría inventada']),
        ]))
    );

    expect($importacion->estado)->toBe('error')
        ->and($importacion->errores[0]['campo'])->toBe('categoria_pld');
});

test('rechaza encabezados obligatorios faltantes', function () {
    Storage::fake('local');

    $headers = ebrClientesHeaders();
    $headers = array_values(array_diff($headers, ['prima_total']));

    $importacion = app(ImportadorEbr::class)->prevalidar(
        ebrEjercicioClientes(),
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', ebrClientesCsv([
            ebrClienteFila(),
        ], $headers))
    );

    expect($importacion->estado)->toBe('error')
        ->and($importacion->errores[0]['mensaje'])->toContain('prima_total');
});
test('normaliza moneda, tipo de cambio y valores nulos sin escalar importes', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioClientes();
    $importador = app(ImportadorEbr::class);

    $importacion = $importador->prevalidar(
        $ejercicio,
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', ebrClientesCsv([
            ebrClienteFila([0 => 'POL-MXN', 6 => 'NULL', 9 => 'NULL', 23 => 'Pesos Mexicanos', 24 => '1000000']),
            ebrClienteFila([0 => 'POL-USD', 23 => 'Dolares Americanos', 24 => '19.4953']),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_error)->toBe(0);

    $importador->importar($ejercicio, $importacion);

    $mxn = EbrPoliza::where('no_poliza', 'POL-MXN')->first();
    $usd = EbrPoliza::where('no_poliza', 'POL-USD')->first();

    expect($mxn->moneda)->toBe('MXN')
        ->and((float) $mxn->tipo_cambio)->toBe(1.0)
        ->and($mxn->fecha_nacimiento)->toBeNull()
        ->and($mxn->edad)->toBeNull()
        ->and($usd->moneda)->toBe('USD')
        ->and(round((float) $usd->tipo_cambio, 4))->toBe(19.4953);
});
test('limpia la actividad no clasificada de uso exclusivo cnbv', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioClientes();
    $importador = app(ImportadorEbr::class);

    $importacion = $importador->prevalidar(
        $ejercicio,
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', ebrClientesCsv([
            ebrClienteFila([12 => 'No Clasificada (Uso Exclusivo Cnbv)  ']),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_error)->toBe(0)
        ->and(collect($importacion->resumen['advertencias'])->pluck('campo'))->not->toContain('ocupacion_giro');

    $importador->importar($ejercicio, $importacion);

    $poliza = EbrPoliza::first();
    expect($poliza->ocupacion_giro)->toBe('No Clasificada')
        ->and($poliza->actividad_nivel_riesgo)->toBe(2);
});
test('anula edades y fechas fuera de rango sin rechazar la fila', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioClientes();
    $importador = app(ImportadorEbr::class);

    $importacion = $importador->prevalidar(
        $ejercicio,
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', ebrClientesCsv([
            ebrClienteFila([0 => 'POL-EDAD-1063', 6 => '0963-11-09', 9 => '1063']),
            ebrClienteFila([0 => 'POL-EDAD-236', 6 => '1790-05-09', 9 => '236']),
            ebrClienteFila([0 => 'POL-EDAD-126', 6 => '31/12/1899', 9 => '126']),
            ebrClienteFila([0 => 'POL-PM-FUTURA', 3 => 'Persona Moral', 6 => '', 7 => '10/07/2027', 9 => '']),
            ebrClienteFila([0 => 'POL-REL-FUTURA', 8 => '07/01/2026', 11 => '0']),
        ]))
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_error)->toBe(0)
        ->and($importacion->resumen['advertencias_total'])->toBeGreaterThanOrEqual(6);

    $importador->importar($ejercicio, $importacion);

    foreach (['POL-EDAD-1063', 'POL-EDAD-236', 'POL-EDAD-126'] as $poliza) {
        $registro = EbrPoliza::where('no_poliza', $poliza)->first();
        expect($registro->edad)->toBeNull()
            ->and($registro->fecha_nacimiento)->toBeNull();
    }

    $pm = EbrPoliza::where('no_poliza', 'POL-PM-FUTURA')->first();
    expect($pm->fecha_constitucion)->toBeNull()
        ->and($pm->antiguedad_empresa_anios)->toBeNull();

    $relacion = EbrPoliza::where('no_poliza', 'POL-REL-FUTURA')->first();
    expect($relacion->fecha_primera_relacion)->toBeNull()
        ->and($relacion->antiguedad_relacion_anios)->toBeNull();
});

test('importa operaciones y endosos de una misma poliza con id_operacion', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioClientes();
    $headers = config('ebr.csv.clientes.headers');
    $primera = config('ebr.csv.clientes.ejemplo');
    $segunda = $primera;

    $segunda[array_search('id_operacion', $headers, true)] = 'OP-2025-000002';
    $segunda[array_search('folio_endoso', $headers, true)] = 'END-2025-000001';
    $segunda[array_search('tipo_movimiento', $headers, true)] = 'Endoso';
    $segunda[array_search('prima_total', $headers, true)] = '500.00';
    $segunda[array_search('num_operaciones_entrada', $headers, true)] = '1';

    $csv = implode("\n", [
        implode(',', $headers),
        implode(',', $primera),
        implode(',', $segunda),
    ]);

    $importador = app(ImportadorEbr::class);
    $importacion = $importador->prevalidar(
        $ejercicio,
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', $csv)
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(2)
        ->and($importacion->resumen['polizas'])->toBe(1)
        ->and($importacion->resumen['operaciones'])->toBe(2);

    $importador->importar($ejercicio, $importacion);

    $polizas = EbrPoliza::orderBy('id')->get();

    expect($polizas)->toHaveCount(2)
        ->and($polizas->pluck('id_operacion')->all())->toBe(['OP-2025-000001', 'OP-2025-000002'])
        ->and($polizas[1]->folio_endoso)->toBe('END-2025-000001')
        ->and($polizas[1]->tipo_movimiento)->toBe('Endoso')
        ->and($polizas->pluck('clave_operacion')->filter()->unique())->toHaveCount(2);
});

test('importa endosos sin prima_total como monto cero y cuenta la operacion', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioClientes();
    $headers = config('ebr.csv.clientes.headers');
    $ejemplos = config('ebr.csv.clientes.ejemplos');

    $csv = implode("\n", [
        implode(',', $headers),
        ...array_map(fn ($fila) => implode(',', $fila), $ejemplos),
    ]);

    $importador = app(ImportadorEbr::class);
    $importacion = $importador->prevalidar(
        $ejercicio,
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', $csv)
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(2)
        ->and($importacion->resumen['polizas'])->toBe(1)
        ->and($importacion->resumen['operaciones'])->toBe(2)
        ->and((float) $importacion->resumen['primas'])->toBe(15000.0)
        ->and(collect($importacion->resumen['advertencias'])->pluck('campo'))->toContain('prima_total');

    $importador->importar($ejercicio, $importacion);

    $endoso = EbrPoliza::where('id_operacion', 'OP-2025-000001-END')->first();

    expect($endoso)->not->toBeNull()
        ->and((float) $endoso->prima_total)->toBe(0.0)
        ->and($endoso->tipo_movimiento)->toBe('Endoso')
        ->and($endoso->folio_endoso)->toBe('END-2025-000001');
});

test('importa emisiones sin prima_total con advertencia y monto cero', function () {
    Storage::fake('local');

    $ejercicio = ebrEjercicioClientes();
    $headers = config('ebr.csv.clientes.headers');
    $fila = config('ebr.csv.clientes.ejemplo');
    $fila[array_search('prima_total', $headers, true)] = '';

    $csv = implode("\n", [implode(',', $headers), implode(',', $fila)]);

    $importador = app(ImportadorEbr::class);
    $importacion = $importador->prevalidar(
        $ejercicio,
        'clientes',
        UploadedFile::fake()->createWithContent('clientes.csv', $csv)
    );

    expect($importacion->estado)->toBe('prevalidado')
        ->and($importacion->filas_validas)->toBe(1)
        ->and((float) $importacion->resumen['primas'])->toBe(0.0)
        ->and(collect($importacion->resumen['advertencias'])->pluck('campo'))->toContain('prima_total');

    $importador->importar($ejercicio, $importacion);

    expect((float) EbrPoliza::first()->prima_total)->toBe(0.0);
});
