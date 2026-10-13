<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Alinea FechaDeteccion con la FechaEmision de la póliza.
     *
     * Paso 1: alertas ligadas a una operación (IDOperacion) cuya
     *         FechaDeteccion difiera de la FechaEmision.
     * Paso 2: alertas sin IDOperacion pero con Poliza que existe en
     *         tbOperaciones: usa la emisión más antigua de la póliza.
     * Las alertas de nivel cliente (Personas Bloqueadas al alta, etc.)
     * sin operación ni póliza se dejan intactas.
     */
    public function up(): void
    {
        $paso1 = DB::table('tbAlertas as a')
            ->join('tbOperaciones as o', 'o.IDOperacion', '=', 'a.IDOperacion')
            ->whereRaw('a.FechaDeteccion <> DATE(o.FechaEmision)')
            ->update(['a.FechaDeteccion' => DB::raw('DATE(o.FechaEmision)')]);

        $paso2 = DB::update(
            "UPDATE tbAlertas a
             JOIN (SELECT FolioPoliza, MIN(DATE(FechaEmision)) AS emision
                   FROM tbOperaciones
                   WHERE FolioPoliza IS NOT NULL AND FolioPoliza <> ''
                   GROUP BY FolioPoliza) m ON m.FolioPoliza = a.Poliza
             SET a.FechaDeteccion = m.emision
             WHERE a.IDOperacion IS NULL
               AND a.Poliza IS NOT NULL AND a.Poliza <> ''
               AND a.FechaDeteccion <> m.emision"
        );

        Log::info('[Backfill FechaDeteccion] Alertas alineadas a FechaEmision de póliza.', [
            'paso1_por_id_operacion' => $paso1,
            'paso2_por_folio_poliza' => $paso2,
        ]);
    }

    /**
     * No reversible: el valor anterior (fecha del momento) no se conserva.
     */
    public function down(): void
    {
        Log::warning('[Backfill FechaDeteccion] Sin reversión: valores previos no conservados.');
    }
};
