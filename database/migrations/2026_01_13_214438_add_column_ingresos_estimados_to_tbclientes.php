<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tbclientes', function (Blueprint $table) {
            $table
                ->decimal('IngresosEstimados', 15, 2)
                ->nullable()
                ->after('Preguntas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbclientes', function (Blueprint $table) {
            $table->dropColumn(['IngresosEstimados']);
        });
    }
};