<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoPersonaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $tiposPersona = [
            ['IDTipoPersona' => 1, 'TipoPersona' => 'Física'],
            ['IDTipoPersona' => 2, 'TipoPersona' => 'Moral'],
        ];

        foreach ($tiposPersona as $tipo) {
            DB::table('cattipoPersona')->updateOrInsert(
                ['IDTipoPersona' => $tipo['IDTipoPersona']],
                ['TipoPersona' => $tipo['TipoPersona']]
            );
        }
    }
}
