<?php

namespace App\Console\Commands;

use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Services\PLD\AnalisisPagosService;
use App\Services\PLD\PrimaCancelacionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige endosos de cancelación históricos cuya prima no es el espejo
 * exacto del historial previo de la póliza (balance distinto de 0).
 *
 * Uso:
 *   php artisan operaciones:corregir-cancelaciones --dry-run   (solo lista)
 *   php artisan operaciones:corregir-cancelaciones              (aplica)
 *   php artisan operaciones:corregir-cancelaciones --poliza=2026-07-000335
 */
class CorregirCancelaciones extends Command
{
    protected $signature = 'operaciones:corregir-cancelaciones
                            {--dry-run : Solo lista las pólizas afectadas sin modificar nada}
                            {--poliza= : Limitar a un FolioPoliza específico}';

    protected $description = 'Corrige primas de endosos de cancelación al espejo exacto del historial (balance 0) y sincroniza MontoOperacion de alertas';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $filtroPoliza = $this->option('poliza');

        $query = TbOperaciones::where('EsEndosoCancelacion', true)
            ->orderBy('FolioPoliza')
            ->orderBy('FechaEmision')
            ->orderBy('IDOperacion');

        if (! empty($filtroPoliza)) {
            $query->where('FolioPoliza', trim((string) $filtroPoliza));
        }

        $cancelaciones = $query->get();

        if ($cancelaciones->isEmpty()) {
            $this->info('No se encontraron endosos de cancelación.');

            return self::SUCCESS;
        }

        // Agrupar por póliza para calcular el acumulado previo a cada cancelación
        // en orden cronológico (emisión + endosos no-cancelación previos + cancelaciones previas).
        $polizas = $cancelaciones->pluck('FolioPoliza')->unique()->values();
        $afectadas = [];
        $corregidas = 0;

        $this->info($dryRun ? 'Modo DRY-RUN: no se modificará nada.' : 'Aplicando correcciones...');

        foreach ($polizas as $folio) {
            $todas = TbOperaciones::where('FolioPoliza', $folio)
                ->orderBy('FechaEmision')
                ->orderBy('IDOperacion')
                ->get();

            $acumulado = 0.0;
            $huboPrevia = false;
            foreach ($todas as $op) {
                if ((bool) $op->EsEndosoCancelacion) {
                    if (! $huboPrevia) {
                        // Sin historial previo no hay espejo contra el cual corregir
                        // (equivale al caso SIN_BASE de evaluarReglasCancelacion).
                        continue;
                    }
                    $esperada = round(-$acumulado, 2) + 0.0;
                    $actual = round((float) $op->PrimaTotal, 2);
                    $hayDiferencia = PrimaCancelacionService::necesitaCorreccion($actual, $esperada);

                    if ($hayDiferencia) {
                        $afectadas[] = [
                            'poliza' => $folio,
                            'id_operacion' => $op->IDOperacion,
                            'folio_endoso' => $op->FolioEndoso,
                            'recibida' => $actual,
                            'esperada' => $esperada,
                            'diferencia' => round($actual - $esperada, 2),
                        ];

                        if (! $dryRun) {
                            DB::transaction(function () use ($op, $esperada, $actual, $folio, $acumulado) {
                                $op->PrimaTotal = $esperada;
                                $op->save();

                                // Sincronizar MontoOperacion de alertas de cancelación de esta operación
                                TbAlertas::where('IDOperacion', $op->IDOperacion)
                                    ->where('Patron', AnalisisPagosService::PATRON_CANCELACION)
                                    ->update(['MontoOperacion' => $esperada]);

                                Log::warning('[Cancelacion][Historico] Prima corregida', [
                                    'folio_poliza' => $folio,
                                    'id_operacion' => $op->IDOperacion,
                                    'prima_recibida' => $actual,
                                    'suma_historial_previa' => round($acumulado, 2),
                                    'prima_aplicada' => $esperada,
                                ]);
                            });
                            $corregidas++;
                        }

                        // Tras la corrección (real o simulada) el valor vigente es la esperada.
                        $acumulado = round($acumulado + $esperada, 2);
                    } else {
                        $acumulado = round($acumulado + $actual, 2);
                    }
                } else {
                    $acumulado = round($acumulado + (float) $op->PrimaTotal, 2);
                }
                $huboPrevia = true;
            }
        }

        if (empty($afectadas)) {
            $this->info('Sin diferencias: todas las cancelaciones ya son espejo exacto del historial.');

            return self::SUCCESS;
        }

        $this->table(
            ['Póliza', 'ID Op', 'Folio Endoso', 'Recibida', 'Esperada', 'Diferencia'],
            array_map(fn ($a) => [$a['poliza'], $a['id_operacion'], $a['folio_endoso'], $a['recibida'], $a['esperada'], $a['diferencia']], $afectadas)
        );

        if ($dryRun) {
            $this->warn(count($afectadas).' cancelación(es) por corregir. Ejecuta sin --dry-run para aplicar.');
        } else {
            $this->info($corregidas.' cancelación(es) corregidas + alertas sincronizadas.');
        }

        return self::SUCCESS;
    }
}
