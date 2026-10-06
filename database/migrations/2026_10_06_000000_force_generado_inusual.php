<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Regla fija: el patrón Inusual siempre se emite con Estatus = Generado.
     *
     * Backfill histórico: lleva a Generado todo el stock con patrón Inusual
     * que no esté ya en Generado. Se excluye Enviado para no romper la
     * trazabilidad de lo ya reportado (reporte regulatorio emitido).
     */
    public function up(): void
    {
        DB::table('tbAlertas')
            ->where('Patron', 'Inusual')
            ->whereNotIn('Estatus', ['Generado', 'Enviado'])
            ->update(['Estatus' => 'Generado']);
    }

    public function down(): void
    {
        // Backfill irreversible por diseño: no se puede saber el estatus previo.
    }
};
