<?php

use App\Mail\AvisoOficialCumplimiento;
use App\Mail\AvisoRiesgoAlto;
use App\Models\CatCorreoNotificacion;
use App\Models\CatParametriaPLD;
use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Verifica de punta a punta las reglas de notificación/alertamiento:
 *  - Ubicación geográfica de alto riesgo: correo, sin alerta.
 *  - Ocupación con NivelRiesgo = 3: correo y alerta.
 *  - Emisión con ubicación de alto riesgo y prima >= parámetro 14: correo, sin alerta.
 */
beforeEach(function () {
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');
    DB::connection('mysql')->beginTransaction();

    Mail::fake();

    CatCorreoNotificacion::where('Archivo', 'OficialCumplimiento')->update(['Activo' => 0]);
    $config = CatCorreoNotificacion::firstOrNew(['Archivo' => 'OficialCumplimiento']);
    $config->fill([
        'Correo' => 'oficial-pruebas@example.test',
        'Nombre' => 'Oficial de Pruebas',
        'Activo' => 1,
    ])->save();

    $user = User::create([
        'usuario' => 'test_'.Str::lower(Str::random(10)),
        'nombre' => 'Usuario',
        'apellido_p' => 'Prueba',
        'apellido_m' => 'PLD',
        'primer_login' => 1,
        'email' => Str::lower(Str::random(10)).'@example.test',
        'password' => 'password',
    ]);
    Sanctum::actingAs($user);

    $this->lowOccupation = CatOcupacionesGiros::where('NivelRiesgo', '<>', 3)
        ->whereRaw("CVE_GIRO REGEXP '^[1-9][0-9]{5,6}$'")
        ->first();
    $this->highOccupation = CatOcupacionesGiros::where('NivelRiesgo', 3)
        ->whereRaw("CVE_GIRO REGEXP '^[1-9][0-9]{5,6}$'")
        ->first();
    $this->lowState = CatEstados::where('IDEstado', '>', 0)
        ->whereRaw('CAST(IndicePaz AS DECIMAL(10,2)) < 3')
        ->first();
    $this->highState = CatEstados::where('IDEstado', '>', 0)
        ->whereRaw('CAST(IndicePaz AS DECIMAL(10,2)) >= 3')
        ->first();

    expect($this->lowOccupation)->not->toBeNull()
        ->and($this->highOccupation)->not->toBeNull()
        ->and($this->lowState)->not->toBeNull()
        ->and($this->highState)->not->toBeNull();
});

afterEach(function () {
    while (DB::connection('mysql')->transactionLevel() > 0) {
        DB::connection('mysql')->rollBack();
    }
});

function crearClienteReglasNotificacion(int $idEstado): TbClientes
{
    $cliente = TbClientes::create([
        'RFC' => 'TST'.Str::upper(Str::random(12)),
        'IDTipoPersona' => 1,
        'IDNacionalidad' => 'MX',
        'CoincideEnListasNegras' => 0,
        'EsPPEActivo' => false,
        'EsAltoRiesgo' => false,
        'Activo' => true,
        'TipoPersona' => 'Cliente',
    ]);

    $cliente->domicilios()->create([
        'Calle' => 'CALLE PRUEBA',
        'Colonia' => 'COLONIA PRUEBA',
        'CP' => '01000',
        'IDEstado' => (string) $idEstado,
        'Municipio' => 'MUNICIPIO PRUEBA',
    ]);

    return $cliente;
}

function payloadClienteReglasNotificacion(
    string $rfc,
    int $cveOcupacion,
    int $idEstado
): array {
    return [
        'RFC' => $rfc,
        'IDTipoPersona' => 1,
        'IDNacionalidad' => 'MX',
        'IDOcupacionGiro' => $cveOcupacion,
        'domicilios' => [
            [
                'calle' => 'CALLE PRUEBA',
                'colonia' => 'COLONIA PRUEBA',
                'CP' => '01000',
                'IDEstado' => $idEstado,
                'municipio' => 'MUNICIPIO PRUEBA',
            ],
        ],
    ];
}

test('alta de cliente con ubicación de alto riesgo envía correo y no crea alerta', function () {
    $payload = payloadClienteReglasNotificacion(
        'TZ'.Str::upper(Str::random(12)),
        (int) $this->lowOccupation->CVE_GIRO,
        (int) $this->highState->IDEstado
    );

    $response = $this->postJson('/api/clientes/guardarCliente', $payload);

    $response->assertStatus(201)->assertJsonPath('codigoError', 0);

    $idCliente = (int) $response->json('IDCliente');
    $cliente = TbClientes::find($idCliente);

    expect($cliente)->not->toBeNull()
        ->and((int) $cliente->EsAltoRiesgo)->toBe(1)
        ->and(TbAlertas::where('IDCliente', $idCliente)->count())->toBe(0);

    Mail::assertSent(AvisoOficialCumplimiento::class);
});

