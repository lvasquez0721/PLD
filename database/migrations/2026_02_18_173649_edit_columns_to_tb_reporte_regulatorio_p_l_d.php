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
        Schema::table('tbReporteRegulatorioPLD', function (Blueprint $table) {
            // Modificar la columna IDMoneda para que sea string (antes era unsignedBigInteger)
            $table->string('IDMoneda', 10)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbReporteRegulatorioPLD', function (Blueprint $table) {
            // Revertir IDMoneda a unsignedBigInteger
            $table->unsignedBigInteger('IDMoneda')->nullable()->change();
        });
    }
};
