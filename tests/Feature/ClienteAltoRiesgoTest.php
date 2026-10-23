<?php

use App\Http\Controllers\ClientesControllerApi;
use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Services\PLD\EvaluadorRiesgoCliente;
use Illuminate\Support\Facades\DB;

/**
 * Valida la evaluación de factores de alto riesgo del cliente:
 *  - Actividad vulnerable (catOcupacionesGiros.NivelRiesgo = 3)
 *  - Zona geográfica (catEstados.IndicePaz = 3)
 *  - Coincidencia en listas negras (CNSF / UIF) y PPE
 */
beforeEach(function () {
    // phpunit.xml apunta a DB "testing"; este test valida contra UAT
    // dentro de una transacción con rollback, sin dejar residuos.
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');

    $this->evaluador = new EvaluadorRiesgoCliente;
});

test('la ocupación con NivelRiesgo 3 genera el patrón Actividad vulnerable', function () {
    $ocupacion = CatOcupacionesGiros::where('NivelRiesgo', '3')->first();

    expect($ocupacion)->not->toBeNull();

    $resultado = $this->evaluador->evaluarOcupacion($ocupacion->IDOcupacionGiro);

    expect($resultado)->not->toBeNull()
        ->and($resultado['patron'])->toBe(EvaluadorRiesgoCliente::PATRON_ACTIVIDAD_VULNERABLE)
        ->and($resultado['descripcion'])->toContain($ocupacion->OcupacionGiro)
        ->and($resultado['razones'])->toContain('NivelRiesgo = 3');
});

test('la ocupación con NivelRiesgo distinto de 3 no genera alerta', function () {
    $ocupacion = CatOcupacionesGiros::where('NivelRiesgo', '1')->first();

    expect($ocupacion)->not->toBeNull()
        ->and($this->evaluador->evaluarOcupacion($ocupacion->IDOcupacionGiro))->toBeNull();
});

test('un domicilio en estado con IndicePaz 3 genera el patrón Zona geografica', function () {
    $estado = CatEstados::where('IndicePaz', '3')->first();

    expect($estado)->not->toBeNull();

    $resultado = $this->evaluador->evaluarZonaGeografica([
        ['IDEstado' => $estado->IDEstado],
    ]);

    expect($resultado)->not->toBeNull()
        ->and($resultado['patron'])->toBe(EvaluadorRiesgoCliente::PATRON_ZONA_GEOGRAFICA)
        ->and($resultado['razones'])->toContain('IndicePaz = 3');
});

test('un domicilio en estado con IndicePaz distinto de 3 no genera alerta', function () {
    $estado = CatEstados::where('IndicePaz', '!=', '3')->first();

    expect($estado)->not->toBeNull()
        ->and($this->evaluador->evaluarZonaGeografica([
            ['IDEstado' => $estado->IDEstado],
        ]))->toBeNull();
});

test('la coincidencia en listas negras y el PPE marcan al cliente de alto riesgo', function () {
    $resultado = $this->evaluador->evaluar(null, [], true, true);

    expect($resultado['esAltoRiesgo'])->toBeTrue()
        ->and($resultado['factores'])->toHaveCount(2)
        ->and($resultado['alertas'])->toBeEmpty();
});

test('un cliente sin factores no es de alto riesgo', function () {
    $resultado = $this->evaluador->evaluar(null, [], false, false);

    expect($resultado['esAltoRiesgo'])->toBeFalse()
        ->and($resultado['factores'])->toBeEmpty()
        ->and($resultado['alertas'])->toBeEmpty();
});

