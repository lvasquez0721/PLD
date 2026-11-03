<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existe = DB::table('catParametriaPLD')
            ->where('Parametro', 'percentMatchPPE')
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('catParametriaPLD')->insert([
            'IDParametro' => 104,
            'Parametro' => 'percentMatchPPE',
            'Valor' => '90',
            'TipoDato' => 'number',
            'Activo' => 1,
            'TimeStampAlta' => now(),
            'TimeStampModificacion' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('catParametriaPLD')
            ->where('Parametro', 'percentMatchPPE')
            ->delete();
    }
};
