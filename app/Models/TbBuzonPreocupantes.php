<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbBuzonPreocupantes extends Model
{
    protected $table = 'tbBuzonPreocupantes';

    protected $primaryKey = 'idBuzonPreocupantes';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'IDReporteOP',
        'Fecha',
        'Descripcion',
        'Usuario',
        'Estatus',
    ];

    public $timestamps = true;

    protected $casts = [
        'Fecha' => 'date',
    ];
}
