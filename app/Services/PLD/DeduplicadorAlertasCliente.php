<?php

namespace App\Services\PLD;

use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use Illuminate\Support\Facades\DB;

/**
 * Deduplica alertas de nivel cliente.
 *
 * Para "Actividad vulnerable" la unicidad se calcula por identidad de persona
 * (RFC/CURP/nombre), de modo que clientes repetidos con el mismo RFC no generen
 * alertas duplicadas.
 */
class DeduplicadorAlertasCliente
{
    /**
     * @return array{grupos:int, sobrantes:int, detalle:array<int, array<string, mixed>>}
     */
    public function deduplicar(string $patron, bool $dryRun = false): array
    {
        $normalizado = mb_strtolower(trim($patron));

        if ($normalizado === 'actividad vulnerable') {
            return $this->deduplicarActividadVulnerable($dryRun);
        }

        return $this->deduplicarPorCliente($patron, $dryRun);
    }

    /**
     * @return array{grupos:int, sobrantes:int, detalle:array<int, array<string, mixed>>}
     */
    private function deduplicarActividadVulnerable(bool $dryRun): array
    {
        $alertas = DB::table('tbAlertas')
            ->select('IDRegistroAlerta', 'IDCliente')
            ->whereRaw('LOWER(TRIM(Patron)) = ?', ['actividad vulnerable'])
            ->whereNull('IDOperacion')
            ->whereNotNull('IDCliente')
            ->orderBy('IDRegistroAlerta')
            ->get();

        if ($alertas->isEmpty()) {
            return ['grupos' => 0, 'sobrantes' => 0, 'detalle' => []];
        }

        $clientes = TbClientes::whereIn('IDCliente', $alertas->pluck('IDCliente')->unique()->values())
            ->get()
            ->keyBy('IDCliente');

        $grupos = [];
        foreach ($alertas as $alerta) {
            $cliente = $clientes->get($alerta->IDCliente);
            if (! $cliente) {
                continue;
            }

            $clave = TbAlertas::claveActividadVulnerable($cliente);
            $grupos[$clave][] = (int) $alerta->IDRegistroAlerta;
        }

        $detalle = [];
        $sobrantes = 0;
        $gruposConDuplicados = 0;

        foreach ($grupos as $clave => $ids) {
            sort($ids);

            if (count($ids) === 1) {
                if (! $dryRun) {
                    DB::table('tbAlertas')
                        ->where('IDRegistroAlerta', $ids[0])
                        ->update(['ActividadVulnerableUnica' => $clave]);
                }

                continue;
            }

            $conservar = array_shift($ids);
            if ($conservar === null || empty($ids)) {
                continue;
            }

            if (! $dryRun) {
                $this->aplicarDeduplicacion($conservar, $ids, $clave);
            }

            $gruposConDuplicados++;
            $sobrantes += count($ids);
            $detalle[] = [
                'clave' => $clave,
                'conservar' => $conservar,
                'eliminar' => $ids,
            ];
        }

        return [
            'grupos' => $gruposConDuplicados,
            'sobrantes' => $sobrantes,
            'detalle' => $detalle,
        ];
    }

    /**
     * @return array{grupos:int, sobrantes:int, detalle:array<int, array<string, mixed>>}
     */
    private function deduplicarPorCliente(string $patron, bool $dryRun): array
    {
        $clientes = DB::table('tbAlertas')
            ->select('IDCliente')
            ->where('Patron', $patron)
            ->whereNull('IDOperacion')
            ->whereNotNull('IDCliente')
            ->groupBy('IDCliente')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('IDCliente')
            ->pluck('IDCliente');

        $detalle = [];
        $sobrantes = 0;

        foreach ($clientes as $idCliente) {
            $ids = DB::table('tbAlertas')
                ->where('IDCliente', $idCliente)
                ->where('Patron', $patron)
                ->whereNull('IDOperacion')
                ->orderBy('IDRegistroAlerta')
                ->pluck('IDRegistroAlerta')
                ->map(fn ($id) => (int) $id)
                ->all();

            $conservar = array_shift($ids);
            if ($conservar === null || empty($ids)) {
                continue;
            }

            if (! $dryRun) {
                $this->aplicarDeduplicacion($conservar, $ids);
            }

            $sobrantes += count($ids);
            $detalle[] = [
                'cliente' => (int) $idCliente,
                'conservar' => $conservar,
                'eliminar' => $ids,
            ];
        }

        return [
            'grupos' => count($detalle),
            'sobrantes' => $sobrantes,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  array<int, int>  $eliminar
     */
    private function aplicarDeduplicacion(int $conservar, array $eliminar, ?string $clave = null): void
    {
        DB::transaction(function () use ($conservar, $eliminar, $clave): void {
            DB::table('tbPagosAlertas')
                ->whereIn('IDRegistroAlerta', $eliminar)
                ->update(['IDRegistroAlerta' => $conservar]);

            DB::table('tbReporteRegulatorioPLD')
                ->whereIn('IDRegistroAlerta', $eliminar)
                ->update(['IDRegistroAlerta' => $conservar]);

            DB::table('tbAlertas')
                ->whereIn('IDRegistroAlerta', $eliminar)
                ->delete();

            if ($clave !== null) {
                DB::table('tbAlertas')
                    ->where('IDRegistroAlerta', $conservar)
                    ->update(['ActividadVulnerableUnica' => $clave]);
            }
        });
    }
}
