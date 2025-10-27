<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitudes extends Model
{
    protected $table = "tbSolicitudes";
    // Assuming the primary key is IDSolicitud (corresponding to $table->unsignedBigInteger('IDSolicitud');)
    protected $primaryKey = 'IDSolicitud';
    public $incrementing = false; // Because migration uses $table->unsignedBigInteger, not increments
    protected $keyType = 'int';

    protected $fillable = [
        'IDSolicitud',
        'IDSolicitante',
        'FCreacion',
        'FActualizacion',
        'FGenerada',
        'StatusSolicitud',
        'NoSolicitud',
        'Usuario',
        'IDAgente',
        'año',
    ];

    public $timestamps = true;

    // If there's foreign key relations, you can define relationships here.
    // Example:
    // public function agente() {
    //     return $this->belongsTo(Agente::class, 'IDAgente');
    // }
}
