<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebr_siniestros', function (Blueprint $table) {
            $table->dropUnique('ebr_siniestros_unico');
            $table->index(['ejercicio_id', 'no_siniestro'], 'ebr_siniestros_no_siniestro_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ebr_siniestros', function (Blueprint $table) {
            $table->dropIndex('ebr_siniestros_no_siniestro_idx');
            $table->unique(['ejercicio_id', 'no_siniestro'], 'ebr_siniestros_unico');
        });
    }
};
