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
        Schema::table('tbClientes', function (Blueprint $table) {
            // Cambia CoincideEnListasNegras de tinyint a integer
            $table->integer('CoincideEnListasNegras')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            // Revierte CoincideEnListasNegras a tinyint
            $table->tinyInteger('CoincideEnListasNegras')->change();
        });
    }
};
