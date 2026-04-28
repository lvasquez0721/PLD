<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tbOperaciones', 'IDFormaPago')) {
            Schema::table('tbOperaciones', function (Blueprint $table) {
                $table->unsignedBigInteger('IDFormaPago')->nullable()->after('IDMoneda');
                $table->foreign('IDFormaPago')->references('IDFormaPago')->on('catFormaPagos')->onDelete('set null');
            });
        }

        $fk = \DB::selectOne("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbOperacionesPagos'
              AND COLUMN_NAME = 'IDFormaPago'
              AND CONSTRAINT_NAME != 'PRIMARY'
        ");
        if ($fk) {
            \DB::statement("ALTER TABLE `tbOperacionesPagos` DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
        }
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->dropColumn('IDFormaPago');
        });
    }

    public function down(): void
    {
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->unsignedBigInteger('IDFormaPago')->nullable();
            $table->foreign('IDFormaPago')->references('IDFormaPago')->on('catFormaPagos')->onDelete('set null');
        });

        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->dropForeign(['IDFormaPago']);
            $table->dropColumn('IDFormaPago');
        });
    }
};
