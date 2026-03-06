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
            $table->string('IDMoneda', 10)->nullable()->after('IDReporteOP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbAlertas', function (Blueprint $table) {
            $table->dropColumn('IDMoneda');
        });
    }
};
