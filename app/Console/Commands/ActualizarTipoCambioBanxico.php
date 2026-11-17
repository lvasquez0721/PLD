<?php

namespace App\Console\Commands;

use App\Services\PLD\TipoCambioService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActualizarTipoCambioBanxico extends Command
{
    protected $signature = 'tipocambio:actualizar
                            {fecha? : Fecha final a sincronizar en formato Y-m-d. Por defecto hoy.}
                            {--dias=7 : Días hacia atrás que se sincronizarán.}';

    protected $description = 'Obtiene el FIX de Banxico y actualiza la tabla catTipoCambio.';

    public function handle(TipoCambioService $tipoCambioService): int
    {
        try {
            $fecha = $this->argument('fecha')
                ? Carbon::parse((string) $this->argument('fecha'))->toDateString()
                : now()->toDateString();
        } catch (\Throwable $e) {
            $this->error('Fecha inválida: '.(string) $this->argument('fecha'));

            return self::FAILURE;
        }

        $dias = max(1, (int) $this->option('dias'));
        $resultado = $tipoCambioService->sincronizar($fecha, $dias);

        if (! $resultado['ok']) {
            $this->error($resultado['mensaje']);

            return self::FAILURE;
        }

        $this->info($resultado['mensaje']);

        foreach ($resultado['registros'] as $registro) {
            $this->line(sprintf(
                '  %s  %s  (%s)',
                $registro['fecha'],
                number_format($registro['dato'], 4, '.', ''),
                $registro['estado']
            ));
        }

        return self::SUCCESS;
    }
}
