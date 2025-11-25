<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Clientes\CatEstados;
use App\Models\Clientes\CatTipoPersona;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\TbClientes;

class LogClientes extends Model
{
    use HasFactory;

    protected $table = 'logClientes';
    protected $primaryKey = 'IDLogCliente';
    public $incrementing = true; // Debe ser autoincremental según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        // 'IDLogCliente', // No incluir campo autoincrementable en $fillable
        'IDCliente',
        'RfcAnterior',
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
        'TimeStampLog',
    ];

    // Relaciones con otras tablas relevantes

    public function cliente()
    {
        return $this->belongsTo(TbClientes::class, 'IDCliente', 'IDCliente');
    }

    public function tipoPersona()
    {
        return $this->belongsTo(CatTipoPersona::class, 'IDTipoPersona', 'IDTipoPersona');
    }

    public function nacionalidad()
    {
        return $this->belongsTo(CatNacionalidad::class, 'IDNacionalidad', 'IDNacionalidad');
    }

    public function estadoNacimiento()
    {
        return $this->belongsTo(CatEstados::class, 'IDEstadoNacimiento', 'IDEstado');
    }

    public function ocupacionGiro()
    {
        return $this->belongsTo(CatOcupacionesGiros::class, 'IDOcupacionGiro', 'IDOcupacionGiro');
    }
}
