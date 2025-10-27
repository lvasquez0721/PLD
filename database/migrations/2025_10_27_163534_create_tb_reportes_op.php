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
        Schema::create('tbReportesOp', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('IDReporteOP')->nullable();
            $table->timestamp('Fecha')->nullable();
            $table->string('Descripcion')->nullable();
            $table->string('Usuario')->nullable();
            $table->string('StatusReporte')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbReportesOp');
    }
};
