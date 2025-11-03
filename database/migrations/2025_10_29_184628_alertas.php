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
        // tbAlertas
        Schema::create('tbAlertas', function (Blueprint $table) {
            $table->unsignedBigInteger('IDRegistroAlerta')->primary(); // No autoincrement, PK se define manualmente
            $table->string('Folio')->nullable();
            $table->string('Patron')->nullable();
            $table->unsignedBigInteger('IDCliente')->nullable();
            $table->string('Cliente')->nullable();
            $table->string('Poliza')->nullable();
            $table->date('FechaDeteccion')->nullable();
            $table->unsignedBigInteger('IDOperacionPago')->nullable();
            $table->time('HoraDeteccion')->nullable();
            $table->date('FechaOperacion')->nullable();
            $table->time('HoraOperacion')->nullable();
            $table->decimal('MontoOperacion', 18, 2)->nullable();
            $table->string('InstrumentoMonetario')->nullable();
            $table->string('RFCAgente')->nullable();
            $table->string('Agente')->nullable();
            $table->string('Estatus')->nullable();
            $table->string('Descripcion')->nullable();
            $table->text('Razones')->nullable();
            $table->text('Evidencias')->nullable();
            $table->unsignedBigInteger('IDReporteOP')->nullable();
            $table->unsignedBigInteger('IDPago')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbAlertas');
    }
};
