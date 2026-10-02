<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logOperaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('IDFormaPago')
                ->nullable()
                ->after('IDMoneda');
        });
    }

    public function down(): void
    {
        Schema::table('logOperaciones', function (Blueprint $table) {
            $table->dropColumn('IDFormaPago');
        });
    }
};
