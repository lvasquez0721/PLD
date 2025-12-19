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
        Schema::create('catListasNotificaQeQ', function (Blueprint $table) {
            // El PK es autoincremental, pero también permite definir el ID manualmente si fuera necesario
            $table->integer('IDLista')->primary();
            $table->string('Lista');
            $table->string('Tipo');
            $table->integer('Activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catListasNotificaQeQ');
    }
};
