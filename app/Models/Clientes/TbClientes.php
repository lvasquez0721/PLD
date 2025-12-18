<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbClientes extends Model
{
    use HasFactory;

    protected $table = 'tbClientes';
    protected $primaryKey = 'IDCliente';
    public $incrementing = true; // IDCliente es autoincremental según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        // 'IDCliente', // NO incluimos campos autoincrementales en $fillable
        'RFC',
        'Nombre',
        'ApellidoPaterno',
        'ApellidoMaterno',
        'RazonSocial',
        'IDTipoPersona',
        'CURP',
        'IDOcupacionGiro',
        'FechaNacimiento',
        'FechaConstitucion',
        'FolioMercantil',
        'CoincideEnListasNegras',
        'EsPPEActivo',
        'IDNacionalidad',
        'IDEstadoNacimiento',
        'Activo',
        'Preguntas'
    ];

    protected $casts = [
        'CoincideEnListasNegras' => 'boolean',
        'EsPPEActivo' => 'boolean',
        'Activo' => 'boolean',
        'FechaNacimiento' => 'date',
        'FechaConstitucion' => 'date',
    ];
}
