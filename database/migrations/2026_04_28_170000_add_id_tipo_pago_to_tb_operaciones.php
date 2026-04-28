<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbOperaciones', 'IDTipoPago')) {
            Schema::table('tbOperaciones', function (Blueprint $table) {
                $table->unsignedBigInteger('IDTipoPago')->nullable()->after('IDFormaPago');
                $table->foreign('IDTipoPago')->references('IDTipoPago')->on('catTipoPagos')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->dropForeign(['IDTipoPago']);
            $table->dropColumn('IDTipoPago');
        });
    }
};
