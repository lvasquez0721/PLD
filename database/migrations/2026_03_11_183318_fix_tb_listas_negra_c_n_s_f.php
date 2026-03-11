<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Hace que el campo IDRegistroListaCNSF sea AUTOINCREMENTAL,
     * sin alterar los registros actuales.
     */
    public function up(): void
    {
        // Para MySQL y campo INT:
        // 1. Asegurarse que el campo sea PRIMARY KEY (ya debe serlo).
        // 2. Ajustar a AUTO_INCREMENT solo si no lo es aún.

        // Revisar si ya es autoincrement es difícil desde aquí, así que intentamos modificar:
        // NOTA: Si el campo ya es auto_increment, esto no causará error.

        // IMPORTANTE: Esto funcionará solo en MySQL!
        DB::statement('ALTER TABLE `tbListasNegraCNSF` MODIFY `IDRegistroListaCNSF` INT UNSIGNED NOT NULL AUTO_INCREMENT;');
    }

    /**
     * Reverse the migrations.
     * Quita el autoincremental (si se requiere, se elimina el atributo).
     */
    public function down(): void
    {
        // Quita el AUTO_INCREMENT, deja la columna como simple INT unsigned NOT NULL
        DB::statement('ALTER TABLE `tbListasNegraCNSF` MODIFY `IDRegistroListaCNSF` INT UNSIGNED NOT NULL;');
    }
};