test('la ocupación vulnerable y el domicilio de alto riesgo generan ambos patrones', function () {
    $ocupacion = CatOcupacionesGiros::where('NivelRiesgo', '3')->first();
    $estado = CatEstados::where('IndicePaz', '3')->first();

    $resultado = $this->evaluador->evaluar(
        $ocupacion->IDOcupacionGiro,
        [['IDEstado' => $estado->IDEstado]],
        false,
        false
    );

    expect($resultado['esAltoRiesgo'])->toBeTrue()
        ->and($resultado['alertas'])->toHaveCount(2)
        ->and(array_column($resultado['alertas'], 'patron'))->toBe([
            EvaluadorRiesgoCliente::PATRON_ACTIVIDAD_VULNERABLE,
            EvaluadorRiesgoCliente::PATRON_ZONA_GEOGRAFICA,
        ]);
});

test('se crea la alerta de nivel cliente con el patrón y el cliente correctos', function () {
    DB::connection('mysql')->beginTransaction();

    try {
        $cliente = new TbClientes;
        $cliente->RFC = 'TEST-PLD-RIESGO-001';
        $cliente->Nombre = 'CLIENTE';
        $cliente->ApellidoPaterno = 'PRUEBA';
        $cliente->ApellidoMaterno = 'ALTO';
        $cliente->IDTipoPersona = 1;
        $cliente->IDNacionalidad = 'MX';
        $cliente->CoincideEnListasNegras = 0;
        $cliente->EsPPEActivo = false;
        $cliente->Activo = true;
        $cliente->save();

        $controlador = new ClientesControllerApi;
        $metodo = new ReflectionMethod($controlador, 'crearAlertaCliente');
        $metodo->setAccessible(true);

        $alerta = $metodo->invoke(
            $controlador,
            $cliente,
            EvaluadorRiesgoCliente::PATRON_ZONA_GEOGRAFICA,
            'Zona geografica detectada: prueba.',
            'Prueba de zona geografica.',
            'Generado'
        );

        expect($alerta->Patron)->toBe(EvaluadorRiesgoCliente::PATRON_ZONA_GEOGRAFICA)
            ->and($alerta->IDCliente)->toBe($cliente->IDCliente)
            ->and($alerta->Estatus)->toBe('Generado')
            ->and($alerta->Descripcion)->toBe('Zona geografica detectada: prueba.');

        $enBase = TbAlertas::where('IDRegistroAlerta', $alerta->IDRegistroAlerta)->first();
        expect($enBase)->not->toBeNull()
            ->and($enBase->Patron)->toBe(EvaluadorRiesgoCliente::PATRON_ZONA_GEOGRAFICA);
    } finally {
        DB::connection('mysql')->rollBack();
    }
});

test('el correo al oficial de cumplimiento incluye el enlace al detalle del cliente', function () {
    $correo = view('emails.aviso-oficial-cumplimiento', [
        'datos' => [
            'asunto' => 'Aviso PLD',
            'idCliente' => 6805,
            'nombre' => 'CLIENTE DE PRUEBA',
            'tipo' => 'persona',
            'fecha' => '01 de enero de 2025',
            'hora' => '10:00',
            'registrosEncontrados' => 0,
            'nombresDetectados' => '',
            'listasDetectadas' => '',
            'categoria' => '2: Prueba',
            'observaciones' => 'Prueba',
            'esPPE' => false,
            'patrones' => [
                ['patron' => 'Actividad vulnerable', 'descripcion' => 'Ocupacion de alto riesgo.'],
                ['patron' => 'Zona geografica', 'descripcion' => 'Domicilio en estado de alto riesgo.'],
            ],
            'esAltoRiesgo' => true,
            'factoresRiesgo' => [
                'Actividad vulnerable: ocupacion/giro "Comercio" con nivel de riesgo alto.',
                'Zona geografica: domicilio en Sinaloa (IndicePaz = 3).',
            ],
            'urlDetalleCliente' => 'https://uat-pld.tlalocseguros.com/clientes/ver-detalles/6805',
        ],
    ])->render();

    expect($correo)->toContain('perfil de alto riesgo')
        ->and($correo)->toContain('/clientes/ver-detalles/6805')
        ->and($correo)->toContain('Actividad vulnerable')
        ->and($correo)->toContain('Zona geografica')
        ->and($correo)->toContain('Alto riesgo');
});
