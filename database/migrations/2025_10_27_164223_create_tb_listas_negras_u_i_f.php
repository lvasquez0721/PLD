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
        Schema::create('tbListasNegrasUIF', function (Blueprint $table) {
            $table->id();
            $table->string('Buscador')->nullable();
            $table->string('RFCCURP')->nullable();
            $table->date('FechaNac')->nullable();
            $table->date('FechaPubAcuerdo')->nullable();
            $table->string('Acuerdo')->nullable();
            $table->string('NoOficioUIF')->nullable();
            $table->integer('AnioLista')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbListasNegrasUIF');
    }
};
