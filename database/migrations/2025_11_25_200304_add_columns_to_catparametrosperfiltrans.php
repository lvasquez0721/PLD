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
        // Corrige el nombre de la tabla: debe ser 'catParametrosPerfilTrans' (respeta mayúsculas y minúsculas)
        Schema::table('catParametrosPerfilTrans', function (Blueprint $table) {
            // Agrega la columna FechaActualizacion después de PorcentajeDatosLaborales
            $table->date('FechaActualizacion')->nullable()->after('PorcentajeDatosLaborales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catParametrosPerfilTrans', function (Blueprint $table) {
            $table->dropColumn('FechaActualizacion');
        });
    }
};
