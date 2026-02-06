<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbClientesPPE extends Model
{
    use HasFactory;

    protected $table = 'tbClientesPPE';

    protected $primaryKey = 'IDDeteccionPPE';

    public $incrementing = true; // Debe ser autoincrementable según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        // 'IDDeteccionPPE', // No incluir clave autoincrementable en $fillable
        'IDCliente',
        'Lista',
        'Cargo',
        'Estado',
        'FechaDeteccion',
        'Origen',
        'TimeStampRegistro',
    ];
}
