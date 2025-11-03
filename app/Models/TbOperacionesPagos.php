<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbOperacionesPagos extends Model
{
    protected $table = 'tbOperacionesPagos';
    protected $primaryKey = 'IDOperacionPago';
    public $incrementing = false; // El PK NO es autoincrementable según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDOperacionPago',
        'IDOperacion',
        'Monto',
        'IDMoneda',
        'IDFormaPago',
        'TipoCambio',
        'FechaPago',
        'TimeStampRegistro',
    ];

    /**
     * Relación con la operación principal (tbOperaciones)
     */
    public function operacion()
    {
        return $this->belongsTo(TbOperaciones::class, 'IDOperacion', 'IDOperacion');
    }

    /**
     * Relación con la moneda (catMonedas)
     */
    public function moneda()
    {
        return $this->belongsTo(CatMonedas::class, 'IDMoneda', 'IDMoneda');
    }

    /**
     * Relación con la forma de pago (catFormaPagos)
     */
    public function formaPago()
    {
        return $this->belongsTo(CatFormaPagos::class, 'IDFormaPago', 'IDFormaPago');
    }
}
