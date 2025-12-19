<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            // Agregar columna 'Preguntas'
            $table->text('Preguntas')->nullable()->after('Activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            // Eliminar columna 'Preguntas'
            $table->dropColumn('Preguntas');
        });
    }
};
