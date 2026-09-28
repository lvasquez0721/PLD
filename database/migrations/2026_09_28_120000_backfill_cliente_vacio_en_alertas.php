<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill: alertas con IDCliente pero Cliente vacío (típico persona moral,
     * donde crearAlerta solo concatenaba Nombre+Apellidos). Prioridad a RazonSocial.
     */
    public function up(): void
    {
        DB::statement("
            UPDATE tbAlertas a
            JOIN tbClientes c ON c.IDCliente = a.IDCliente
            SET a.Cliente = CASE
                WHEN TRIM(COALESCE(c.RazonSocial, '')) <> ''
                    THEN TRIM(c.RazonSocial)
                ELSE TRIM(CONCAT_WS(' ', NULLIF(TRIM(COALESCE(c.Nombre, '')), ''), NULLIF(TRIM(COALESCE(c.ApellidoPaterno, '')), ''), NULLIF(TRIM(COALESCE(c.ApellidoMaterno, '')), '')))
            END
            WHERE a.IDCliente IS NOT NULL
              AND TRIM(COALESCE(a.Cliente, '')) = ''
        ");
    }

    public function down(): void
    {
        // No reversible: el valor anterior (vacío/espacios) no se puede restaurar.
    }
};
