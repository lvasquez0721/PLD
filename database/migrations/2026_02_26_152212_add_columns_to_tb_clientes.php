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
        Schema::table('tbClientes', function (Blueprint $table) {
            // Agregar columna ckAgente después de IDCliente con valor default 0
            $table->integer('ckAgente')->after('IDCliente')->default(0);
            // Puedes agregar aquí más columnas si se requieren en el futuro.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            // Eliminar columna ckAgente al revertir la migración
            $table->dropColumn('ckAgente');
        });
    }
};
