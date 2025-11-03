<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CatIDClientesSistema extends Model
{
    use HasFactory;

    protected $table = 'catIDClientesSistema';
    protected $primaryKey = 'IDOrigenSistema';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDOrigenSistema',
        'IDCliente',
        'IDSistema',
        'NCliente',
    ];
}
