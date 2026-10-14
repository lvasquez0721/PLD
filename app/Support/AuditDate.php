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
     * "Misma fecha, un año atrás": 13/10/2026 10:42 -> 13/10/2025 10:42.
     * Si el flag está apagado, devuelve la hora real.
     */
    public static function now(): Carbon
    {
        $now = Carbon::now();

        return self::correosEnabled() ? $now->copy()->subYear() : $now;
    }
}
