<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EstadoSeeder extends Seeder
{
    
    public function run(): void {
        $fechaActual = Carbon::now();

        DB::table('catestados')->insert([
            ['IDEstado' => '01', 'Estado' => 'Aguascalientes',       'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '02', 'Estado' => 'Baja California',       'IndicePaz' => 3, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '03', 'Estado' => 'Baja California Sur',   'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '04', 'Estado' => 'Campeche',              'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '05', 'Estado' => 'Coahuila',              'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '06', 'Estado' => 'Colima',                'IndicePaz' => 3, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '07', 'Estado' => 'Chiapas',               'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '08', 'Estado' => 'Chihuahua',             'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '09', 'Estado' => 'Ciudad de México',      'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '10', 'Estado' => 'Durango',               'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '11', 'Estado' => 'Guanajuato',            'IndicePaz' => 3, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '12', 'Estado' => 'Guerrero',              'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '13', 'Estado' => 'Hidalgo',               'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '14', 'Estado' => 'Jalisco',               'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '15', 'Estado' => 'México',                'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '16', 'Estado' => 'Michoacán',             'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '17', 'Estado' => 'Morelos',               'IndicePaz' => 3, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '18', 'Estado' => 'Nayarit',               'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '19', 'Estado' => 'Nuevo León',            'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '20', 'Estado' => 'Oaxaca',                'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '21', 'Estado' => 'Puebla',                'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '22', 'Estado' => 'Querétaro',             'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '23', 'Estado' => 'Quintana Roo',          'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '24', 'Estado' => 'San Luis Potosí',       'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '25', 'Estado' => 'Sinaloa',               'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '26', 'Estado' => 'Sonora',                'IndicePaz' => 2, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '27', 'Estado' => 'Tabasco',               'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '28', 'Estado' => 'Tamaulipas',            'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '29', 'Estado' => 'Tlaxcala',              'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '30', 'Estado' => 'Veracruz',              'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '31', 'Estado' => 'Yucatán',               'IndicePaz' => 1, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
            ['IDEstado' => '32', 'Estado' => 'Zacatecas',             'IndicePaz' => 3, 'created_at' => $fechaActual, 'updated_at' => $fechaActual],
        ]);
    }
}
