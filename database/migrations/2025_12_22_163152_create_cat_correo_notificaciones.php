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
        Schema::create('catCorreoNotificaciones', function (Blueprint $table) {
            $table->bigIncrements('IDCorreo');
            $table->string('Archivo');
            $table->string('Correo');
            $table->string('Nombre');
            $table->integer('Activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catCorreoNotificaciones');
    }
};
