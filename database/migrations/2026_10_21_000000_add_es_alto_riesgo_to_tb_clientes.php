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
            $table->tinyInteger('EsAltoRiesgo')->default(0)->after('EsPPEActivo');
        });

        Schema::table('logClientes', function (Blueprint $table) {
            $table->tinyInteger('EsAltoRiesgo')->nullable()->after('EsPPEActivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            $table->dropColumn('EsAltoRiesgo');
        });

        Schema::table('logClientes', function (Blueprint $table) {
            $table->dropColumn('EsAltoRiesgo');
        });
    }
};
