<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parametros = [
            [
                'IDParametro' => 101,
                'Parametro' => 'Porcentaje Match Nombres Listas',
                'Valor' => '82',
                'TipoDato' => 'number',
            ],
            [
                'IDParametro' => 102,
                'Parametro' => 'Cobertura Minima Nombres Listas',
                'Valor' => '78',
                'TipoDato' => 'number',
            ],
            [
                'IDParametro' => 103,
                'Parametro' => 'Porcentaje Match Apellido Listas',
                'Valor' => '85',
                'TipoDato' => 'number',
            ],
        ];

        foreach ($parametros as $parametro) {
            $exists = DB::table('catParametriaPLD')
                ->where('IDParametro', $parametro['IDParametro'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('catParametriaPLD')->insert($parametro + [
                'Activo' => 1,
                'TimeStampAlta' => now(),
                'TimeStampModificacion' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('catParametriaPLD')->whereIn('IDParametro', [101, 102, 103])->delete();
    }
};
