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
        Schema::create('tbListaNegraCNSF', function (Blueprint $table) {
            $table->id();
            $table->string('Nombres')->nullable();
            $table->string('Direccion')->nullable();
            $table->string('Empresa')->nullable();
            $table->string('Cedula')->nullable();
            $table->string('Pasaporte')->nullable();
            $table->string('NIT')->nullable();
            $table->string('IFE')->nullable();
            $table->string('RFC')->nullable();
            $table->string('CURP')->nullable();
            $table->string('Pais')->nullable();
            $table->date('FechaNacimiento')->nullable();
            $table->string('Usuario')->nullable();
            $table->timestamp('TimeStampAlta')->nullable();
            $table->timestamp('TimeStampModif')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbListaNegraCNSF');
    }
};
