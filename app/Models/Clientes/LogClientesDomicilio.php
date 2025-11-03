<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LogClientesDomicilio extends Model
{
    use HasFactory;

    protected $table = 'logClientesDomicilio';
    protected $primaryKey = 'IDLogDomicilio';
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
        'IDMunicipio',
        'IDLocalidad',
        'Telefono',
    ];
}