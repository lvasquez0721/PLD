<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SistemaOrigenSeeder extends Seeder
{
    public function run(): void {
        $fechaActual = Carbon::now();

        DB::table('catsistemas')->insert([
            ['IDSistema' => 1, 'Sistema' => 'SIT',          'Activo' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDSistema' => 2, 'Sistema' => 'Xpertys',       'Activo' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
        ]);
    }
}