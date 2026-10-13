<?php

use App\Http\Controllers\OperacionesController;
use App\Models\Clientes\TbClientes;
use App\Models\TbOperaciones;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // phpunit.xml apunta a DB "testing"; este test valida contra UAT
    // dentro de una transacción con rollback, sin dejar residuos.
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');
});

test('crearAlerta guarda la FechaEmision de la poliza en FechaDeteccion', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $cliente = TbClientes::find(6734);
        expect($cliente)->not->toBeNull();

        $operacion = new TbOperaciones;
        $operacion->IDCliente = $cliente->IDCliente;
        $operacion->FolioPoliza = 'TEST-PLD-FECHA-001';
        $operacion->FechaEmision = '2024-01-15';
        $operacion->PrimaTotal = 50000;
        $operacion->IDMoneda = 'MXN';
        $operacion->FechaInicioVigencia = '2024-01-15';
        $operacion->FechaFinVigencia = '2025-01-15';
        $operacion->GastosEmision = 0;
        $operacion->IDFormaPago = 3;
        $operacion->PagaTercero = false;
        $operacion->EsEndosoCancelacion = false;
        $operacion->save();

        $metodo = new ReflectionMethod(OperacionesController::class, 'crearAlerta');
        $metodo->setAccessible(true);

        $alerta = $metodo->invoke(
            new OperacionesController,
            $operacion,
            $cliente,
            ['patron' => 'Monto Inusual', 'descripcion' => 't', 'razones' => 't'],
            [],
            [],
            null,
            '3'
        );

        // Emisión 2024 vs hoy: si guardara "la fecha del momento", falla.
        expect(substr((string) $alerta->FechaDeteccion, 0, 10))->toBe('2024-01-15')
            ->and(substr((string) $alerta->FechaOperacion, 0, 10))->toBe('2024-01-15');
    } finally {
        DB::connection('mysql')->rollBack();
    }
});
