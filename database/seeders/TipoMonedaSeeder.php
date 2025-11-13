<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TipoMonedaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $fechaActual = Carbon::now();

        DB::table('catmonedas')->insert([
            ['IDMoneda' => 1, 'Moneda' => 'Pesos Mexicanos', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDMoneda' => 2, 'Moneda' => 'Dólares americanos', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
        ]);
    }
}
