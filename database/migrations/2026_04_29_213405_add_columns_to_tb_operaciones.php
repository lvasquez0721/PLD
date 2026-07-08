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
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->boolean('PagaTercero')->default(0)->after('cancelaPoliza');
            $table->string('EsquemaDePago')->nullable()->after('PagaTercero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbOperaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tb_operaciones', 'EsquemaDePago')) {
                $table->dropColumn('EsquemaDePago');
            }
            if (Schema::hasColumn('tb_operaciones', 'PagaTercero')) {
                $table->dropColumn('PagaTercero');
            }
        });
    }
};
