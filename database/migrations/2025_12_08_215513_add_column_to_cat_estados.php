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
        Schema::table('catEstados', function (Blueprint $table) {
            // Agregar columna 'CveEntidad'
            $table->string('CveEntidad')->nullable()->after('IDEstado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catEstados', function (Blueprint $table) {
            // Eliminar columna 'CveEntidad'E
            $table->dropColumn('CveEntidad');
        });
    }
};
