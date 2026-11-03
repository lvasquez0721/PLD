<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tbClientes')
            ->where('ckAgente', 1)
            ->where('TipoPersona', 'Cliente')
            ->update(['TipoPersona' => 'Agente']);
    }

    public function down(): void
    {
        DB::table('tbClientes')
            ->where('ckAgente', 1)
            ->where('TipoPersona', 'Agente')
            ->update(['TipoPersona' => 'Cliente']);
    }
};
