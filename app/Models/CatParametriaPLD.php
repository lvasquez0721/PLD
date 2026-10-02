<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatParametriaPLD extends Model
{
    protected $table = 'catParametriaPLD';

    protected $primaryKey = 'IDParametro';

    public $incrementing = false; // El campo IDParametro NO es autoincremental según la migración

    protected $keyType = 'int';

    protected $fillable = [
        'IDParametro', // SÍ debe incluirse ya que no es autoincremental
        'Parametro',
        'Valor',
        'TipoDato',
        'Activo',
        'TimeStampAlta',
        'TimeStampModificacion',
    ];

    public $timestamps = true;

    protected $casts = [
        'Activo' => 'boolean',
        'TimeStampAlta' => 'datetime',
        'TimeStampModificacion' => 'datetime',
    ];

    // Constantes de IDs de parámetros
    const OPERACIONES_RELEVANTES = 1;

    const MONTO_MINIMO_ALERTA = 14;

    const TOLERANCIA_PAGOS_FRACCIONADOS = 15;

    const REPORTEADOR_MONTO_ACUMULADO = 2;

    const MONTO_AUTORIZACION_EFECTIVO_PF = 16;

    const MONTO_AUTORIZACION_EFECTIVO_PM = 17;

    const ENTORNO_DESARROLLO = 100;

    /**
     * Obtiene el valor de un parámetro por su ID.
     * Retorna el valor formateado según su tipo de dato o el valor raw si no se especifica.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public static function getValor(int $id, $default = null)
    {
        $param = self::find($id);

        if (! $param) {
            return $default;
        }

        // Retornar según el tipo de dato si es necesario, por ahora retornamos Valor
        // Si es numérico, casteamos
        if ($param->TipoDato === 'number') {
            return (float) $param->Valor;
        }

        return $param->Valor;
    }

    public static function getReporteadorMontoAcumulado()
    {
        return self::getValor(self::REPORTEADOR_MONTO_ACUMULADO, 75000);
    }

    public static function getOperacionesRelevantes()
    {
        return self::getValor(self::OPERACIONES_RELEVANTES, 7500);
    }

    public static function getMontoMinimoAlerta()
    {
        return self::getValor(self::MONTO_MINIMO_ALERTA, 7500);
    }

    public static function getToleranciaPagosFraccionados()
    {
        return self::getValor(self::TOLERANCIA_PAGOS_FRACCIONADOS, 10);
    }

    /**
     * Indica si la etiqueta global "Entorno de desarrollo" está activa.
     * Fuente de verdad: catParametriaPLD ID 100 (entorno_desarrollo).
     */
    public static function getEntornoDesarrolloActivo(): bool
    {
        $param = self::find(self::ENTORNO_DESARROLLO);

        if (! $param) {
            return false;
        }

        return in_array(strtolower(trim((string) $param->Valor)), ['1', 'true', 'si', 'sí', 'on'], true);
    }

    /**
     * Persiste el estado de la etiqueta "Entorno de desarrollo".
     */
    public static function setEntornoDesarrolloActivo(bool $activo): void
    {
        $now = now();
        $param = self::find(self::ENTORNO_DESARROLLO);

        if ($param) {
            $param->update([
                'Valor' => $activo ? '1' : '0',
                'TimeStampModificacion' => $now,
            ]);

            return;
        }

        self::create([
            'IDParametro' => self::ENTORNO_DESARROLLO,
            'Parametro' => 'entorno_desarrollo',
            'Valor' => $activo ? '1' : '0',
            'TipoDato' => 'boolean',
            'Activo' => 1,
            'TimeStampAlta' => $now,
            'TimeStampModificacion' => $now,
        ]);
    }
}
