<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logApi', function (Blueprint $table) {
            $table->index('created_at', 'logapi_created_at_idx');
            $table->index('Metodo', 'logapi_metodo_idx');
            $table->index('Estatus', 'logapi_estatus_idx');
            $table->index('Ruta', 'logapi_ruta_idx');
            $table->index(['created_at', 'Metodo', 'Estatus'], 'logapi_fecha_metodo_estatus_idx');
        });
    }

    public function down(): void
    {
        Schema::table('logApi', function (Blueprint $table) {
            $table->dropIndex('logapi_fecha_metodo_estatus_idx');
            $table->dropIndex('logapi_ruta_idx');
            $table->dropIndex('logapi_estatus_idx');
            $table->dropIndex('logapi_metodo_idx');
            $table->dropIndex('logapi_created_at_idx');
        });
    }
};
