<?php

use App\Models\Ebr\EbrAlerta;
use App\Models\Ebr\EbrCatActividad;
use App\Models\Ebr\EbrCatEstado;
use App\Models\Ebr\EbrEjercicio;
use App\Models\Ebr\EbrPoliza;
use App\Models\Ebr\EbrSiniestro;
use App\Services\Ebr\CalculadoraEbr;
use Database\Seeders\EbrCatalogoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (EbrCatEstado::count() === 0) {
        $this->seed(EbrCatalogoSeeder::class);
    }
    EbrCatActividad::where('ocupacion_giro', 'Agricultor')->update(['nivel_riesgo' => 1, 'ocupacion_agrupada' => 'Agricultor']);
});

function ebrEjercicioReglas(): EbrEjercicio
{
    return EbrEjercicio::create([
        'anio' => 2097,
        'nombre' => 'Test reglas EBR',
        'fecha_inicio' => '2097-01-01',
        'fecha_fin' => '2097-12-31',
        'estado' => 'borrador',
        'version_metodologia' => '2025',
        'mitigantes' => config('ebr.mitigantes'),
    ]);
}

function ebrPolizaReglas(EbrEjercicio $ejercicio, string $noPoliza, string $noCliente, string $pais = 'México', array $extra = []): EbrPoliza
{
    return EbrPoliza::create(array_merge([
        'ejercicio_id' => $ejercicio->id,
        'no_poliza' => $noPoliza,
        'no_cliente' => $noCliente,
        'tipo_persona' => 'Persona Física',
        'edad' => 40,
        'antiguedad_relacion_anios' => 5,
        'ocupacion_giro' => 'Agricultor',
        'actividad_nivel_riesgo' => 1,
        'estado' => 'Querétaro',
        'pais_nacionalidad' => $pais,
        'categoria_pld' => 'Sin coincidencia en listas',
        'lista_riesgo' => 1,
        'canal' => 'Interno',
        'ramo' => 'Agrícola',
        'producto' => 'Elemental',
        'grupo_producto' => 'Agrícola',
        'prima_total' => 15000,
        'tipo_cambio' => 1,
        'fecha_emision' => '2097-01-10',
        'num_operaciones_entrada' => 1,
    ], $extra));
}

test('excluye siniestros y alertas no vinculados al periodo', function () {
    $ejercicio = ebrEjercicioReglas();
    ebrPolizaReglas($ejercicio, 'P1', 'C1');
    ebrPolizaReglas($ejercicio, 'P2', 'C2', 'Panamá');

    EbrSiniestro::create([
        'ejercicio_id' => $ejercicio->id, 'no_siniestro' => 'S1', 'no_poliza' => 'P1',
        'fecha_pago' => '2097-06-01', 'monto_pagado' => 5000, 'num_operaciones_salida' => 1,
    ]);
    EbrSiniestro::create([
        'ejercicio_id' => $ejercicio->id, 'no_siniestro' => 'S2', 'no_poliza' => 'POL-EXTERNA',
        'fecha_pago' => '2097-06-01', 'monto_pagado' => 9000, 'num_operaciones_salida' => 1,
    ]);

    EbrAlerta::create([
        'ejercicio_id' => $ejercicio->id, 'patron' => 'Fraccionado', 'no_cliente' => 'C1',
        'no_poliza' => 'P1', 'fecha_deteccion' => '2097-03-01', 'monto' => 1000,
        'procedio' => true, 'num_operaciones' => 1,
    ]);
    EbrAlerta::create([
        'ejercicio_id' => $ejercicio->id, 'patron' => 'Fraccionado', 'no_cliente' => 'C1',
        'no_poliza' => 'P1', 'fecha_deteccion' => '2097-03-02', 'monto' => 1000,
        'procedio' => true, 'num_operaciones' => 1,
    ]);
    EbrAlerta::create([
        'ejercicio_id' => $ejercicio->id, 'patron' => 'Monto', 'no_cliente' => 'C2',
        'no_poliza' => 'P2', 'fecha_deteccion' => '2097-03-03', 'monto' => 30000,
        'procedio' => true, 'num_operaciones' => 1,
    ]);
    EbrAlerta::create([
        'ejercicio_id' => $ejercicio->id, 'patron' => 'Fraccionado', 'no_cliente' => 'C-EXTERNO',
        'no_poliza' => 'POL-EXTERNA', 'fecha_deteccion' => '2097-03-04', 'monto' => 1000,
        'procedio' => true, 'num_operaciones' => 1,
    ]);

    $resultado = app(CalculadoraEbr::class)->calcular($ejercicio);

    expect($resultado['resultados']['resumen_datos']['siniestros'])->toBe(1)
        ->and($resultado['resultados']['resumen_datos']['siniestros_importados'])->toBe(2)
        ->and($resultado['resultados']['resumen_datos']['alertas_vinculadas'])->toBe(3)
        ->and($resultado['resultados']['resumen_datos']['alertas_procedentes'])->toBe(4)
        ->and($resultado['resultados']['resumen_datos']['alertas_procedentes_vinculadas'])->toBe(3)
        ->and($resultado['resultados']['resumen_datos']['alertas_relevantes'])->toBe(1)
        ->and(collect($resultado['resultados']['elementos']['geografia']['paises'])->pluck('pais'))->toContain('México')->toContain('Panamá');

    $fraccionado = collect($resultado['resultados']['elementos']['transacciones']['indicadores'])->firstWhere('codigo', '5.2');
    expect($fraccionado['contratos'])->toBe(1);
});

test('normaliza los mitigantes guardados contra el catalogo de la metodologia', function () {
    $ejercicio = ebrEjercicioReglas();
    $ejercicio->update(['mitigantes' => [
        ['id' => 1, 'scope' => 'entidad', 'max' => 1.0, 'nivel' => 'Optimizado'],
        ['id' => 4, 'scope' => 'entidad', 'max' => 1.0, 'nivel' => 'Optimizado'],
    ]]);
    ebrPolizaReglas($ejercicio, 'P1', 'C1');

    $resultado = app(CalculadoraEbr::class)->calcular($ejercicio);

    expect($resultado['resultados']['elementos']['productos']['residual'])->toBe(2.38)
        ->and(round($resultado['resultados']['institucion']['mitigante_entidad'], 6))->toBe(0.35);
});
