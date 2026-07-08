<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbOperacionesPagos', 'IDFormaPago')) {
            Schema::table('tbOperacionesPagos', function (Blueprint $table) {
                $table->unsignedBigInteger('IDFormaPago')->nullable()->after('IDMoneda');
                $table->foreign('IDFormaPago')->references('IDFormaPago')->on('catFormaPagos')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->dropForeign(['IDFormaPago']);
            $table->dropColumn('IDFormaPago');
        });
    }
};
