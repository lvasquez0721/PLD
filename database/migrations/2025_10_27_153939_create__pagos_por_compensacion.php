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
        Schema::create('pagos_por_compensacion', function (Blueprint $table) {
            $table->id();
            $table->string('Poliza');
            $table->string('Endoso')->nullable();
            $table->string('RPP')->nullable();
            $table->date('Fecha_Pago')->nullable();
            $table->date('Fecha_registro')->nullable();
            $table->date('Fecha_apcontable')->nullable();
            $table->decimal('Importe_pago', 15, 2)->nullable();
            $table->unsignedBigInteger('IDMetodoPago')->nullable();
            $table->boolean('FacturaSi_NO')->default(false);
            $table->string('Ncliente')->nullable();
            $table->string('Tipo_Movimiento')->nullable();
            $table->string('Concepto')->nullable();
            $table->string('FolioControl')->nullable();
            $table->string('FolioControlInterno')->nullable();
            $table->string('NumeroRecibo')->nullable();
            $table->string('MesCierre')->nullable();
            $table->year('AnioCierre')->nullable();
            $table->boolean('CierreDiario')->default(false);
            $table->string('FacturaComplemento')->nullable();
            $table->string('FolioControlCFDI')->nullable();
            $table->string('FolioControlComp')->nullable();
            $table->boolean('FacturacionAgrupada')->default(false);
            $table->timestamp('TimeStamp')->nullable();
            $table->boolean('Facturable')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos_por_compensacion');
    }
};
