<?php

use App\Http\Controllers\OperacionesController;
use App\Models\CatParametriaPLD;
use App\Models\Clientes\CatEstados;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use Illuminate\Support\Facades\DB;

/**
 * Valida la regla de emisión de operación: cliente con domicilio en estado
 * de alto riesgo (IndicePaz >= 3) y prima USD >= parámetro 14. La regla solo
 * notifica al oficial, no genera alertas.
 */
beforeEach(function () {
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');

    $this->controlador = new OperacionesController;
    $this->evaluarUbicacion = new ReflectionMethod($this->controlador, 'evaluarUbicacionPrimaRiesgoOperacion');
    $this->datosAviso = new ReflectionMethod($this->controlador, 'datosAvisoRiesgoOperacion');
});

test('detecta ubicación de riesgo y prima USD mayor o igual al parámetro 14 sin generar alerta', function () {
    $estadosRiesgo = CatEstados::where('IndicePaz', '>=', '3')->pluck('IDEstado');
    $cliente = TbClientes::whereHas('domicilios', fn ($q) => $q->whereIn('IDEstado', $estadosRiesgo))->first();

    if (! $cliente) {
        $this->markTestSkipped('No hay clientes con domicilio en estado de alto riesgo en la base de datos de prueba.');
    }

    $umbral = (float) CatParametriaPLD::getValor(CatParametriaPLD::MONTO_MINIMO_ALERTA, 7500);

    $operacion = new TbOperaciones([
        'IDCliente' => $cliente->IDCliente,
        'IDMoneda' => 'USD',
        'FechaEmision' => now(),
        'PrimaTotal' => $umbral,
        'FolioPoliza' => 'TEST-PLD-RIESGO-UBICACION',
    ]);

    $ubicacion = $this->evaluarUbicacion->invoke($this->controlador, $operacion, $cliente);

    expect($ubicacion)->not->toBeNull()
        ->and($ubicacion['prima_usd'])->toBeGreaterThanOrEqual($umbral)
        ->and($ubicacion['umbral_usd'])->toBe($umbral);

    $alertasAntes = TbAlertas::where('IDCliente', $cliente->IDCliente)->count();
    $datos = $this->datosAviso->invoke($this->controlador, $operacion, $cliente);
    $alertasDespues = TbAlertas::where('IDCliente', $cliente->IDCliente)->count();

    expect($datos)->not->toBeNull()
        ->and($datos['UbicacionRiesgo'] ?? null)->not->toBeNull()
        ->and($datos['PrimaUSD'])->toBeGreaterThanOrEqual($umbral)
        ->and($alertasDespues)->toBe($alertasAntes);
});

test('no notifica por ubicación cuando la prima USD es menor al parámetro 14', function () {
    $estadosRiesgo = CatEstados::where('IndicePaz', '>=', '3')->pluck('IDEstado');
    $cliente = TbClientes::whereHas('domicilios', fn ($q) => $q->whereIn('IDEstado', $estadosRiesgo))->first();

    if (! $cliente) {
        $this->markTestSkipped('No hay clientes con domicilio en estado de alto riesgo en la base de datos de prueba.');
    }

    $umbral = (float) CatParametriaPLD::getValor(CatParametriaPLD::MONTO_MINIMO_ALERTA, 7500);

    $operacion = new TbOperaciones([
        'IDCliente' => $cliente->IDCliente,
        'IDMoneda' => 'USD',
        'FechaEmision' => now(),
        'PrimaTotal' => max(0, $umbral - 1),
        'FolioPoliza' => 'TEST-PLD-RIESGO-UBICACION-BAJA',
    ]);

    expect($this->evaluarUbicacion->invoke($this->controlador, $operacion, $cliente))->toBeNull();
});
