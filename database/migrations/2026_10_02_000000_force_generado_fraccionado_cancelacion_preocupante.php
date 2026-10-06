<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Regla fija: los patrones Fraccionado, Cancelacion y Preocupante
     * siempre se emiten con Estatus = Generado.
     *
     * Backfill histórico: lleva a Generado todo el stock con esos patrones
     * que no esté ya en Generado. Se excluye Enviado para no romper la
     * trazabilidad de lo ya reportado (reporte regulatorio emitido).
     *
     * Nota: Preocupante en Generado deja de listarse en /reporte-operaciones,
     * que solo muestra Por reportar / Enviado.
     */
    public function up(): void
    {
        DB::table('tbAlertas')
            ->whereIn('Patron', ['Fraccionado', 'Cancelacion', 'Preocupante'])
            ->whereNotIn('Estatus', ['Generado', 'Enviado'])
            ->update(['Estatus' => 'Generado']);
    }

    public function down(): void
    {
        // Backfill irreversible por diseño: no se puede saber el estatus previo.
    }
};
