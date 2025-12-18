<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbDetallesPago extends Model
{
    protected $table = 'tb_detalles_pago';
    protected $primaryKey = 'IDDetallePago';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDOperacionPago',
        'MontoPagado',
    ];

    /**
     * Relación con el modelo de pagos de operación.
     */
    public function operacionPago()
    {
        return $this->belongsTo(TbOperacionesPagos::class, 'IDOperacionPago', 'IDOperacionPago');
    }
}
