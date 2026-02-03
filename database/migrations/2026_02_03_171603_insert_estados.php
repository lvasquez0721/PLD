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
        // Inserción de los estados en la tabla catEstados, con el índice de paz actualizado según la columna "Puntaje" del archivo proporcionado
        $estados = [
            ['IDEstado' => 1,  'Estado' => 'Aguascalientes',      'IndicePaz' => '2.4', 'CveEntidad' => '01'],
            ['IDEstado' => 2,  'Estado' => 'Baja California',     'IndicePaz' => '4.1', 'CveEntidad' => '02'],
            ['IDEstado' => 3,  'Estado' => 'Baja California Sur', 'IndicePaz' => '3.0', 'CveEntidad' => '03'],
            ['IDEstado' => 4,  'Estado' => 'Campeche',            'IndicePaz' => '2.1', 'CveEntidad' => '04'],
            ['IDEstado' => 5,  'Estado' => 'Coahuila de Zaragoza', 'IndicePaz' => '2.1', 'CveEntidad' => '05'],
            ['IDEstado' => 6,  'Estado' => 'Colima',              'IndicePaz' => '4.7', 'CveEntidad' => '06'],
            ['IDEstado' => 7,  'Estado' => 'Chiapas',             'IndicePaz' => '1.9', 'CveEntidad' => '07'],
            ['IDEstado' => 8,  'Estado' => 'Chihuahua',           'IndicePaz' => '3.6', 'CveEntidad' => '08'],
            ['IDEstado' => 9,  'Estado' => 'Ciudad de México',    'IndicePaz' => '2.7', 'CveEntidad' => '09'],
            ['IDEstado' => 10, 'Estado' => 'Durango',             'IndicePaz' => '1.8', 'CveEntidad' => '10'],
            ['IDEstado' => 11, 'Estado' => 'Guanajuato',          'IndicePaz' => '4.3', 'CveEntidad' => '11'],
            ['IDEstado' => 12, 'Estado' => 'Guerrero',            'IndicePaz' => '3.3', 'CveEntidad' => '12'],
            ['IDEstado' => 13, 'Estado' => 'Hidalgo',             'IndicePaz' => '2.2', 'CveEntidad' => '13'],
            ['IDEstado' => 14, 'Estado' => 'Jalisco',             'IndicePaz' => '2.7', 'CveEntidad' => '14'],
            ['IDEstado' => 15, 'Estado' => 'Estado de México',    'IndicePaz' => '3.4', 'CveEntidad' => '15'],
            ['IDEstado' => 16, 'Estado' => 'Michoacán',           'IndicePaz' => '2.9', 'CveEntidad' => '16'],
            ['IDEstado' => 17, 'Estado' => 'Morelos',             'IndicePaz' => '4.3', 'CveEntidad' => '17'],
            ['IDEstado' => 18, 'Estado' => 'Nayarit',             'IndicePaz' => '2.1', 'CveEntidad' => '18'],
            ['IDEstado' => 19, 'Estado' => 'Nuevo León',          'IndicePaz' => '3.5', 'CveEntidad' => '19'],
            ['IDEstado' => 20, 'Estado' => 'Oaxaca',              'IndicePaz' => '2.5', 'CveEntidad' => '20'],
            ['IDEstado' => 21, 'Estado' => 'Puebla',              'IndicePaz' => '2.4', 'CveEntidad' => '21'],
            ['IDEstado' => 22, 'Estado' => 'Querétaro',           'IndicePaz' => '2.7', 'CveEntidad' => '22'],
            ['IDEstado' => 23, 'Estado' => 'Quintana Roo',        'IndicePaz' => '3.8', 'CveEntidad' => '23'],
            ['IDEstado' => 24, 'Estado' => 'San Luis Potosí',     'IndicePaz' => '2.9', 'CveEntidad' => '24'],
            ['IDEstado' => 25, 'Estado' => 'Sinaloa',             'IndicePaz' => '3.2', 'CveEntidad' => '25'],
            ['IDEstado' => 26, 'Estado' => 'Sonora',              'IndicePaz' => '3.3', 'CveEntidad' => '26'],
            ['IDEstado' => 27, 'Estado' => 'Tabasco',             'IndicePaz' => '3.1', 'CveEntidad' => '27'],
            ['IDEstado' => 28, 'Estado' => 'Tamaulipas',          'IndicePaz' => '2.4', 'CveEntidad' => '28'],
            ['IDEstado' => 29, 'Estado' => 'Tlaxcala',            'IndicePaz' => '1.7', 'CveEntidad' => '29'],
            ['IDEstado' => 30, 'Estado' => 'Veracruz',            'IndicePaz' => '2.4', 'CveEntidad' => '30'],
            ['IDEstado' => 31, 'Estado' => 'Yucatán',             'IndicePaz' => '1.3', 'CveEntidad' => '31'],
            ['IDEstado' => 32, 'Estado' => 'Zacatecas',           'IndicePaz' => '2.9', 'CveEntidad' => '32'],
        ];

        // Insertar o actualizar para prevenir errores de duplicado en pruebas/migraciones múltiples
        foreach ($estados as $estado) {
            DB::table('catEstados')->updateOrInsert(
                ['IDEstado' => $estado['IDEstado']],
                $estado
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Borra solo los estados insertados por este seeder
        DB::table('catEstados')->whereIn('IDEstado', range(1, 32))->delete();
    }
};
