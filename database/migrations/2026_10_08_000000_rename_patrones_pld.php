<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombre de patrones de alertamiento (solo valores, misma funcionalidad):
     *  Relevante -> Monto
     *  Acumulado -> Acumulado Efectivo
     *  Inusual -> Monto Inusual
     *  Preocupante -> Nuevo
     */
    public function up(): void
    {
        if (Schema::hasTable('tbAlertas')) {
            DB::table('tbAlertas')->where('Patron', 'Relevante')->update(['Patron' => 'Monto']);
            DB::table('tbAlertas')->where('Patron', 'Acumulado')->update(['Patron' => 'Acumulado Efectivo']);
            DB::table('tbAlertas')->where('Patron', 'Inusual')->update(['Patron' => 'Monto Inusual']);
            DB::table('tbAlertas')->where('Patron', 'Preocupante')->update(['Patron' => 'Nuevo']);
        }

        if (Schema::hasTable('catTipoReporte')) {
            DB::table('catTipoReporte')->where('IDTipoReporte', 1)->update(['TipoReporte' => 'Monto']);
            DB::table('catTipoReporte')->where('IDTipoReporte', 2)->update(['TipoReporte' => 'Monto Inusual']);
            DB::table('catTipoReporte')->where('IDTipoReporte', 3)->update(['TipoReporte' => 'Nuevo']);
        }

        if (Schema::hasTable('tbReporteRegulatorioPLD')) {
            DB::table('tbReporteRegulatorioPLD')->where('TipoReporte', 'Relevante')->update(['TipoReporte' => 'Monto']);
            DB::table('tbReporteRegulatorioPLD')->where('TipoReporte', 'Inusual')->update(['TipoReporte' => 'Monto Inusual']);
            DB::table('tbReporteRegulatorioPLD')->where('TipoReporte', 'Preocupante')->update(['TipoReporte' => 'Nuevo']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbAlertas')) {
            DB::table('tbAlertas')->where('Patron', 'Monto Inusual')->update(['Patron' => 'Inusual']);
            DB::table('tbAlertas')->where('Patron', 'Acumulado Efectivo')->update(['Patron' => 'Acumulado']);
            // OJO: 'Monto' a secas revierte a 'Relevante'. Se ejecuta antes que Monto Inusual ya revertido arriba.
            DB::table('tbAlertas')->where('Patron', 'Monto')->update(['Patron' => 'Relevante']);
            DB::table('tbAlertas')->where('Patron', 'Nuevo')->update(['Patron' => 'Preocupante']);
        }

        if (Schema::hasTable('catTipoReporte')) {
            DB::table('catTipoReporte')->where('IDTipoReporte', 1)->update(['TipoReporte' => 'Relevante']);
            DB::table('catTipoReporte')->where('IDTipoReporte', 2)->update(['TipoReporte' => 'Inusual']);
            DB::table('catTipoReporte')->where('IDTipoReporte', 3)->update(['TipoReporte' => 'Preocupante']);
        }

        if (Schema::hasTable('tbReporteRegulatorioPLD')) {
            DB::table('tbReporteRegulatorioPLD')->where('TipoReporte', 'Monto Inusual')->update(['TipoReporte' => 'Inusual']);
            DB::table('tbReporteRegulatorioPLD')->where('TipoReporte', 'Monto')->update(['TipoReporte' => 'Relevante']);
            DB::table('tbReporteRegulatorioPLD')->where('TipoReporte', 'Nuevo')->update(['TipoReporte' => 'Preocupante']);
        }
    }
};
