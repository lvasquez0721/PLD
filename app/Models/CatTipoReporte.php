<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatTipoReporte extends Model
{
    protected $table = 'catTipoReporte';

    protected $primaryKey = 'IDTipoReporte';

    public $incrementing = false;

    protected $keyType = 'unsignedBigInteger';

    protected $fillable = [
        'IDTipoReporte',
        'TipoReporte',
    ];

    /**
     * Relación con los reportes regulatorios (tbReporteRegulatorioPLD)
     */
    public function reportesRegulatorios()
    {
        return $this->hasMany(TbReporteRegulatorioPLD::class, 'IDTipoReporte', 'IDTipoReporte');
    }
}
