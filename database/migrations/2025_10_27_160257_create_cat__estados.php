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
        Schema::create('cat_Estados', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IdEstado');
            $table->string('Estado')->nullable();
            $table->string('CveEntidad')->nullable();
            $table->string('ApoyoSAGARPA16')->nullable();
            $table->string('ApoyoSAGARPA17')->nullable();
            $table->string('NivelRiesgo')->nullable();
            $table->timestamp('TimeStampModif')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cat_Estados');
    }
};
