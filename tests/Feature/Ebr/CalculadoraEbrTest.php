<?php

use App\Models\Ebr\EbrAlerta;
use App\Models\Ebr\EbrEjercicio;
use App\Models\Ebr\EbrPoliza;
use App\Models\Ebr\EbrSiniestro;
use App\Services\Ebr\CalculadoraEbr;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ebrEjercicioCalculo(): EbrEjercicio
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

function ebrPolizaCalculo(EbrEjercicio $ejercicio, array $overrides = []): EbrPoliza
{
    return EbrPoliza::create(array_merge([
        'ejercicio_id' => $ejercicio->id,
        'no_poliza' => 'POL-2025-000001',
        'id_operacion' => 'OP-2025-000001',
        'folio_endoso' => null,
        'tipo_movimiento' => 'Emisión',
        'no_cliente' => '1001',
        'tipo_persona' => 'Persona Física',
        'fecha_nacimiento' => '1980-05-10',
        'fecha_primera_relacion' => '2020-01-15',
        'edad' => 45,
        'antiguedad_relacion_anios' => 5,
        'actividad_nivel_riesgo' => 1,
        'estado' => 'Querétaro',
        'pais_nacionalidad' => 'México',
        'categoria_pld' => 'Sin coincidencia en listas',
        'lista_riesgo' => 1,
        'canal' => 'Interno',
        'grupo_producto' => 'Agrícola',
        'prima_total' => 1000,
        'moneda' => 'MXN',
        'tipo_cambio' => 1,
        'fecha_emision' => '2025-01-10',
        'num_operaciones_entrada' => 10,
        'excluida' => false,
    ], $overrides));
}

test('reproduce las reglas del informe 2025 con operaciones y alertas fuera de fecha', function () {
    $ejercicio = ebrEjercicioCalculo();

    ebrPolizaCalculo($ejercicio, [
        'clave_operacion' => hash('sha256', 'POL-2025-000001|1001|OP-2025-000001'),
    ]);
    ebrPolizaCalculo($ejercicio, [
        'no_poliza' => 'POL-2025-000001',
        'id_operacion' => 'OP-2025-000001-ENDOSO',
        'folio_endoso' => 'END-1',
        'tipo_movimiento' => 'Endoso',
        'prima_total' => 500,
        'num_operaciones_entrada' => 5,
        'clave_operacion' => hash('sha256', 'POL-2025-000001|1001|OP-2025-000001-ENDOSO'),
    ]);
    ebrPolizaCalculo($ejercicio, [
        'no_poliza' => 'POL-2025-000002',
        'id_operacion' => 'OP-2025-000002',
        'no_cliente' => '2002',
        'tipo_persona' => 'Persona Moral',
        'fecha_nacimiento' => null,
        'fecha_constitucion' => '2010-01-01',
        'edad' => null,
        'antiguedad_empresa_anios' => 15,
        'antiguedad_relacion_anios' => 8,
        'actividad_nivel_riesgo' => 2,
        'grupo_producto' => 'Ganadero',
        'prima_total' => 2000,
        'num_operaciones_entrada' => 20,
        'clave_operacion' => hash('sha256', 'POL-2025-000002|2002|OP-2025-000002'),
    ]);

    EbrSiniestro::create([
        'ejercicio_id' => $ejercicio->id,
        'no_siniestro' => 'SIN-2025-000001',
        'no_poliza' => 'POL-2025-000001',
        'no_cliente' => '1001',
        'fecha_pago' => '2025-06-15',
        'monto_pagado' => 500,
        'moneda' => 'MXN',
        'tipo_cambio' => 1,
        'num_operaciones_salida' => 1,
        'estado' => 'Querétaro',
        'grupo_producto' => 'Ganadero',
        'canal' => 'Agente',
    ]);

    EbrAlerta::create([
        'ejercicio_id' => $ejercicio->id,
        'patron' => 'Monto',
        'no_cliente' => null,
        'no_poliza' => 'POL-QUE-NO-EXISTE',
        'id_operacion' => 'OP-2025-000001-ENDOSO',
        'fecha_deteccion' => '2026-01-15',
        'monto' => 100,
        'moneda' => 'MXN',
        'tipo_cambio' => 1,
        'estatus' => 'Concluido',
        'procedio' => true,
        'num_operaciones' => 5,
    ]);

    $resultado = app(CalculadoraEbr::class)->calcular($ejercicio);
    $datos = $resultado['resultados'];

    expect($datos['resumen_datos']['polizas'])->toBe(2)
        ->and($datos['resumen_datos']['operaciones_entrada'])->toBe(35)
        ->and($datos['resumen_datos']['clientes'])->toBe(2)
        ->and($datos['resumen_datos']['alertas'])->toBe(0)
        ->and($datos['resumen_datos']['alertas_importadas'])->toBe(1)
        ->and($datos['resumen_datos']['alertas_vinculadas'])->toBe(1)
        ->and($datos['resumen_datos']['alertas_relevantes'])->toBe(0)
        ->and($datos['elementos']['productos']['inherente'])->toBe(2.5)
        ->and($datos['elementos']['productos']['residual'])->toBe(2.38)
        ->and($datos['elementos']['clientes']['mitigante'])->toBe(0.1)
        ->and($datos['institucion']['mitigante_entidad'])->toBe(0.35)
        ->and($datos['elementos']['transacciones']['inherente'])->toBeGreaterThan(0);
});

