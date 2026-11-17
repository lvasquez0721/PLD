<?php

use App\Services\PLD\TipoCambioService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['database.connections.mysql.database' => 'PLD-UAT']);
    DB::purge('mysql');
    config(['services.banxico.token' => 'test-token']);

    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

function fakeBanxicoFix(array $datos): void
{
    Http::fake([
        'www.banxico.org.mx/*' => Http::response([
            'bmx' => [
                'series' => [
                    ['datos' => $datos],
                ],
            ],
        ], 200),
    ]);
}

function sembrarTipoCambio(array $valores): void
{
    foreach ($valores as $fecha => $valor) {
        DB::table('catTipoCambio')->updateOrInsert(
            ['Fecha' => $fecha],
            ['DOFDolar' => $valor]
        );
    }
}

test('sincroniza la FIX de Banxico en catTipoCambio', function () {
    fakeBanxicoFix([
        ['fecha' => '10/11/2026', 'dato' => '18.6678'],
        ['fecha' => '11/11/2026', 'dato' => '18.6192'],
    ]);

    $resultado = app(TipoCambioService::class)->sincronizar('2026-11-12', 3);

    expect($resultado['ok'])->toBeTrue()
        ->and(DB::table('catTipoCambio')->where('Fecha', '2026-11-10')->value('DOFDolar'))->toBe('18.6678')
        ->and(DB::table('catTipoCambio')->where('Fecha', '2026-11-11')->value('DOFDolar'))->toBe('18.6192');
});

test('usa la FIX publicada el día hábil anterior', function () {
    sembrarTipoCambio([
        '2026-11-05' => '18.7147',
        '2026-11-06' => '18.6297',
        '2026-11-09' => '18.5983',
        '2026-11-10' => '18.6678',
        '2026-11-11' => '18.6192',
    ]);

    $servicio = app(TipoCambioService::class);

    expect($servicio->obtenerTipoCambioParaOperacion('2026-11-10'))->toBe(18.6297)
        ->and($servicio->obtenerTipoCambioParaOperacion('2026-11-12'))->toBe(18.6678)
        ->and($servicio->obtenerTipoCambioParaOperacion('2026-11-08'))->toBe(18.7147)
        ->and($servicio->obtenerTipoCambioParaOperacion('2026-11-09'))->toBe(18.7147);
});

test('hace backfill cuando falta una FIX en el historial local', function () {
    DB::table('catTipoCambio')
        ->whereIn('Fecha', ['2026-11-10', '2026-11-11'])
        ->delete();

    sembrarTipoCambio([
        '2026-11-06' => '18.6297',
        '2026-11-09' => '18.5983',
    ]);

    fakeBanxicoFix([
        ['fecha' => '10/11/2026', 'dato' => '18.6678'],
        ['fecha' => '11/11/2026', 'dato' => '18.6192'],
    ]);

    expect(app(TipoCambioService::class)->obtenerTipoCambioParaOperacion('2026-11-12'))->toBe(18.6678)
        ->and(DB::table('catTipoCambio')->where('Fecha', '2026-11-11')->value('DOFDolar'))->toBe('18.6192');
});

test('convierte MXN a USD y USD a MXN con la FIX aplicable', function () {
    sembrarTipoCambio([
        '2026-11-06' => '18.6297',
        '2026-11-09' => '18.5983',
    ]);

    $servicio = app(TipoCambioService::class);

    expect(round($servicio->convertirAUSD(150000, 'MXN', null, '2026-11-10'), 2))->toBe(8051.66)
        ->and(round($servicio->convertirAMXN(1000, 'USD', null, '2026-11-10'), 2))->toBe(18629.70);
});

test('devuelve null cuando no hay FIX disponible', function () {
    fakeBanxicoFix([]);

    expect(app(TipoCambioService::class)->obtenerTipoCambioParaOperacion('1900-01-01'))->toBeNull();
});

test('el comando actualiza catTipoCambio', function () {
    fakeBanxicoFix([
        ['fecha' => '12/11/2026', 'dato' => '18.6188'],
    ]);

    $this->artisan('tipocambio:actualizar', ['fecha' => '2026-11-12'])
        ->assertSuccessful();

    expect(DB::table('catTipoCambio')->where('Fecha', '2026-11-12')->value('DOFDolar'))->toBe('18.6188');
});
