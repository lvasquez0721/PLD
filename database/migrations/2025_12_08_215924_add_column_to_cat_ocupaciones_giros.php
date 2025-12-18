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
        Schema::table('catOcupacionesGiros', function (Blueprint $table) {
            // Agregar columna 'NivelRiesgo'
            $table->string('NivelRiesgo')->nullable()->after('OcupacionGiro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catOcupacionesGiros', function (Blueprint $table) {
            // Eliminar columna 'NivelRiesgo'
            $table->dropColumn('NivelRiesgo');
        });
    }
};
