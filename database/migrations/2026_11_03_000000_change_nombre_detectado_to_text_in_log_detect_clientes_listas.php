<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los nombres detectados en listas (principalmente UIF) pueden superar
     * los 255 caracteres. Con VARCHAR(255) el alta de personas falla con
     * SQLSTATE 22001 (1406 Data too long) al registrar la bitácora.
     */
    public function up(): void
    {
        Schema::table('logDetectClientesListas', function (Blueprint $table) {
            $table->text('NombreDetectado')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('logDetectClientesListas', function (Blueprint $table) {
            $table->string('NombreDetectado')->nullable()->change();
        });
    }
};
