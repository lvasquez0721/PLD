<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatEstados extends Model
{
    use HasFactory;

    protected $table = 'catEstados';
    protected $primaryKey = 'IDEstado';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDEstado',
        'Estado',
        'IndicePaz',
		'CveEntidad'
    ];
}
