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
        Schema::table('loglistanegracnsf', function (Blueprint $table) {
            $table->text('Acuerdo')->nullable()->after('FechaNacimiento');
            $table->text('Observaciones')->nullable()->comment('Valores extra en RFC Y CURP')->after('CURP');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loglistanegracnsf', function (Blueprint $table) {
            $table->dropColumn('Acuerdo');
            $table->dropColumn('Observaciones');
        });
    }
};
