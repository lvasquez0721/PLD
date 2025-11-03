<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbOperacionesBeneficiarios extends Model
{
    protected $table = 'tbOperacionesBeneficiarios';
    protected $primaryKey = 'IDOperacionBeneficiario';
    public $incrementing = false; // El PK no es autoincrement según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDOperacion',
        'RFCBeneficiario',
        'CURPBeneficiario',
        'NombreBeneficiario',
        'APaternoBeneficiario',
        'AMaternoBeneficiario',
        'RazonSocialBeneficiario',
        'Preferente',
        'PorcentajeParticipacion',
    ];

    /**
     * Relación con la operación principal (tbOperaciones)
     */
    public function operacion()
    {
        return $this->belongsTo(TbOperaciones::class, 'IDOperacion', 'IDOperacion');
    }
}
