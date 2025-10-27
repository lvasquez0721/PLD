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
        Schema::create('catParametriaPLD', function (Blueprint $table) {
            $table->id();
            $table->string('Parametro')->nullable();
            $table->string('Valor')->nullable();
            $table->string('Tipo')->nullable();
            $table->boolean('Activo')->default(true);
            $table->timestamp('TimeStampAlta')->nullable();
            $table->timestamp('TimeStampModificacion')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catParametriaPLD');
    }
};
