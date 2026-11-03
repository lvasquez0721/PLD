<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('ebr_alertas', 'ebr_alertas_unica')) {
            Schema::table('ebr_alertas', function (Blueprint $table) {
                $table->dropUnique('ebr_alertas_unica');
            });
        }

        $columnas = array_values(array_filter(
            ['folio', 'resultado'],
            fn (string $columna) => Schema::hasColumn('ebr_alertas', $columna)
        ));

        if (! empty($columnas)) {
            Schema::table('ebr_alertas', function (Blueprint $table) use ($columnas) {
                $table->dropColumn($columnas);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ebr_alertas', 'folio')) {
            Schema::table('ebr_alertas', function (Blueprint $table) {
                $table->string('folio', 80)->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('ebr_alertas', 'resultado')) {
            Schema::table('ebr_alertas', function (Blueprint $table) {
                $table->string('resultado', 80)->nullable()->after('procedio');
            });
        }
    }
};
