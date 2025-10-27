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
        Schema::create('tbTotal_Pagos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('IDPoliza');
            $table->string('Endoso')->nullable();
            $table->string('RPP')->nullable();
            $table->date('Fecha_emision')->nullable();
            $table->date('Fecha_ult_pago_cte')->nullable();
            $table->date('Fecha_ult_pago_shcp')->nullable();
            $table->date('Fecha_ult_pago_sagarpa')->nullable();
            $table->decimal('Prima_asegurado', 15, 2)->nullable();
            $table->decimal('Prima_gobierno', 15, 2)->nullable();
            $table->decimal('Prima_otros', 15, 2)->nullable();
            $table->decimal('Total_pagos_cte', 15, 2)->nullable();
            $table->decimal('Total_pagos_shcp', 15, 2)->nullable();
            $table->decimal('Total_pagos_sagarpa', 15, 2)->nullable();
            $table->decimal('Gastos_Emision', 15, 2)->nullable();
            $table->string('Ncliente')->nullable();
            $table->date('visibleFechaPago')->nullable();
            $table->unsignedBigInteger('IDMetodoPagoFront')->nullable();
            $table->string('FacInterna')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_total__pagos');
    }
};
