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
        Schema::create('logcalculoinusualidadprimaemitida', function (Blueprint $table) {
            $table->id();
            $table->string('PolizaHistorico')->nullable();
            $table->string('EndosoHistorico')->nullable();
            $table->date('FechaEmision')->nullable();
            $table->decimal('PrimaTotalHistorica', 18, 2)->nullable();
            $table->integer('NCliente')->nullable();
            $table->string('Cliente')->nullable();
            $table->date('FechaInicioMuestra')->nullable();
            $table->date('FechaFinMuestra')->nullable();
            $table->string('PolizaEmitida')->nullable()->index();
            $table->string('EndosoEmitido')->nullable()->index();
            $table->decimal('PrimaEmitida', 18, 2)->nullable();
            $table->boolean('Detectado')->default(0);
            $table->integer('AniosAnterioresConsiderados')->nullable();
            $table->decimal('Promedio', 18, 2)->nullable();
            $table->decimal('DesviacionEstandar', 18, 2)->nullable();
            $table->decimal('FactorDesviacionEstandar', 18, 2)->nullable();
            $table->decimal('LimiteInferior', 18, 2)->nullable();
            $table->decimal('LimiteSuperior', 18, 2)->nullable();
            $table->timestamp('TimeStamp')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logcalculoinusualidadprimaemitida');
    }
};
