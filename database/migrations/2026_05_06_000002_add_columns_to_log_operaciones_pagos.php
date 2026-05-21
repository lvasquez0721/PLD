<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logOperacionesPagos', function (Blueprint $table) {
            if (! Schema::hasColumn('logOperacionesPagos', 'PagaTercero')) {
                $table->boolean('PagaTercero')->default(0)->after('TimeStampRegistro');
            }
            if (! Schema::hasColumn('logOperacionesPagos', 'AvisoDeCobro')) {
                $table->string('AvisoDeCobro')->nullable()->after('PagaTercero');
            }
            if (! Schema::hasColumn('logOperacionesPagos', 'folioPoliza')) {
                $table->string('folioPoliza', 50)->nullable()->after('AvisoDeCobro');
            }
            if (! Schema::hasColumn('logOperacionesPagos', 'folioEndoso')) {
                $table->string('folioEndoso', 50)->nullable()->after('folioPoliza');
            }
        });
    }

    public function down(): void
    {
        Schema::table('logOperacionesPagos', function (Blueprint $table) {
            if (Schema::hasColumn('logOperacionesPagos', 'folioPoliza')) {
                $table->dropColumn('folioPoliza');
            }
            if (Schema::hasColumn('logOperacionesPagos', 'folioEndoso')) {
                $table->dropColumn('folioEndoso');
            }
            if (Schema::hasColumn('logOperacionesPagos', 'AvisoDeCobro')) {
                $table->dropColumn('AvisoDeCobro');
            }
            if (Schema::hasColumn('logOperacionesPagos', 'PagaTercero')) {
                $table->dropColumn('PagaTercero');
            }
        });
    }
};
