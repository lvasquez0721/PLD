<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbClientesPPE extends Model
{
    use HasFactory;

    protected $table = 'tbClientesPPE';
    protected $primaryKey = 'IDDeteccionPPE';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDDeteccionPPE',
        'IDCliente',
        'Lista',
        'Cargo',
        'Estado',
        'FechaDeteccion',
        'Origen',
        'TimeStampRegistro',
    ];
}
