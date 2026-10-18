<?php

namespace App\Support;

/**
 * Utilidades para interpretar la CURP.
 */
class Curp
{
    /**
     * Obtiene la fecha de nacimiento (Y-m-d) a partir de una CURP.
     *
     * La CURP guarda la fecha en las posiciones 5-10 (YYMMDD). El siglo se
     * determina con el carácter 17: dígito para nacidos antes de 2000 y
     * letra para nacidos a partir de 2000.
     */
    public static function fechaNacimiento(?string $curp): ?string
    {
        $curp = preg_replace('/\s+/', '', strtoupper(trim((string) $curp)));

        if (strlen($curp) < 10) {
            return null;
        }

        $anio = substr($curp, 4, 2);
        $mes = substr($curp, 6, 2);
        $dia = substr($curp, 8, 2);

        if (! ctype_digit($anio.$mes.$dia)) {
            return null;
        }

        $homoclave = strlen($curp) >= 17 ? substr($curp, 16, 1) : '';

        if ($homoclave !== '') {
            $siglo = ctype_alpha($homoclave) ? 2000 : 1900;
        } else {
            // Sin carácter 17: heurística contra el año actual.
            $siglo = ((int) $anio > (int) now()->format('y')) ? 1900 : 2000;
        }

        $year = $siglo + (int) $anio;
        $month = (int) $mes;
        $day = (int) $dia;

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        // CURP con homoclave inconsistente que produciría un año futuro.
        if ($year > (int) now()->format('Y')) {
            $year -= 100;

            if (! checkdate($month, $day, $year)) {
                return null;
            }
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
