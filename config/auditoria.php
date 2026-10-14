<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backdate de auditoría (fechas 2025)
    |--------------------------------------------------------------------------
    |
    | AUDIT_BACKDATE_CORREOS=true  -> los correos muestran la misma fecha
    |   con un año menos (13/10/2026 => 13/10/2025). Solo visual, no toca BD.
    | AUDIT_BACKDATE_PERFIL=true   -> al ejecutar el perfil transaccional,
    |   FechaEjecucción del lote recién creado se reescribe a -1 año.
    |
    | REVERSIÓN: poner ambos en false + `php artisan config:clear`.
    |
    */

    'backdate_correos' => env('AUDIT_BACKDATE_CORREOS', false),

    'backdate_perfil' => env('AUDIT_BACKDATE_PERFIL', false),

];
