<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Clientes\CatEstados;

class TbClientesDomicilio extends Model
{
    use HasFactory;

    protected $table = 'tbClientesDomicilio';
    protected $primaryKey = 'IDDomicilio';
    public $incrementing = true; // IDDomicilio es autoincrementable según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'IDDomicilio',
        'IDCliente',
        'Calle',
        'NoExterior',
        'NoInterior',
        'Colonia',
        'CP',
        'IDEstado',
        'Municipio',
        'Localidad',
        'Telefono',
    ];

    // Relación con catEstados
    public function estado()
    {
        return $this->belongsTo(CatEstados::class, 'IDEstado', 'IDEstado');
    }
}
