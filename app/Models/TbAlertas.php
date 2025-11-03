<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbAlertas extends Model
{
    protected $table = 'tbAlertas';
    protected $primaryKey = 'IDRegistroAlerta';
    public $incrementing = false; // El PK NO es autoincrementable según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDRegistroAlerta',
        'Folio',
        'Patron',
        'IDCliente',
        'Cliente',
        'Poliza',
        'FechaDeteccion',
        'IDOperacionPago',
        'HoraDeteccion',
        'FechaOperacion',
        'HoraOperacion',
        'MontoOperacion',
        'InstrumentoMonetario',
        'RFCAgente',
        'Agente',
        'Estatus',
        'Descripcion',
        'Razones',
        'Evidencias',
        'IDReporteOP',
        'IDPago',
    ];

    /**
     * Relación con la operación de pago (tbOperacionesPagos)
     */
    public function operacionPago()
    {
        return $this->belongsTo(TbOperacionesPagos::class, 'IDOperacionPago', 'IDOperacionPago');
    }
}
