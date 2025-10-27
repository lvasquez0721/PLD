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
        Schema::create('Cat_TipoCambio', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha')->nullable();
            $table->decimal('campko', 18, 6)->nullable();
            $table->decimal('DOF_dolar', 18, 6)->nullable();
            $table->decimal('DOF_udi', 18, 6)->nullable();
            $table->decimal('FIX_dolar', 18, 6)->nullable();
            $table->decimal('DOF_TIIE28', 18, 6)->nullable();
            $table->decimal('DOF_TIIE91', 18, 6)->nullable();
            $table->decimal('FIX_udi', 18, 6)->nullable();
            $table->decimal('FIX_TIIE28', 18, 6)->nullable();
            $table->decimal('FIX_CETES', 18, 6)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Cat_TipoCambio');
    }
};
