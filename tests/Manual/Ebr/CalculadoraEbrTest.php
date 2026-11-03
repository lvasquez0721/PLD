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

it('calcula productos, geografia, canales y transacciones con la metodologia EBR', function () {
    $ejercicio = EbrEjercicio::create([
        'anio' => 2096,
        'nombre' => 'Test EBR',
        'fecha_inicio' => '2096-01-01',
        'fecha_fin' => '2096-12-31',
        'estado' => 'borrador',
        'version_metodologia' => '2025',
        'mitigantes' => config('ebr.mitigantes'),
    ]);

    EbrPoliza::create([
        'ejercicio_id' => $ejercicio->id, 'no_poliza' => 'P1', 'no_cliente' => 'C1',
        'tipo_persona' => 'Persona Física', 'edad' => 40, 'antiguedad_relacion_anios' => 5,
        'ocupacion_giro' => 'Agricultor', 'actividad_nivel_riesgo' => 1, 'actividad_agrupada' => 'Agricultor',
        'estado' => 'Querétaro', 'categoria_pld' => 'Sin coincidencia en listas', 'lista_riesgo' => 1,
        'canal' => 'Interno', 'ramo' => 'Agrícola', 'producto' => 'Elemental', 'grupo_producto' => 'Agrícola',
        'prima_total' => 10000, 'tipo_cambio' => 1, 'fecha_emision' => '2096-01-10', 'num_operaciones_entrada' => 1,
    ]);
    EbrPoliza::create([
        'ejercicio_id' => $ejercicio->id, 'no_poliza' => 'P2', 'no_cliente' => 'C2',
        'tipo_persona' => 'Persona Moral', 'antiguedad_empresa_anios' => 8, 'antiguedad_relacion_anios' => 5,
        'ocupacion_giro' => 'Agricultor', 'actividad_nivel_riesgo' => 1, 'actividad_agrupada' => 'Agricultor',
        'estado' => 'Jalisco', 'categoria_pld' => 'Sin coincidencia en listas', 'lista_riesgo' => 1,
        'canal' => 'Agente', 'ramo' => 'Ganadero', 'producto' => 'Ganadero', 'grupo_producto' => 'Ganadero',
        'prima_total' => 20000, 'tipo_cambio' => 1, 'fecha_emision' => '2096-02-10', 'num_operaciones_entrada' => 1,
    ]);
    EbrSiniestro::create(['ejercicio_id' => $ejercicio->id, 'no_siniestro' => 'S1', 'no_poliza' => 'P1', 'no_cliente' => 'C1', 'fecha_pago' => '2096-06-01', 'monto_pagado' => 5000, 'estado' => 'Querétaro', 'grupo_producto' => 'Agrícola', 'canal' => 'Interno', 'num_operaciones_salida' => 1]);
    EbrSiniestro::create(['ejercicio_id' => $ejercicio->id, 'no_siniestro' => 'S2', 'no_poliza' => 'P2', 'no_cliente' => 'C2', 'fecha_pago' => '2096-07-01', 'monto_pagado' => 9000, 'estado' => 'Jalisco', 'grupo_producto' => 'Ganadero', 'canal' => 'Agente', 'num_operaciones_salida' => 1]);
    EbrAlerta::create(['ejercicio_id' => $ejercicio->id, 'patron' => 'Fraccionado', 'no_cliente' => 'C1', 'no_poliza' => 'P1', 'fecha_deteccion' => '2096-03-01', 'monto' => 30000, 'procedio' => true, 'num_operaciones' => 1]);
    EbrAlerta::create(['ejercicio_id' => $ejercicio->id, 'patron' => 'Monto', 'no_cliente' => 'C2', 'no_poliza' => 'P2', 'fecha_deteccion' => '2096-04-01', 'monto' => 80000, 'procedio' => true, 'num_operaciones' => 1]);

    $resultado = app(CalculadoraEbr::class)->calcular($ejercicio);

    $clientes = $resultado['resultados']['elementos']['clientes'];
    $institucion = $resultado['resultados']['institucion'];

    expect($resultado['resultados']['elementos']['productos']['inherente'])->toBe(2.5)
        ->and($resultado['resultados']['elementos']['productos']['residual'])->toBe(2.38)
        ->and($resultado['resultados']['elementos']['geografia']['inherente'])->toBeGreaterThan(0)
        ->and($resultado['resultados']['elementos']['canales']['inherente'])->toBeGreaterThan(0)
        ->and($resultado['resultados']['elementos']['transacciones']['inherente'])->toBe(5.0)
        ->and($clientes['mitigante'])->toBe(0.1)
        ->and(collect($clientes['pf']['indicadores'])->firstWhere('clave', 'actividad')['mitigante'])->toBe(0.2)
        ->and(collect($clientes['pm']['indicadores'])->firstWhere('clave', 'actividad')['mitigante'])->toBe(0.2)
        ->and(round($institucion['mitigante_entidad'], 6))->toBe(0.35)
        ->and(round($institucion['residual_final'], 2))->toBe(round($institucion['residual'] * 0.65, 2))
        ->and($institucion['residual_final'])->toBeGreaterThan(0);
});
