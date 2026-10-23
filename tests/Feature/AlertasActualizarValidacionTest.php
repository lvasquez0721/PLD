<?php

use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Valida las reglas de /alertas/actualizar: los únicos campos obligatorios
 * son "Descripción de la operación" y "Razones". El resto son opcionales y,
 * cuando llegan vacíos, se conserva el valor ya guardado en la alerta.
 */
beforeEach(function () {
    // phpunit.xml apunta a DB "testing"; este test valida contra UAT
    // dentro de una transacción con rollback, sin dejar residuos.
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');
});

/**
 * Usuario en memoria: no se persiste en la BD, sólo sirve para autenticar.
 */
function usuarioDePrueba(): User
{
    $user = new User;
    $user->id = 999999;
    $user->usuario = 'prueba';
    $user->nombre = 'Prueba';
    $user->email = 'prueba@pld.local';
    $user->email_verified_at = now();

    return $user;
}

function alertaDePrueba(): TbAlertas
{
    $cliente = TbClientes::find(6734);

    $alerta = new TbAlertas;
    $alerta->Patron = 'Nuevo';
    $alerta->Estatus = 'Generado';
    $alerta->IDCliente = $cliente->IDCliente;
    $alerta->Cliente = 'Cliente de prueba';
    $alerta->Poliza = 'POLIZA-PRUEBA-001';
    $alerta->MontoOperacion = 50000;
    $alerta->InstrumentoMonetario = 'Transferencia';
    $alerta->RFCAgente = 'RFC-PRUEBA';
    $alerta->Agente = 'Agente de prueba';
    $alerta->IDMoneda = 'MXN';
    $alerta->Descripcion = 'Descripción original';
    $alerta->Razones = 'Razones originales';
    $alerta->save();

    return $alerta;
}

test('actualizar alerta exige la descripción de la operación', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $this->actingAs(usuarioDePrueba());

        $response = $this->put(route('alertas.actualizar'), [
            'idAlerta' => 999999,
            'razones' => 'Razones válidas',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('descripcionOperacion');
    } finally {
        DB::connection('mysql')->rollBack();
    }
});

test('actualizar alerta exige las razones', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $this->actingAs(usuarioDePrueba());

        $response = $this->put(route('alertas.actualizar'), [
            'idAlerta' => 999999,
            'descripcionOperacion' => 'Descripción válida',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('razones');
    } finally {
        DB::connection('mysql')->rollBack();
    }
});

test('actualizar alerta acepta únicamente descripción y razones', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $alerta = alertaDePrueba();
        $idClienteOriginal = (int) $alerta->IDCliente;

        $this->actingAs(usuarioDePrueba());

        $response = $this->put(route('alertas.actualizar'), [
            'idAlerta' => $alerta->IDRegistroAlerta,
            'descripcionOperacion' => 'Nueva descripción',
            'razones' => 'Nuevas razones',
        ]);

        $response->assertStatus(200);

        $alerta->refresh();

        // Los campos obligatorios sí se actualizan.
        expect($alerta->Descripcion)->toBe('Nueva descripción')
            ->and($alerta->Razones)->toBe('Nuevas razones');

        // Los campos opcionales que no se enviaron conservan su valor.
        expect($alerta->Estatus)->toBe('Generado')
            ->and($alerta->Poliza)->toBe('POLIZA-PRUEBA-001')
            ->and((float) $alerta->MontoOperacion)->toBe(50000.0)
            ->and($alerta->InstrumentoMonetario)->toBe('Transferencia')
            ->and($alerta->IDMoneda)->toBe('MXN')
            ->and($alerta->Agente)->toBe('Agente de prueba')
            ->and($alerta->RFCAgente)->toBe('RFC-PRUEBA')
            ->and($alerta->Cliente)->toBe('Cliente de prueba')
            ->and((int) $alerta->IDCliente)->toBe($idClienteOriginal);
    } finally {
        DB::connection('mysql')->rollBack();
    }
});

test('actualizar alerta acepta los campos opcionales enviados vacíos y conserva los valores', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $alerta = alertaDePrueba();

        $this->actingAs(usuarioDePrueba());

        $response = $this->put(route('alertas.actualizar'), [
            'idAlerta' => $alerta->IDRegistroAlerta,
            'descripcionOperacion' => 'Nueva descripción',
            'razones' => 'Nuevas razones',
            'estatus' => '',
            'nombre' => '',
            'noCliente' => '',
            'poliza' => '',
            'agente' => '',
            'instrumento' => '',
            'idMoneda' => '',
            'monto' => '',
        ]);

        $response->assertStatus(200);

        $alerta->refresh();

        expect($alerta->Descripcion)->toBe('Nueva descripción')
            ->and($alerta->Razones)->toBe('Nuevas razones')
            ->and($alerta->Estatus)->toBe('Generado')
            ->and($alerta->Poliza)->toBe('POLIZA-PRUEBA-001')
            ->and((float) $alerta->MontoOperacion)->toBe(50000.0)
            ->and($alerta->InstrumentoMonetario)->toBe('Transferencia')
            ->and($alerta->IDMoneda)->toBe('MXN')
            ->and($alerta->Agente)->toBe('Agente de prueba')
            ->and($alerta->RFCAgente)->toBe('RFC-PRUEBA');
    } finally {
        DB::connection('mysql')->rollBack();
    }
});
