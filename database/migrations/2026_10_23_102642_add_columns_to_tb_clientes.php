<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            $table->enum('TipoPersona', ['Cliente', 'Agente', 'Colaborador'])
                  ->default('Cliente');
        });

        // Set all existing records to 'Cliente'
        DB::table('tbClientes')->update(['TipoPersona' => 'Cliente']);
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Primero aseguramos que ningún dato pueda bloquear el drop de columna
        // Esto no es estríctamente necesario en MySQL, pero puede ayudar con DBs más estrictas.
        // Por ejemplo, podríamos resetear o eliminar valores no estándar aquí si hiciera falta.

        // Eliminar la columna enum requiere tener cuidado si hay restricciones o indices
        Schema::table('tbClientes', function (Blueprint $table) {
            // Si existieran indices sobre la columna se tendrían que eliminar primero aquí
            // (no es el caso aquí, pero lo dejamos documentado)

            $table->dropColumn('TipoPersona');
        });
    }
};
