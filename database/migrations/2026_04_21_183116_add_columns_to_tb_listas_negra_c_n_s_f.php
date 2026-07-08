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
        Schema::table('tbListasNegraCNSF', function (Blueprint $table) {
            // Agregar Observaciones (después de CURP)
            $table->text('Observaciones')
                ->nullable()
                ->default(null)
                ->comment('Valores extra en RFC Y CURP')
                ->after('CURP');

            // Agregar Acuerdo (después de FechaNacimiento)
            $table->text('Acuerdo')
                ->nullable()
                ->default(null)
                ->after('FechaNacimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbListasNegraCNSF', function (Blueprint $table) {
            $table->dropColumn('Observaciones');
            $table->dropColumn('Acuerdo');
        });
    }
};
