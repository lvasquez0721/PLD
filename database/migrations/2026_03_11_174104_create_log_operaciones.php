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
        Schema::create('logOperaciones', function (Blueprint $table) {
            // Puede o no puede ser autoincremental, así que lo dejamos configurable manualmente:
            $table->unsignedBigInteger('IDOperacion');

            $table->unsignedBigInteger('IDCliente')->nullable();
            $table->string('IDMoneda')->nullable();

            $table->string('FechaInicioVigencia')->nullable();
            $table->string('FechaFinVigencia')->nullable();

            $table->string('RazonSocialAgente', 255)->nullable();
            $table->string('FolioPoliza', 50)->nullable();
            $table->string('FolioEndoso', 50)->nullable();

            $table->string('FechaEmision')->nullable();

            $table->decimal('PrimaTotal', 15, 2)->nullable();
            $table->decimal('GastosEmision', 15, 2)->nullable();

            $table->string('RFCAgente', 14)->nullable();
            $table->string('CURPAgente', 18)->nullable();
            $table->string('NombreAgente', 100)->nullable();
            $table->string('APaternoAgente', 100)->nullable();
            $table->string('AMaternoAgente', 100)->nullable();

            $table->string('tipoDocumento', 50)->nullable();
            $table->boolean('cancelaPoliza')->default(false);

            // Timestamps del registro de log
            $table->timestamps();
        });

        Schema::create('logOperacionesPagos', function (Blueprint $table) {
            $table->bigIncrements('IDOperacionPago'); // PK autoincremental

            $table->unsignedBigInteger('IDOperacion')->nullable();
            $table->unsignedBigInteger('IDCliente')->nullable();

            $table->decimal('Monto', 15, 2)->nullable();
            $table->string('IDMoneda')->nullable();
            $table->string('IDFormaPago')->nullable();

            $table->string('TipoCambio')->nullable();

            $table->string('FechaPago')->nullable();
            $table->string('TimeStampRegistro')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logOperaciones');
        Schema::dropIfExists('logOperacionesPagos');
    }
};
