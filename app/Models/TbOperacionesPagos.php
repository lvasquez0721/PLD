<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbOperacionesPagos extends Model
{
    protected $table = 'tbOperacionesPagos';

    protected $primaryKey = 'IDOperacionPago';

    public $incrementing = true; // El PK ES autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDOperacion',
        'IDCliente',
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
}
