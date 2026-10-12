<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reactiva todos los clientes inactivos.
     * A partir de ahora el cliente siempre permanece Activo,
     * aun con coincidencia en listas negras.
     */
    public function up(): void
    {
        DB::table('tbClientes')
            ->where('Activo', 0)
            ->orWhereNull('Activo')
            ->update(['Activo' => 1]);
    }

    public function down(): void
    {
        // No reversible: no se puede saber cuáles fueron inactivados por listas.
    }
};
