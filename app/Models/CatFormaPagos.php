<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatFormaPagos extends Model
{
    protected $table = 'catFormaPagos';

    protected $primaryKey = 'IDFormaPago';

    public $incrementing = false; // El PK NO es autoincrementable según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        'IDFormaPago',
        'FormaPago',
    ];
}
