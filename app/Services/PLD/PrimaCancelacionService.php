<?php

namespace App\Services\PLD;

use App\Models\TbOperaciones;
use Illuminate\Support\Facades\DB;

/**
 * Regla de negocio: todo endoso de cancelación debe ser el espejo exacto
 * del historial previo de la póliza, de modo que el balance final sea 0.
 *
 * primaEsperada = -SUM(PrimaTotal de operaciones previas con mismo FolioPoliza)
 * Comparación exacta a 2 decimales y con signo (la cancelación debe ser negativa).
 */
class PrimaCancelacionService
{
    /**
     * Suma del historial previo de la póliza (emisión + endosos anteriores).
     * Retorna null si no hay historial (póliza sin operaciones previas).
     *
     * @param  string  $folioPoliza  Folio normalizado (trim)
     * @param  int|null  $excludeId  IDOperacion a excluir (útil en corrección histórica)
     */
    public static function sumaHistorialPrevio(string $folioPoliza, ?int $excludeId = null): ?float
    {
        $query = TbOperaciones::where('FolioPoliza', $folioPoliza);

        if ($excludeId !== null) {
            $query->where('IDOperacion', '!=', $excludeId);
        }

        // Dentro de una transacción el llamador puede haber aplicado lockForUpdate;
        // aquí no lo forzamos para permitir uso en SELECTs de lectura (dry-run).
        $existe = (clone $query)->exists();

        if (! $existe) {
            return null;
        }

        return round((float) (clone $query)->sum('PrimaTotal'), 2);
    }

    /**
     * Prima esperada del endoso de cancelación: -sumaHistorialPrevio.
     * Retorna null si no hay historial previo.
     */
    public static function primaEsperada(string $folioPoliza, ?int $excludeId = null): ?float
    {
        $suma = self::sumaHistorialPrevio($folioPoliza, $excludeId);

        if ($suma === null) {
            return null;
        }

        // +0.0 normaliza un eventual -0.0 a 0.0 positivo.
        return round(-$suma, 2) + 0.0;
    }

    /**
     * Comparación exacta a 2 decimales con signo.
     * Normaliza ambos valores con number_format para evitar errores de float.
     * Trata 0.00 y -0.00 como iguales.
     */
    public static function esExacta(float|int|string $recibida, float|int|string $esperada): bool
    {
        $a = (float) $recibida;
        $b = (float) $esperada;

        if ($a == 0.0 && $b == 0.0) {
            return true;
        }

        return number_format($a, 2, '.', '') === number_format($b, 2, '.', '');
    }

    public static function necesitaCorreccion(float|int|string $recibida, float|int|string $esperada): bool
    {
        return ! self::esExacta($recibida, $esperada);
    }

    /**
     * Suma del historial previo bloqueando filas (para uso dentro de insertarOperacion,
     * evita race conditions entre dos cancelaciones concurrentes).
     * Retorna [existeHistorial(bool), suma(float)].
     */
    public static function sumaHistorialPrevioConBloqueo(string $folioPoliza): array
    {
        $primas = TbOperaciones::where('FolioPoliza', $folioPoliza)
            ->lockForUpdate()
            ->pluck('PrimaTotal');

        if ($primas->isEmpty()) {
            return [false, 0.0];
        }

        $suma = round((float) $primas->sum(), 2);

        return [true, $suma];
    }
}