test('respeta el grupo de producto del siniestro y usa las cinco metricas de clientes', function () {
    $ejercicio = ebrEjercicioCalculo();

    ebrPolizaCalculo($ejercicio, [
        'fecha_nacimiento' => '1950-01-01',
        'fecha_primera_relacion' => '2025-06-01',
        'estado' => 'Baja California',
        'actividad_nivel_riesgo' => 3,
        'categoria_pld' => 'Personas bloqueadas',
        'lista_riesgo' => 5,
        'clave_operacion' => hash('sha256', 'POL-2025-000001|1001|OP-2025-000001'),
    ]);
    ebrPolizaCalculo($ejercicio, [
        'no_poliza' => 'POL-2025-000002',
        'id_operacion' => 'OP-2025-000002',
        'no_cliente' => '2002',
        'tipo_persona' => 'Persona Moral',
        'fecha_nacimiento' => null,
        'fecha_constitucion' => '2010-01-01',
        'edad' => null,
        'antiguedad_empresa_anios' => 15,
        'antiguedad_relacion_anios' => 8,
        'actividad_nivel_riesgo' => 2,
        'grupo_producto' => 'Ganadero',
        'prima_total' => 2000,
        'num_operaciones_entrada' => 20,
        'clave_operacion' => hash('sha256', 'POL-2025-000002|2002|OP-2025-000002'),
    ]);

    EbrSiniestro::create([
        'ejercicio_id' => $ejercicio->id,
        'no_siniestro' => 'SIN-2025-000001',
        'no_poliza' => 'POL-2025-000001',
        'no_cliente' => '1001',
        'fecha_pago' => '2025-06-15',
        'monto_pagado' => 500,
        'moneda' => 'MXN',
        'tipo_cambio' => 1,
        'num_operaciones_salida' => 1,
        'estado' => 'Querétaro',
        'grupo_producto' => 'Ganadero',
        'canal' => 'Agente',
    ]);

    $resultado = app(CalculadoraEbr::class)->calcular($ejercicio);
    $clientes = $resultado['resultados']['elementos']['clientes'];
    $productos = collect($resultado['resultados']['elementos']['productos']['detalle'])
        ->keyBy('grupo');

    $metricas = array_keys($clientes['pf']['distribuciones_tipo']);
    expect($metricas)->toBe(['clientes', 'ops_in', 'ops_out', 'monto_in', 'monto_out']);

    $esperado = 0.0;
    foreach ($metricas as $metrica) {
        $score = ((float) $clientes['pf']['inherente'] * (float) $clientes['pf']['distribuciones_tipo'][$metrica])
            + ((float) $clientes['pm']['inherente'] * (float) $clientes['pm']['distribuciones_tipo'][$metrica]);
        $esperado = max($esperado, $score);
    }

    expect($productos['Ganadero']['distribuciones']['ops_out'])->toBe(1.0)
        ->and($productos['Agrícola']['distribuciones']['ops_out'])->toBe(0.0)
        ->and($clientes['inherente'])->toBe(round($esperado, 6));
});
