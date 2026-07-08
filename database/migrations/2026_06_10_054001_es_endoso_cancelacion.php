<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega el campo 'EsEndosoCancelacion' a la tabla 'tbOperaciones'
     */
    public function up(): void
    {
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->boolean('EsEndosoCancelacion')->default(false); // No after('EsEndoso') para evitar error si no existe
        });
    }

    /**
     * Reverse the migrations.
     *
     * Elimina el campo 'EsEndosoCancelacion' de la tabla 'tbOperaciones'
     */
    public function down(): void
    {
        Schema::table('tbOperaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tbOperaciones', 'EsEndosoCancelacion')) {
                $table->dropColumn('EsEndosoCancelacion');
            }
        });
    }
};