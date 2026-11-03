<?php

namespace App\Services\ListasNegras;

/**
 * Interpreta la respuesta de Q-Detect para determinar coincidencias PPE.
 *
 * El endpoint /api/find devuelve coincidencias de todas las listas (SAT69,
 * SANC, DEA, PEPINT, etc.), por lo que solo los registros con LISTA = "PPE"
 * y una coincidencia suficientemente alta deben considerarse PPE.
 */
class DetectorPPE
{
    public const COINCIDENCIA_MINIMA = 90;

    /**
     * @param  array<string, mixed>  $dataPPE
     * @return array<int, array<string, mixed>>
     */
    public function filtrar(array $dataPPE, float $minCoincidencia = self::COINCIDENCIA_MINIMA): array
    {
        $registros = $dataPPE['data'] ?? null;

        if (! is_array($registros)) {
            return [];
        }

        return array_values(array_filter($registros, function ($registro) use ($minCoincidencia): bool {
            if (! is_array($registro)) {
                return false;
            }

            $lista = strtoupper(trim((string) ($registro['LISTA'] ?? '')));
            if ($lista !== 'PPE') {
                return false;
            }

            return (float) ($registro['COINCIDENCIA'] ?? 0) >= $minCoincidencia;
        }));
    }

    /**
     * @param  array<string, mixed>  $dataPPE
     */
    public function esPPE(array $dataPPE, float $minCoincidencia = self::COINCIDENCIA_MINIMA): bool
    {
        return $this->filtrar($dataPPE, $minCoincidencia) !== [];
    }

    public function estaActivo(array $registro): bool
    {
        $estatus = strtolower(trim((string) ($registro['ESTATUS'] ?? '')));

        return in_array($estatus, ['activo', 'activa'], true);
    }
}
