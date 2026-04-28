#!/usr/bin/env php
<?php

/**
 * Script para insertar datos de prueba en tbOperaciones y tbOperacionesPagos.
 *
 * Ejecutar con:
 *   php tinker_test_data.php
 *
 * NOTA: IDMoneda usa strings ('MXN', 'USD') porque las migraciones
 * de conversión cambiaron el tipo de columna a varchar.
 */

use App\Models\TbOperaciones;
use App\Models\TbOperacionesPagos;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ── Configuración ────────────────────────────────────────────────────────────
$clientes_ids = [
    14, 74, 88, 89, 539, 635, 820, 821, 1019, 2346,
    2551, 2552, 2733, 2892, 3104, 3348, 3525, 3570, 3843,
    3869, 4208, 4316, 4322, 4365, 4562, 5249, 6060,
];
$tipoCambio = 17.50;
// IDFormaPago (catFormaPagos tiene: 1=Efectivo, 2=Cheque, 3=Transferencia, 4=TDD, 5=TDC, etc.)
$formasPago = ['1', '2', '3', '4', '5', '6'];
// Monedas (IDMoneda ahora es varchar: 'MXN', 'USD')
$monedaMXN = 'MXN';
$monedaUSD = 'USD';

echo "=== Insertando datos de prueba para Reporteador PLD ===\n";
echo "Clientes existentes: " . count($clientes_ids) . "\n";
echo "Tipo de cambio: $tipoCambio\n";
echo "Monedas: $monedaMXN, $monedaUSD\n\n";

// Clientes con montos altos (probarán umbrales USD >=500/10K/50K y MXN >=300K/500K)
$montoAlto  = [14, 74, 539, 820, 2551, 3104, 3869, 4322, 4562, 6060];
$montoMedio = [88, 821, 2346, 2733, 3525, 4208, 5249];

$operacionesCreadas = 0;
$pagosCreados = 0;

// Limpiar datos de prueba anteriores (si existen)
DB::table('tbOperacionesPagos')->whereIn('IDCliente', $clientes_ids)->delete();
DB::table('tbOperaciones')->whereIn('IDCliente', $clientes_ids)->delete();

foreach ($clientes_ids as $idx => $idCliente) {
    // 2 operaciones para la primera mitad de clientes, 1 para el resto
    $numOps = ($idx < 13) ? 2 : 1;

    for ($i = 0; $i < $numOps; $i++) {
        $folioPoliza = sprintf('PLD-%04d-%02d', $idCliente, $i + 1);
        $fechaEmision = Carbon::now()->subDays(rand(30, 180));

        $op = TbOperaciones::create([
            'IDCliente'          => $idCliente,
            'IDMoneda'           => ($idCliente % 3 === 0) ? $monedaUSD : $monedaMXN,
            'FechaInicioVigencia'=> $fechaEmision->copy()->format('Y-m-d'),
            'FechaFinVigencia'   => $fechaEmision->copy()->addYear()->format('Y-m-d'),
            'RazonSocialAgente'  => 'Agente de Prueba PLD',
            'FolioPoliza'        => $folioPoliza,
            'FolioEndoso'        => null,
            'FechaEmision'       => $fechaEmision->format('Y-m-d'),
            'PrimaTotal'         => rand(5000, 200000),
            'GastosEmision'      => rand(100, 5000),
            'RFCAgente'          => 'AGTP' . str_pad($idCliente, 6, '0', STR_PAD_LEFT),
            'CURPAgente'         => null,
            'NombreAgente'       => 'Agente Prueba ' . $idCliente,
            'APaternoAgente'     => 'Paterno',
            'AMaternoAgente'     => 'Materno',
            'tipoDocumento'      => 'Poliza',
            'cancelaPoliza'      => false,
        ]);

        $idOperacion = $op->IDOperacion;
        $operacionesCreadas++;

        // ── Pagos ───────────────────────────────────────────────────────────
        $numPagos = rand(2, 5);
        $esAlto   = in_array($idCliente, $montoAlto);
        $esMedio  = in_array($idCliente, $montoMedio);
        $usaUSD   = ($idCliente % 3 === 0);

        for ($j = 0; $j < $numPagos; $j++) {
            $fechaPago = $fechaEmision->copy()->addDays($j * rand(7, 40));

            if ($usaUSD) {
                if ($esAlto) {
                    $monto = round(rand(10000, 55000) + (rand(0, 99) / 100), 2);
                } elseif ($esMedio) {
                    $monto = round(rand(500, 9500) + (rand(0, 99) / 100), 2);
                } else {
                    $monto = round(rand(50, 490) + (rand(0, 99) / 100), 2);
                }
                $idMoneda = $monedaUSD;
            } else {
                if ($esAlto) {
                    $monto = round(rand(300000, 800000) + (rand(0, 99) / 100), 2);
                } elseif ($esMedio) {
                    $monto = round(rand(50000, 290000) + (rand(0, 99) / 100), 2);
                } else {
                    $monto = round(rand(1000, 49000) + (rand(0, 99) / 100), 2);
                }
                $idMoneda = $monedaMXN;
            }

            TbOperacionesPagos::create([
                'IDOperacion'       => $idOperacion,
                'IDCliente'         => $idCliente,
                'Monto'             => $monto,
                'IDMoneda'          => $idMoneda,
                'IDFormaPago'       => $formasPago[array_rand($formasPago)],
                'TipoCambio'        => $tipoCambio,
                'FechaPago'         => $fechaPago->format('Y-m-d'),
                'TimeStampRegistro' => $fechaPago->format('Y-m-d H:i:s'),
            ]);

            $pagosCreados++;
        }
    }
}

echo "============================================\n";
echo "Operaciones creadas: $operacionesCreadas\n";
echo "Pagos creados:       $pagosCreados\n";
echo "============================================\n";

// ── Resumen ──────────────────────────────────────────────────────────────────
$resumen = DB::table('tbOperacionesPagos')
    ->select('IDMoneda', DB::raw('COUNT(*) as total'), DB::raw('SUM(Monto) as suma'))
    ->groupBy('IDMoneda')
    ->get();

echo "\nResumen de pagos por moneda:\n";
foreach ($resumen as $r) {
    $label = $r->IDMoneda === $monedaMXN ? 'MXN' : strtoupper($r->IDMoneda);
    printf("  %s: %d pagos, suma: %s\n", $label, $r->total, number_format((float) $r->suma, 2));
}

// ── Validación: verificar que aparecen con los umbrales esperados ────────────
$altosUSD = DB::table('tbOperacionesPagos')
    ->where('IDMoneda', $monedaUSD)
    ->where('Monto', '>=', 500)
    ->distinct('IDCliente')
    ->count('IDCliente');

$altosMXN = DB::table('tbOperacionesPagos')
    ->where('IDMoneda', $monedaMXN)
    ->where('Monto', '>=', 300000)
    ->distinct('IDCliente')
    ->count('IDCliente');

$totalClientesConPagos = DB::table('tbOperacionesPagos')
    ->distinct('IDCliente')
    ->count('IDCliente');

echo "\nClientes con pagos:              $totalClientesConPagos\n";
echo "Clientes USD con pagos >= 500:   $altosUSD\n";
echo "Clientes MXN con pagos >= 300K:  $altosMXN\n";

echo "\nDatos de prueba insertados correctamente.\n";
echo "Ya puedes probar el Reporteador PLD desde el navegador.\n";
