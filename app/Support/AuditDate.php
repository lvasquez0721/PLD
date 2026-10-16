<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Fecha de auditoría para correos y perfil transaccional.
 *
 * REVERSIÓN: poner AUDIT_BACKDATE_CORREOS=false y AUDIT_BACKDATE_PERFIL=false
 * en .env + `php artisan config:clear`. Sin tocar código.
 */
class AuditDate
{
    /**
     * ¿Backdate de fecha visual en correos activo?
     */
    public static function correosEnabled(): bool
    {
        return (bool) config('auditoria.backdate_correos', false);
    }

    /**
     * ¿Backdate de FechaEjecucción del perfil activo?
     */
    public static function perfilEnabled(): bool
    {
        return (bool) config('auditoria.backdate_perfil', false);
    }

    /**
     * TODO AUDITORES (temporal, hardcodeado): misma fecha con año 2025.
     * Revertir al terminar auditoría al condicional con correosEnabled().
     */
    public static function now(): Carbon
    {
        return Carbon::now()->copy()->setYear(2025);
    }
}
