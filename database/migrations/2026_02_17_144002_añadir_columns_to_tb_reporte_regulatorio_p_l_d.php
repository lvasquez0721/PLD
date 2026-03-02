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
        Schema::table('tbReporteRegulatorioPLD', function (Blueprint $table) {
            // Añadir columna para la relación con CatTipoReporte
            $table->unsignedBigInteger('IDTipoReporte')->nullable()->after('TipoReporte');

            // Agregar la clave foránea para CatTipoReporte
            $table->foreign('IDTipoReporte')
                ->references('IDTipoReporte')
                ->on('catTipoReporte')
                ->onDelete('set null');

            // Añadir columna para la relación con CatTipoOperacion
            $table->unsignedBigInteger('IDTipoOperacion')->nullable()->after('TipoOperacion');

            // Agregar la clave foránea para CatTipoOperacion
            $table->foreign('IDTipoOperacion')
                ->references('IDTipoOperacion')
                ->on('catTipoOperacion')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbReporteRegulatorioPLD', function (Blueprint $table) {
            // Eliminar la clave foránea y columna para CatTipoOperacion
            $table->dropForeign(['IDTipoOperacion']);
            $table->dropColumn('IDTipoOperacion');

            // Eliminar la clave foránea y columna para CatTipoReporte
            $table->dropForeign(['IDTipoReporte']);
            $table->dropColumn('IDTipoReporte');
        });
    }
};
