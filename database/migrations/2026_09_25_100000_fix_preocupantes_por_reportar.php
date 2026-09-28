<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrección retroactiva: alertas Preocupante que quedaron en Generado
     * deben pasar a Por reportar para ser reportables en /reporte-operaciones.
     * Requisito: "AL EMITIRSE REPORTES Preocupantes, SE DEBEN EMITIR CON PATRON DE Por reportar"
     */
    public function up(): void
    {
        DB::table('tbAlertas')
            ->where('Patron', 'Preocupante')
            ->where('Estatus', 'Generado')
            ->update(['Estatus' => 'Por reportar']);
    }

    public function down(): void
    {
        DB::table('tbAlertas')
            ->where('Patron', 'Preocupante')
            ->where('Estatus', 'Por reportar')
            ->update(['Estatus' => 'Generado']);
    }
};
