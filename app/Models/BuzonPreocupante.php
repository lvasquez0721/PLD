<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuzonPreocupante extends Model
{
    use HasFactory;

    protected $table = 'tbBuzonPreocupantes';
    protected $primaryKey = 'idBuzonPreocupantes';
    public $timestamps = false;

    protected $fillable = [
        'IDReporteOP',
        'Fecha',
        'Descripcion',
        'Usuario',
        'Estatus'
    ];
}

