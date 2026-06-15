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
            $table->boolean('EsEndosoCancelacion')->default(false)->after('EsEndoso');
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
            $table->dropColumn('EsEndosoCancelacion');
        });
    }
};
