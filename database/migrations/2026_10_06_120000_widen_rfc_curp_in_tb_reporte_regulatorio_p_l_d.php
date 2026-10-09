<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tbReporteRegulatorioPLD copia datos de tbClientes y tbOperaciones,
     * cuyas columnas son más anchas (tbClientes.RFC varchar(20),
     * tbClientes.CURP varchar(255), tbOperaciones.CURPAgente varchar(255)).
     * Sin esto, reportar una alerta con RFC/CURP largos falla con
     * SQLSTATE 22001 (1406 Data too long).
     */
    public function up(): void
    {
        Schema::table('tbReporteRegulatorioPLD', function (Blueprint $table) {
            $table->string('RFC', 20)->nullable()->change();
            $table->string('CURP', 255)->nullable()->change();
            $table->string('CURPAgente', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbReporteRegulatorioPLD', function (Blueprint $table) {
            $table->string('RFC', 13)->nullable()->change();
            $table->string('CURP', 18)->nullable()->change();
            $table->string('CURPAgente', 18)->nullable()->change();
        });
    }
};
