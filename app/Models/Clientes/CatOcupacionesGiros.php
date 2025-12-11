<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatOcupacionesGiros extends Model
{
    use HasFactory;

    protected $table = 'catOcupacionesGiros';
    protected $primaryKey = 'IDOcupacionGiro';
    public $incrementing = true; // IDOcupacionGiro es autoincremental según la migración
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        // 'IDOcupacionGiro', // NO incluimos campos autoincrementales en $fillable
        'CVE_GIRO',
        'OcupacionGiro',
		'NivelRiesgo'
    ];
}
