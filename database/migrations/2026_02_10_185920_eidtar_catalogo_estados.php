<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
        // Actualiza la columna CveEntidad en catEstados según el catálogo proporcionado
        $estadosCveEntidad = [
            1  => 'AS',
            2  => 'BC',
            3  => 'BS',
            4  => 'CC',
            5  => 'CL',
            6  => 'CM',
            7  => 'CS',
            8  => 'CH',
            9  => 'DF',
            10 => 'DG',
            11 => 'GT',
            12 => 'GR',
            13 => 'HG',
            14 => 'JC',
            15 => 'MC',
            16 => 'MN',
            17 => 'MS',
            18 => 'NT',
            19 => 'NL',
            20 => 'OC',
            21 => 'PL',
            22 => 'QT',
            23 => 'QR',
            24 => 'SP',
            25 => 'SL',
            26 => 'SR',
            27 => 'TC',
            28 => 'TS',
            29 => 'TL',
            30 => 'VZ',
            31 => 'YN',
            32 => 'ZS',
        ];

        foreach ($estadosCveEntidad as $idEstado => $cveEntidad) {
            \Illuminate\Support\Facades\DB::table('catEstados')
                ->where('IDEstado', $idEstado)
                ->update(['CveEntidad' => $cveEntidad]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
