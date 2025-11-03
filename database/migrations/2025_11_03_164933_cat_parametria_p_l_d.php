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
        Schema::create('catParametriaPLD', function (Blueprint $table) {
            $table->bigIncrements('IDParametro');
            $table->string('Parametro')->comment('Nombre o descripción del parámetro');
            $table->string('Valor')->nullable()->comment('Valor asignado al parámetro');
            $table->string('TipoDato')->nullable()->comment('Tipo de dato del parámetro');
            $table->boolean('Activo')->default(true)->comment('Indica si el parámetro está activo');
            $table->timestamp('TimeStampAlta')->nullable()->comment('Fecha y hora de alta del parámetro');
            $table->timestamp('TimeStampModificacion')->nullable()->comment('Fecha y hora de modificación del parámetro');

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
