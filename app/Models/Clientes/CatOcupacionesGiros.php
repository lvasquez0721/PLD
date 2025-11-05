<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatOcupacionesGiros extends Model
{
    use HasFactory;

    protected $table = 'catOcupacionesGiros';
    protected $primaryKey = 'IDOcupacionGiro';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'IDOcupacionGiro',
        'OcupacionGiro',
    ];
}
