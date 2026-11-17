<?php

namespace App\Console\Commands;

use App\Services\PLD\DeduplicadorAlertasCliente;
use Illuminate\Console\Command;

/**
 * Elimina alertas de nivel cliente duplicadas por cliente/patrón.
 *
 * Para "Actividad vulnerable" la unicidad se calcula por identidad de persona
 * (RFC/CURP/nombre), por lo que clientes repetidos con el mismo RFC no generan
 * alertas duplicadas.
 *
 * Uso:
 *   php artisan pld:deduplicar-alertas-cliente --dry-run
 *   php artisan pld:deduplicar-alertas-cliente
 *   php artisan pld:deduplicar-alertas-cliente --patron="Actividad vulnerable"
 */
class DeduplicarAlertasCliente extends Command
{
    protected $signature = 'pld:deduplicar-alertas-cliente
                            {--patron= : Patrón a deduplicar (por defecto: Actividad vulnerable)}
                            {--dry-run : Solo muestra los cambios sin modificar la base}';

    protected $description = 'Elimina alertas de cliente duplicadas, conservando la más antigua';

    public function handle(DeduplicadorAlertasCliente $deduplicador): int
    {
        $patron = trim((string) $this->option('patron')) ?: 'Actividad vulnerable';
        $dryRun = (bool) $this->option('dry-run');

        $resultado = $deduplicador->deduplicar($patron, $dryRun);

        if ($resultado['grupos'] === 0) {
            $this->info("No se encontraron alertas duplicadas para el patrón '{$patron}'.");

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Modo DRY-RUN: no se modificará nada.' : 'Aplicando deduplicación...');
        $this->newLine();

        foreach ($resultado['detalle'] as $item) {
            $referencia = $item['cliente'] ?? substr((string) $item['clave'], 0, 16).'…';
            $this->line(sprintf(
                'Referencia %s: conservar %s | eliminar %s',
                $referencia,
                $item['conservar'],
                implode(', ', $item['eliminar'])
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s Grupos con duplicados: %d | Alertas sobrantes: %d',
            $dryRun ? 'Detectados:' : 'Corregidos:',
            $resultado['grupos'],
            $resultado['sobrantes']
        ));

        return self::SUCCESS;
    }
}
