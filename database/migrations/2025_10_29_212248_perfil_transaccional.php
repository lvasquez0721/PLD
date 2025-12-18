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
        //
        // tbPerfilTransaccional
        Schema::create('tbPerfilTransaccional', function (Blueprint $table) {
            $table->bigIncrements('IDRegistroPerfil'); // PK, autoincremental
            $table->unsignedBigInteger('IDCliente')->nullable();
            $table->string('IDEstadoNacimiento')->nullable();
            $table->unsignedTinyInteger('NivelRiesgoNac')->nullable();
            $table->string('CalculoNacimiento')->nullable();
            $table->unsignedBigInteger('IDEstadoDomicilio')->nullable();
            $table->unsignedTinyInteger('NivelRiesgoDoc')->nullable();
            $table->string('CalculoResidencia')->nullable();
            $table->unsignedBigInteger('IDEstadoLabora')->nullable();
            $table->unsignedTinyInteger('NivelRiesgoResidencia')->nullable();
            $table->string('CalculoLaboral')->nullable();
            $table->string('TotalUbicacion')->nullable();
            $table->string('Origen')->nullable();
            $table->string('ORecursos')->nullable();
            $table->string('IngresosMensuales')->nullable();
            $table->string('PromedioHA')->nullable();
            $table->string('TotalEconomico')->nullable();
            $table->string('OcupGiro')->nullable();
            $table->unsignedTinyInteger('NivelRiesgo')->nullable();
            $table->string('CalculoOcupacion')->nullable();
            $table->string('Perfil')->nullable();
            $table->date('FechaEjecucción')->nullable();
            $table->unsignedBigInteger('IDTipoEjecuccion')->nullable();
            $table->decimal('AVGPrimaTotal', 18, 2)->nullable();
            $table->decimal('AVGHaTotal', 18, 2)->nullable();
            $table->decimal('STDEVPrimaTotal', 18, 2)->nullable();
            $table->decimal('STDEVHaTotal', 18, 2)->nullable();
            $table->string('origenRecursos')->nullable();
            $table->decimal('ValorIngresoEstimado', 18, 2)->nullable();
            $table->decimal('ValorHaEstimado', 18, 2)->nullable();
            $table->timestamp('TimeStamp')->nullable();

            $table->timestamps();
        });

        // catParametrosPerfilTrans
        Schema::create('catParametrosPerfilTrans', function (Blueprint $table) {
            $table->bigIncrements('IDRegistroParametro'); // PK, autoincremental
            $table->decimal('PorcentajeNacimiento', 5, 2)->nullable();
            $table->decimal('PorcentajeResidencia', 5, 2)->nullable();
            $table->decimal('PorcentajePredio', 5, 2)->nullable();
            $table->decimal('PorcentajeNacionalidad', 5, 2)->nullable();
            $table->decimal('PorcentajeAmbitoLaboral', 5, 2)->nullable();
            $table->decimal('PorcentajeUbicacion', 5, 2)->nullable();
            $table->decimal('PorcentajeOrigenRecursos', 5, 2)->nullable();
            $table->decimal('PorcentajeIngresosEstimados', 5, 2)->nullable();
            $table->decimal('PorcentajePromedioUR', 5, 2)->nullable();
            $table->decimal('PorcentajeDatosEconomicos', 5, 2)->nullable();
            $table->decimal('PorcentajeDatosLaborales', 5, 2)->nullable();
            $table->timestamp('TimeStampAlta')->nullable();
            $table->boolean('Activo')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
