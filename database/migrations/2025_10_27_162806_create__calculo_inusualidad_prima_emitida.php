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
        Schema::create('CalculoInusualidadPrimaEmitida', function (Blueprint $table) {
            $table->id();
            $table->string('PolizaHistorico')->nullable();
            $table->string('EndosoHistorico')->nullable();
            $table->timestamp('FechaEmision')->nullable();
            $table->decimal('PrimaTotalHistorica', 18, 6)->nullable();
            $table->string('NCliente')->nullable();
            $table->string('Cliente')->nullable();
            $table->timestamp('FechaInicioMuestra')->nullable();
            $table->timestamp('FechaFinMuestra')->nullable();
            $table->string('PolizaEmitida')->nullable();
            $table->string('EndosoEmitido')->nullable();
            $table->decimal('PrimaEmitida', 18, 6)->nullable();
            $table->boolean('Detectado')->nullable();
            $table->integer('AniosAnterioresConsiderados')->nullable();
            $table->decimal('Promedio', 18, 6)->nullable();
            $table->decimal('DesviacionEstandar', 18, 6)->nullable();
            $table->decimal('FactorDesviacionEstandar', 18, 6)->nullable();
            $table->decimal('LimiteInferior', 18, 6)->nullable();
            $table->decimal('LimiteSuperior', 18, 6)->nullable();
            $table->timestamp('TimeStamp')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('CalculoInusualidadPrimaEmitida');
    }
};
