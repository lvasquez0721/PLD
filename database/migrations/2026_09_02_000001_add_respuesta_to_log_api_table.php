<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logApi', function (Blueprint $table) {
            $table->longText('Respuesta')->nullable()->after('Payload');
        });
    }

    public function down(): void
    {
        Schema::table('logApi', function (Blueprint $table) {
            $table->dropColumn('Respuesta');
        });
    }
};
