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
        Schema::table('tbAlertas', function (Blueprint $table) {
            $table->dropColumn('IDOperacionPago');
            $table->dropColumn('IDPago');

            $table->unsignedBigInteger('IDOperacion')->nullable()->after('IDCliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbAlertas', function (Blueprint $table) {
            // Revert the changes made in the up() method
            $table->unsignedBigInteger('IDOperacionPago')->nullable()->after('FechaDeteccion');
            $table->unsignedBigInteger('IDPago')->nullable()->after('IDReporteOP');
            $table->dropColumn('IDOperacion');
        });
    }
};