test('alta de cliente con ocupación NivelRiesgo 3 envía correo y crea alerta', function () {
    $payload = payloadClienteReglasNotificacion(
        'TO'.Str::upper(Str::random(12)),
        (int) $this->highOccupation->CVE_GIRO,
        (int) $this->lowState->IDEstado
    );

    $response = $this->postJson('/api/clientes/guardarCliente', $payload);

    $response->assertStatus(201)->assertJsonPath('codigoError', 0);

    $idCliente = (int) $response->json('IDCliente');
    $cliente = TbClientes::find($idCliente);

    expect($cliente)->not->toBeNull()
        ->and((int) $cliente->EsAltoRiesgo)->toBe(1)
        ->and(TbAlertas::where('IDCliente', $idCliente)
            ->where('Patron', 'Actividad vulnerable')
            ->count())->toBe(1);

    Mail::assertSent(AvisoOficialCumplimiento::class);
});

test('edición de cliente con ubicación de alto riesgo envía correo y no crea alerta', function () {
    $cliente = crearClienteReglasNotificacion((int) $this->lowState->IDEstado);

    $payload = payloadClienteReglasNotificacion(
        $cliente->RFC,
        (int) $this->lowOccupation->CVE_GIRO,
        (int) $this->highState->IDEstado
    );

    $response = $this->putJson('/api/clientes/'.$cliente->IDCliente, $payload);

    $response->assertStatus(200)->assertJsonPath('codigoError', 0);

    $cliente->refresh();

    expect((int) $cliente->EsAltoRiesgo)->toBe(1)
        ->and(TbAlertas::where('IDCliente', $cliente->IDCliente)->count())->toBe(0);

    Mail::assertSent(AvisoOficialCumplimiento::class);
});

test('edición de cliente con ocupación NivelRiesgo 3 envía correo y crea alerta', function () {
    $cliente = crearClienteReglasNotificacion((int) $this->lowState->IDEstado);

    $payload = payloadClienteReglasNotificacion(
        $cliente->RFC,
        (int) $this->highOccupation->CVE_GIRO,
        (int) $this->lowState->IDEstado
    );

    $response = $this->putJson('/api/clientes/'.$cliente->IDCliente, $payload);

    $response->assertStatus(200)->assertJsonPath('codigoError', 0);

    $cliente->refresh();

    expect((int) $cliente->EsAltoRiesgo)->toBe(1)
        ->and(TbAlertas::where('IDCliente', $cliente->IDCliente)
            ->where('Patron', 'Actividad vulnerable')
            ->count())->toBe(1);

    Mail::assertSent(AvisoOficialCumplimiento::class);
});

test('emisión con ubicación de alto riesgo y prima igual al parámetro 14 envía correo sin alerta', function () {
    $cliente = crearClienteReglasNotificacion((int) $this->highState->IDEstado);
    $umbral = (float) CatParametriaPLD::getValor(CatParametriaPLD::MONTO_MINIMO_ALERTA, 7500);

    $payload = [
        'IDCliente' => $cliente->IDCliente,
        'FolioPoliza' => 'TST'.Str::upper(Str::random(16)),
        'FechaEmision' => now()->toDateString(),
        'PrimaTotal' => $umbral,
        'IDMoneda' => 'USD',
        'FechaInicioVigencia' => now()->toDateString(),
        'FechaFinVigencia' => now()->addDays(365)->toDateString(),
        'GastosEmision' => 0,
        'EsEndosoCancelacion' => false,
        'PagaTercero' => false,
    ];

    $response = $this->postJson('/api/insertar/operacion', $payload);

    $response->assertStatus(201)->assertJsonPath('codigoError', 0);

    $idOperacion = (int) $response->json('IDOperacion');

    expect(TbAlertas::where('IDOperacion', $idOperacion)->count())->toBe(0);

    Mail::assertSent(AvisoRiesgoAlto::class);
});

test('emisión con ubicación de alto riesgo y prima menor al parámetro 14 no envía correo ni crea alerta', function () {
    $cliente = crearClienteReglasNotificacion((int) $this->highState->IDEstado);
    $umbral = (float) CatParametriaPLD::getValor(CatParametriaPLD::MONTO_MINIMO_ALERTA, 7500);

    $payload = [
        'IDCliente' => $cliente->IDCliente,
        'FolioPoliza' => 'TST'.Str::upper(Str::random(16)),
        'FechaEmision' => now()->toDateString(),
        'PrimaTotal' => $umbral - 0.01,
        'IDMoneda' => 'USD',
        'FechaInicioVigencia' => now()->toDateString(),
        'FechaFinVigencia' => now()->addDays(365)->toDateString(),
        'GastosEmision' => 0,
        'EsEndosoCancelacion' => false,
        'PagaTercero' => false,
    ];

    $response = $this->postJson('/api/insertar/operacion', $payload);

    $response->assertStatus(201)->assertJsonPath('codigoError', 0);

    $idOperacion = (int) $response->json('IDOperacion');

    expect(TbAlertas::where('IDOperacion', $idOperacion)->count())->toBe(0);

    Mail::assertNothingSent();
});

