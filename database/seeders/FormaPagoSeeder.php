<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FormaPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {
        $fechaActual = Carbon::now();

        DB::table('catformapagos')->insert([
            ['IDFormaPago' => 1, 'FormaPago' => 'Efectivo', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDFormaPago' => 2, 'FormaPago' => 'Cheque', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDFormaPago' => 3, 'FormaPago' => 'Transferencia', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDFormaPago' => 4, 'FormaPago' => 'Compensación', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDFormaPago' => 5, 'FormaPago' => 'Tarjeta débito', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDFormaPago' => 6, 'FormaPago' => 'Tarjeta crédito', 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
        ]);
    }
}
