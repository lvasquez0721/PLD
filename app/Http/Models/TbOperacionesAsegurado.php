<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbOperacionesAsegurado extends Model
{
    protected $table = 'tbOperacionesAsegurado';
    protected $primaryKey = 'IDAsegurado';
    public $incrementing = false; // El PK NO es autoincrementable según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDOperacion',
        'RFCAsegurado',
        'CURPAsegurado',
        'NombreAsegurado',
        'APaternoAsegurado',
        'AMaternoAsegurado',
        'RazonSocialAsegurado',
    ];

    /**
     * Relación: Este asegurado pertenece a una operación (tbOperaciones)
     */
    public function operacion()
    {
        return $this->belongsTo(TbOperaciones::class, 'IDOperacion', 'IDOperacion');
    }
}
