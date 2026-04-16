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
            // Añadir columna para la relación con CatTipoReporte si no existe
            if (!Schema::hasColumn('tbReporteRegulatorioPLD', 'IDTipoReporte')) {
                $table->unsignedBigInteger('IDTipoReporte')->nullable()->after('TipoReporte');
                $table->foreign('IDTipoReporte')
                    ->references('IDTipoReporte')
                    ->on('catTipoReporte')
                    ->onDelete('set null');
            }

            // Añadir columna para la relación con CatTipoOperacion si no existe
            if (!Schema::hasColumn('tbReporteRegulatorioPLD', 'IDTipoOperacion')) {
                $table->unsignedBigInteger('IDTipoOperacion')->nullable()->after('TipoOperacion');
                $table->foreign('IDTipoOperacion')
                    ->references('IDTipoOperacion')
                    ->on('catTipoOperacion')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbReporteRegulatorioPLD', function (Blueprint $table) {
            // Eliminar la clave foránea y columna para CatTipoOperacion si existen
            if (Schema::hasColumn('tbReporteRegulatorioPLD', 'IDTipoOperacion')) {
                $table->dropForeign(['IDTipoOperacion']);
                $table->dropColumn('IDTipoOperacion');
            }

            // Eliminar la clave foránea y columna para CatTipoReporte si existen
            if (Schema::hasColumn('tbReporteRegulatorioPLD', 'IDTipoReporte')) {
                $table->dropForeign(['IDTipoReporte']);
                $table->dropColumn('IDTipoReporte');
            }
        });
    }
};