test('editar varias veces un cliente con actividad vulnerable conserva una sola alerta', function () {
    $payload = payloadClienteReglasNotificacion(
        'TV'.Str::upper(Str::random(12)),
        (int) $this->highOccupation->CVE_GIRO,
        (int) $this->lowState->IDEstado
    );

    $alta = $this->postJson('/api/clientes/guardarCliente', $payload);
    $alta->assertStatus(201)->assertJsonPath('codigoError', 0);

    $idCliente = (int) $alta->json('IDCliente');

    $alertaOriginal = TbAlertas::where('IDCliente', $idCliente)
        ->where('Patron', 'Actividad vulnerable')
        ->first();

    expect($alertaOriginal)->not->toBeNull();

    $this->putJson('/api/clientes/'.$idCliente, $payload)
        ->assertStatus(200)
        ->assertJsonPath('codigoError', 0);

    $this->putJson('/api/clientes/'.$idCliente, $payload)
        ->assertStatus(200)
        ->assertJsonPath('codigoError', 0);

    $alertas = TbAlertas::where('IDCliente', $idCliente)
        ->where('Patron', 'Actividad vulnerable')
        ->orderBy('IDRegistroAlerta')
        ->pluck('IDRegistroAlerta');

    expect($alertas)->toHaveCount(1)
        ->and((int) $alertas->first())->toBe((int) $alertaOriginal->IDRegistroAlerta);
});

test('el comando deduplica alertas de cliente conservando la más antigua', function () {
    $cliente = crearClienteReglasNotificacion((int) $this->lowState->IDEstado);

    $crearDuplicado = fn () => TbAlertas::create([
        'Patron' => 'Actividad vulnerable',
        'IDCliente' => $cliente->IDCliente,
        'Cliente' => 'CLIENTE PRUEBA',
        'Estatus' => 'Generado',
        'Descripcion' => 'Duplicado de prueba',
        'Razones' => 'Duplicado de prueba',
    ]);

    $original = $crearDuplicado();
    $crearDuplicado();
    $crearDuplicado();

    expect(TbAlertas::where('IDCliente', $cliente->IDCliente)
        ->where('Patron', 'Actividad vulnerable')
        ->count())->toBe(3);

    $this->artisan('pld:deduplicar-alertas-cliente', [
        '--patron' => 'Actividad vulnerable',
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(TbAlertas::where('IDCliente', $cliente->IDCliente)
        ->where('Patron', 'Actividad vulnerable')
        ->count())->toBe(3);

    $this->artisan('pld:deduplicar-alertas-cliente', [
        '--patron' => 'Actividad vulnerable',
    ])->assertExitCode(0);

    $restantes = TbAlertas::where('IDCliente', $cliente->IDCliente)
        ->where('Patron', 'Actividad vulnerable')
        ->pluck('IDRegistroAlerta');

    expect($restantes)->toHaveCount(1)
        ->and((int) $restantes->first())->toBe((int) $original->IDRegistroAlerta);
});

test('actividad vulnerable no se duplica entre clientes con el mismo RFC', function () {
    $rfc = 'ID'.Str::upper(Str::random(12));

    $cliente1 = crearClienteReglasNotificacion((int) $this->lowState->IDEstado);
    $cliente1->RFC = $rfc;
    $cliente1->save();

    $cliente2 = crearClienteReglasNotificacion((int) $this->lowState->IDEstado);
    $cliente2->RFC = $rfc;
    $cliente2->save();

    $atributos = fn (TbClientes $cliente) => [
        'Patron' => 'Actividad vulnerable',
        'IDCliente' => $cliente->IDCliente,
        'Cliente' => 'CLIENTE PRUEBA',
        'Estatus' => 'Generado',
        'Descripcion' => 'Duplicado de prueba',
        'Razones' => 'Duplicado de prueba',
    ];

    $primera = TbAlertas::crearParaClienteSiNoExiste($atributos($cliente1), $cliente1);
    $segunda = TbAlertas::crearParaClienteSiNoExiste($atributos($cliente2), $cliente2);

    expect((int) $segunda->IDRegistroAlerta)->toBe((int) $primera->IDRegistroAlerta)
        ->and(TbAlertas::where('Patron', 'Actividad vulnerable')
            ->whereIn('IDCliente', [$cliente1->IDCliente, $cliente2->IDCliente])
            ->count())->toBe(1);
});

test('la base de datos impide duplicar actividad vulnerable para la misma identidad', function () {
    $cliente = crearClienteReglasNotificacion((int) $this->lowState->IDEstado);
    $cliente->RFC = 'ID'.Str::upper(Str::random(12));
    $cliente->save();

    $clave = TbAlertas::claveActividadVulnerable($cliente);
    $atributos = [
        'Patron' => 'Actividad vulnerable',
        'ActividadVulnerableUnica' => $clave,
        'IDCliente' => $cliente->IDCliente,
        'Cliente' => 'CLIENTE PRUEBA',
        'Estatus' => 'Generado',
        'Descripcion' => 'Duplicado de prueba',
        'Razones' => 'Duplicado de prueba',
    ];

    TbAlertas::create($atributos);

    expect(fn () => TbAlertas::create($atributos))->toThrow(QueryException::class);
});
