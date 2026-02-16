<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert catalog entries into catTipoReporte
        DB::table('catTipoReporte')->insert([
            ['IDTipoReporte' => 1, 'TipoReporte' => 'Relevante'],
            ['IDTipoReporte' => 2, 'TipoReporte' => 'Inusual'],
            ['IDTipoReporte' => 3, 'TipoReporte' => 'Preocupante'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the inserted catalog entries using IDs
        DB::table('catTipoReporte')->whereIn('IDTipoReporte', [1, 2, 3])->delete();
    }
};
