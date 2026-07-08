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
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            // Añadir columna PagaTercero solo si no existe
            if (! Schema::hasColumn('tbOperacionesPagos', 'PagaTercero')) {
                // Es probable que no exista la columna 'cancelaPoliza', valida según estructura real
                $table->boolean('PagaTercero')->default(0)->after('IDFormaPago');
            }
            // Añadir columna AvisoDeCobro solo si no existe
            if (! Schema::hasColumn('tbOperacionesPagos', 'AvisoDeCobro')) {
                $table->string('AvisoDeCobro')->nullable()->after('PagaTercero');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            // Elimina la columna AvisoDeCobro si existe
            if (Schema::hasColumn('tbOperacionesPagos', 'AvisoDeCobro')) {
                $table->dropColumn('AvisoDeCobro');
            }
            // Elimina la columna PagaTercero si existe
            if (Schema::hasColumn('tbOperacionesPagos', 'PagaTercero')) {
                $table->dropColumn('PagaTercero');
            }
        });
    }
};
