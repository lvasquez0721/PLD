<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatMunicipio extends Model
{
    use HasFactory;

    protected $table = 'catMunicipio';
    protected $primaryKey = 'IDMunicipio';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDMunicipio',
        'Municipio',
        'IDEstado',
    ];
}
