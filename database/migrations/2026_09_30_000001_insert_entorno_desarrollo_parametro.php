<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Etiqueta global "Entorno de desarrollo".
     * Se persiste como parámetro booleano en catParametriaPLD
     * para que un switch en la UI pueda activarla/desactivarla.
     */
    public function up(): void
    {
        $exists = DB::table('catParametriaPLD')
            ->where('IDParametro', 100)
            ->exists();

        if (! $exists) {
            DB::table('catParametriaPLD')->insert([
                'IDParametro' => 100,
                'Parametro' => 'entorno_desarrollo',
                'Valor' => '0',
                'TipoDato' => 'boolean',
                'Activo' => 1,
                'TimeStampAlta' => now(),
                'TimeStampModificacion' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('catParametriaPLD')->where('IDParametro', 100)->delete();
    }
};
