<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            if (! Schema::hasColumn('tbOperacionesPagos', 'folioPoliza')) {
                $table->string('folioPoliza', 50)->nullable()->after('TimeStampRegistro');
            }
            if (! Schema::hasColumn('tbOperacionesPagos', 'folioEndoso')) {
                $table->string('folioEndoso', 50)->nullable()->after('folioPoliza');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            if (Schema::hasColumn('tbOperacionesPagos', 'folioPoliza')) {
                $table->dropColumn('folioPoliza');
            }
            if (Schema::hasColumn('tbOperacionesPagos', 'folioEndoso')) {
                $table->dropColumn('folioEndoso');
            }
        });
    }
};
