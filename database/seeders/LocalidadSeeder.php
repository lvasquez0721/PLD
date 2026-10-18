<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class LocalidadSeeder extends Seeder
{
    /**
     * Ruta del archivo CSV de localidades (raiz del proyecto).
     * Formato: localidad;cveLocalidad  (delimitador punto y coma)
     * cveLocalidad: 8 digitos EE MM LLLL (entidad 2 + municipio 2 + localidad 4).
     */
    private const CSV_PATH = 'localidades.csv';

    public function run(): void
    {
        $ruta = base_path(self::CSV_PATH);
        if (! file_exists($ruta)) {
            return;
        }

        $fechaActual = Carbon::now();
        $registros   = [];
        $abrir       = fopen($ruta, 'r');

        if (! $abrir) {
            return;
        }

        $primera = true;
        while (($linea = fgetcsv($abrir, 0, ';')) !== false) {
            if ($primera) {
                $primera = false;
                continue; // cabecera: localidad;cveLocalidad
            }

            if (count($linea) < 2) {
                continue;
            }

            $localidad = trim($linea[0] ?? '');
            $cve       = trim($linea[1] ?? '');

            if ($localidad === '' || $cve === '' || ! is_numeric($cve)) {
                continue;
            }

            $cve = str_pad((string) $cve, 8, '0', STR_PAD_LEFT);

            $registros[] = [
                'IDLocalidad'  => $cve,
                'Localidad'    => $localidad,
                'IDMunicipio'  => substr($cve, 2, 2),
                'IDEstado'     => substr($cve, 0, 2),
                'created_at'   => $fechaActual,
                'updated_at'   => $fechaActual,
            ];

            if (count($registros) >= 500) {
                DB::table('catLocalidad')->upsert(
                    $registros,
                    ['IDLocalidad'],
                    ['Localidad', 'IDMunicipio', 'IDEstado', 'updated_at']
                );
                $registros = [];
            }
        }

        fclose($abrir);

        if (! empty($registros)) {
            DB::table('catLocalidad')->upsert(
                $registros,
                ['IDLocalidad'],
                ['Localidad', 'IDMunicipio', 'IDEstado', 'updated_at']
            );
        }
    }
}
