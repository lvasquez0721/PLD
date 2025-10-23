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
        Schema::create('tbIncisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPoliza');
            $table->unsignedBigInteger('IDSolicitud');
            $table->string('NoPoliza');
            $table->string('NoRPP');
            $table->string('FEmisionPoliza');
            $table->string('StatusPoliza');
            $table->string('SuperAsegurada');
            $table->string('SumaAsegurada');
            $table->string('SumaASeguradaTotal');
            $table->string('PrimaTotal');
            $table->string('FIV');
            $table->string('FFV');
            $table->string('FLPPoliza');
            $table->string('PrimaAsegurado');
            $table->string('PrimaGob');
            $table->string('PrimaApoyos');
            $table->string('Tarifa');
            $table->string('Rendimiento');
            $table->string('Precio');
            $table->string('GastoEmision');
            $table->string('SubSidioGobSiNo');
            $table->string('PorcentajeGob');
            $table->string('ApoyoCruzadaSiNo');
            $table->string('PorcentajeApoyos');
            $table->string('Beneficiarios');
            $table->unsignedBigInteger('IDFormaPago');
            $table->string('FormaPago');
            $table->string('MontoPagado');
            $table->string('ConceptoPago');
            $table->string('IndemnizacionTotal');
            $table->unsignedBigInteger('IDCiclo');
            $table->unsignedBigInteger('IDModalidad');
            $table->unsignedBigInteger('IDCultivo');
            $table->unsignedBigInteger('IDEstado');
            $table->unsignedBigInteger('IDRamo');
            $table->unsignedBigInteger('IDSubramo');
            $table->unsignedBigInteger('IDProducto');
            $table->unsignedBigInteger('IDMetodoEva');
            $table->unsignedBigInteger('IDUnidadRiesgo');
            $table->unsignedBigInteger('IDMoneda');
            $table->unsignedBigInteger('IDOficina');
            $table->unsignedBigInteger('IDAgente');
            $table->string('MontoPagado');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbIncisos');
    }
};
