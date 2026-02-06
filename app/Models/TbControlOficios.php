<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbControlOficios extends Model
{
    use HasFactory;

    protected $table = 'tbcontroloficios';

    protected $primaryKey = 'IDRegistro';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'IDRegistro',
        'IDListaN',
        'PathArchivo',
        'Archivo',
        'IDAccion',
        'TimeStampArchivo',
    ];
}
