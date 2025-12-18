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
        Schema::table('catMonedas', function (Blueprint $table) {
            $table->date('Fecha')->nullable()->after('Moneda');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catMonedas', function (Blueprint $table) {
            $table->dropColumn(['Fecha']);
        });
    }
};
