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
        Schema::create('PerfilTransaccional', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPerfil');
            $table->string('NCliente')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('EdoNacimiento')->nullable();
            $table->string('NivelRiesgoNac')->nullable();
            $table->string('CalculoNacimiento')->nullable();
            $table->string('EdoDomicilio')->nullable();
            $table->string('NivelRiesgoDoc')->nullable();
            $table->string('CalculoResidencia')->nullable();
            $table->string('EdoLabora')->nullable();
            $table->string('NivelRiesgoResidencia')->nullable();
            $table->string('CalculoLaboral')->nullable();
            $table->string('TotalUbicacion')->nullable();
            $table->string('Origen')->nullable();
            $table->string('ORecursos')->nullable();
            $table->string('Ingresos')->nullable();
            $table->string('PromedioHA')->nullable();
            $table->string('TotalEconomico')->nullable();
            $table->string('OcupGiro')->nullable();
            $table->string('NivelRiesgo')->nullable();
            $table->string('CalculoOcupacion')->nullable();
            $table->string('Perfil')->nullable();
            $table->string('Periodo')->nullable();
            $table->unsignedBigInteger('IDTipoEjecuccion')->nullable();
            $table->decimal('AVGPrimaTotal', 15, 2)->nullable();
            $table->decimal('AVGHaTotal', 15, 2)->nullable();
            $table->decimal('STDEVPrimaTotal', 15, 2)->nullable();
            $table->decimal('STDEVHaTotal', 15, 2)->nullable();
            $table->string('origenRecursos')->nullable();
            $table->decimal('ValorIngresoEstimado', 15, 2)->nullable();
            $table->decimal('ValorHaEstimado', 15, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('PerfilTransaccional');
    }
};
