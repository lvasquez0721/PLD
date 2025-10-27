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
        Schema::create('tbAlertas', function (Blueprint $table) {
            $table->id();
            $table->integer('IDAlertas');
            $table->string('Folio')->nullable();
            $table->string('Patron')->nullable();
            $table->string('NCliente')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('NoOperacion')->nullable();
            $table->string('NoPoliza')->nullable();
            $table->timestamp('FechaDeteccion')->nullable();
            $table->time('Hora')->nullable();
            $table->timestamp('FechaOperacion')->nullable();
            $table->time('HoraOperacion')->nullable();
            $table->string('NoMovimiento')->nullable();
            $table->decimal('Monto', 18, 6)->nullable();
            $table->string('InstrumentoMonetario')->nullable();
            $table->string('Agente')->nullable();
            $table->string('Estatus')->nullable();
            $table->string('Descripcion')->nullable();
            $table->string('Razones')->nullable();
            $table->string('Evidencias')->nullable();
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
