<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catcampos', function (Blueprint $table) {
            $table->integer('IDCampo')->primary(); // No autoincremental
            $table->integer('IDModulo')->nullable();
            $table->integer('Seccion')->nullable();
            $table->string('Tipo', 50)->nullable();
            $table->string('IDSubPerfil', 25)->nullable();
            $table->string('NombreCampo', 255)->nullable();
            $table->string('Longitud', 50)->nullable();
            $table->string('Placeholder', 255)->nullable();
            $table->string('Clase', 255)->nullable();
            $table->integer('Columnas')->nullable();
            $table->string('EtiquetaCampo', 255)->nullable();
            $table->string('idname', 255)->nullable();
            $table->integer('Orden')->nullable();
            $table->boolean('Requerido')->nullable();
            $table->boolean('Visible')->nullable();
            $table->string('Value', 90)->nullable();
            $table->text('JQuery')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catcampos');
    }
};
