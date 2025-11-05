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
        Schema::create('tbBuzonPreocupantes', function (Blueprint $table) {
            $table->bigIncrements('idBuzonPreocupantes');
            $table->unsignedBigInteger('IDReporteOP')->comment('Identificador del Reporte OP');
            $table->date('Fecha')->nullable()->comment('Fecha del buzón preocupante');
            $table->text('Descripcion')->nullable()->comment('Descripción del buzón preocupante');
            $table->string('Usuario')->nullable()->comment('Usuario relacionado con el buzón preocupante');
            $table->string('Estatus')->default('Pendiente')->comment('Estatus del buzón preocupante');

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
