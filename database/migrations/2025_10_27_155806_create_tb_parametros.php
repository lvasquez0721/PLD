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
        Schema::create('tbParametros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDParametro');
            $table->string('PorNacimiento')->nullable();
            $table->string('PorResidencia')->nullable();
            $table->string('PorPredio')->nullable();
            $table->string('PorNacionalidad')->nullable();
            $table->string('PorAmbitoLaboral')->nullable();
            $table->string('PorUbicacion')->nullable();
            $table->string('PorOrigenRecursos')->nullable();
            $table->string('PorIngresosEstimados')->nullable();
            $table->string('PorPromedioUR')->nullable();
            $table->string('PorDatosEconomicos')->nullable();
            $table->string('PorDatosLaborales')->nullable();
            $table->timestamp('FechaActualizacion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_parametros');
    }
};
