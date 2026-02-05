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
    const MONTO_AUTORIZACION_EFECTIVO_PF = 16;
    const MONTO_AUTORIZACION_EFECTIVO_PM = 17;

    /**
     * Obtiene el valor de un parámetro por su ID.
     * Retorna el valor formateado según su tipo de dato o el valor raw si no se especifica.
     *
     * @param int $id
     * @param mixed $default
     * @return mixed
     */
    public static function getValor(int $id, $default = null)
    {
        $param = self::find($id);

        if (!$param) {
            return $default;
        }

        // Retornar según el tipo de dato si es necesario, por ahora retornamos Valor
        // Si es numérico, casteamos
        if ($param->TipoDato === 'number') {
            return (float) $param->Valor;
        }

        return $param->Valor;
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
}
