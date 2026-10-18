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
        Schema::create('catLocalidad', function (Blueprint $table) {
            // Clave de localidad de 8 digitos: EE MM LLLL (entidad 2 + municipio 2 + localidad 4)
            $table->string('IDLocalidad', 8)->primary();
            $table->string('Localidad');
            $table->string('IDMunicipio', 4);
            $table->string('IDEstado', 2);
            $table->timestamps();

            $table->index(['IDEstado', 'IDMunicipio']);
            $table->index('Localidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catLocalidad');
    }
};
