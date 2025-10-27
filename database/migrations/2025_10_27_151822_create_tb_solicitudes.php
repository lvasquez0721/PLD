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
        Schema::create('tbSolicitudes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDSolicitud');
            $table->unsignedBigInteger('IDSolicitante');
            $table->date('FCreacion')->nullable();
            $table->date('FActualizacion')->nullable();
            $table->date('FGenerada')->nullable();
            $table->string('StatusSolicitud', 50)->nullable();
            $table->string('NoSolicitud', 100)->nullable();
            $table->string('Usuario', 100)->nullable();
            $table->unsignedBigInteger('IDAgente')->nullable();
            $table->year('año')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_solicitudes');
    }
};
