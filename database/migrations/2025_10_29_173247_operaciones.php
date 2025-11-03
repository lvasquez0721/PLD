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
        // tbOperaciones
        Schema::create('tbOperaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('IDOperacion')->primary();
            $table->unsignedBigInteger('IDCliente')->nullable();
            $table->string('FolioPoliza')->nullable();
            $table->string('FolioEndoso')->nullable();
            $table->date('FechaEmision')->nullable();
            $table->decimal('PrimaTotal', 15, 2)->nullable();
            $table->decimal('GastosEmision', 15, 2)->nullable();
            $table->string('RFCAgente')->nullable();
            $table->string('CURPAgente')->nullable();
            $table->string('NombreAgente')->nullable();
            $table->string('APaternoAgente')->nullable();
            $table->string('AMaternoAgente')->nullable();

            // Nota: NO USAR $table->id() NI $table->bigIncrements()
            // para que el IDOperacion NO sea autoincrement.
            // El PK se define explícitamente arriba.

            $table->foreign('IDCliente')->references('IDCliente')->on('tbClientes')->onDelete('set null');
        });

        // tbOperacionesBeneficiarios
        Schema::create('tbOperacionesBeneficiarios', function (Blueprint $table) {
            $table->unsignedBigInteger('IDOperacionBeneficiario')->primary();
            $table->unsignedBigInteger('IDOperacion')->nullable();
            $table->string('RFCBeneficiario')->nullable();
            $table->string('CURPBeneficiario')->nullable();
            $table->string('NombreBeneficiario')->nullable();
            $table->string('APaternoBeneficiario')->nullable();
            $table->string('AMaternoBeneficiario')->nullable();
            $table->string('RazonSocialBeneficiario')->nullable();
            $table->boolean('Preferente')->default(false)->nullable();
            $table->decimal('PorcentajeParticipacion', 5, 2)->nullable();

            $table->timestamps();

            // IDOperacionBeneficiario tampoco es autoincrementable, definido manualmente arriba

            $table->foreign('IDOperacion')->references('IDOperacion')->on('tbOperaciones')->onDelete('set null');
        });

        // tbOperacionesAsegurado
        Schema::create('tbOperacionesAsegurado', function (Blueprint $table) {
            $table->unsignedBigInteger('IDAsegurado')->primary();
            $table->unsignedBigInteger('IDOperacion')->nullable();
            $table->string('RFCAsegurado')->nullable();
            $table->string('CURPAsegurado')->nullable();
            $table->string('NombreAsegurado')->nullable();
            $table->string('APaternoAsegurado')->nullable();
            $table->string('AMaternoAsegurado')->nullable();
            $table->string('RazonSocialAsegurado')->nullable();

            $table->timestamps();

            // IDAsegurado NO es autoincrementable

            $table->foreign('IDOperacion')->references('IDOperacion')->on('tbOperaciones')->onDelete('set null');
        });

        // catMonedas
        Schema::create('catMonedas', function (Blueprint $table) {
            $table->unsignedBigInteger('IDMoneda')->primary();
            $table->string('Moneda');
            $table->timestamps();
            // IDMoneda no es autoincrement, definido manualmente
        });

        // catFormaPagos
        Schema::create('catFormaPagos', function (Blueprint $table) {
            $table->unsignedBigInteger('IDFormaPago')->primary();
            $table->string('FormaPago');
            $table->timestamps();
            // IDFormaPago no es autoincrement, definido manualmente
        });

        // tbOperacionesPagos
        Schema::create('tbOperacionesPagos', function (Blueprint $table) {
            $table->unsignedBigInteger('IDOperacionPago')->primary();
            $table->unsignedBigInteger('IDOperacion')->nullable();
            $table->decimal('Monto', 18, 2)->nullable();
            $table->unsignedBigInteger('IDMoneda')->nullable();
            $table->unsignedBigInteger('IDFormaPago')->nullable();
            $table->decimal('TipoCambio', 18, 6)->nullable();
            $table->date('FechaPago')->nullable();
            $table->timestamp('TimeStampRegistro')->nullable();

            $table->timestamps();

            // IDOperacionPago no es autoincrement, definido manualmente

            $table->foreign('IDOperacion')->references('IDOperacion')->on('tbOperaciones')->onDelete('set null');
            $table->foreign('IDMoneda')->references('IDMoneda')->on('catMonedas')->onDelete('set null');
            $table->foreign('IDFormaPago')->references('IDFormaPago')->on('catFormaPagos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tbOperacionesPagos');
        Schema::dropIfExists('catFormaPagos');
        Schema::dropIfExists('catMonedas');
        Schema::dropIfExists('tbOperacionesAsegurado');
        Schema::dropIfExists('tbOperacionesBeneficiarios');
        Schema::dropIfExists('tbOperaciones');
    }
};
