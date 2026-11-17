<?php

namespace App\Models\Clientes;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TbClientes extends Model
{
    use HasFactory;

    public const TIPO_CLIENTE = 'Cliente';

    public const TIPO_AGENTE = 'Agente';

    public const TIPO_COLABORADOR = 'Colaborador';

    protected $table = 'tbClientes';

    protected $primaryKey = 'IDCliente';

    public $incrementing = true; // IDCliente es autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        // 'IDCliente', // NO incluimos campos autoincrementales en $fillable
        'ckAgente',
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
        'EsAltoRiesgo',
        'IDNacionalidad',
        'IDEstadoNacimiento',
        'Activo',
        'Preguntas',
        'IngresosEstimados',
        'TipoPersona', // Añadido campo enum según migración 2026_10_23_102642_add_columns_to_tb_clientes.php
    ];

    protected $casts = [
        'IDTipoPersona' => 'integer',
        'EsPPEActivo' => 'boolean',
        'EsAltoRiesgo' => 'boolean',
        'Activo' => 'boolean',
        'FechaNacimiento' => 'date',
        'FechaConstitucion' => 'date',
        'TipoPersona' => 'string', // Cast a string el nuevo campo enum
    ];

    public function scopeDeTipoPersona($query, string $tipo)
    {
        return $query->where('TipoPersona', $tipo);
    }

    public function domicilios()
    {
        return $this->hasMany(TbClientesDomicilio::class, 'IDCliente', 'IDCliente');
    }

    public function idsSistema()
    {
        return $this->hasMany(CatIDClientesSistema::class, 'IDCliente', 'IDCliente');
    }

    public function operaciones()
    {
        return $this->hasMany(\App\Models\TbOperaciones::class, 'IDCliente', 'IDCliente');
    }

    public function tipoPersona()
    {
        return $this->belongsTo(CatTipoPersona::class, 'IDTipoPersona', 'IDTipoPersona');
    }

    public function ocupacionGiro()
    {
        return $this->belongsTo(CatOcupacionesGiros::class, 'IDOcupacionGiro', 'IDOcupacionGiro');
    }

    /**
     * Clave de identidad de la persona para evitar alertas duplicadas cuando
     * existen registros repetidos del mismo cliente.
     */
    public static function claveIdentidadAlerta(self $cliente): string
    {
        $rfc = strtoupper(trim((string) ($cliente->RFC ?? '')));

        if ($rfc !== '' && $rfc !== 'XAXX010101000') {
            return 'RFC:'.$rfc;
        }

        $curp = strtoupper(trim((string) ($cliente->CURP ?? '')));

        if ($curp !== '') {
            return 'CURP:'.$curp;
        }

        $razonSocial = strtoupper(trim((string) ($cliente->RazonSocial ?? '')));

        if ($razonSocial !== '') {
            return 'RAZON:'.preg_replace('/\s+/', ' ', $razonSocial);
        }

        $nombre = strtoupper(trim(implode(' ', array_filter([
            $cliente->Nombre ?? null,
            $cliente->ApellidoPaterno ?? null,
            $cliente->ApellidoMaterno ?? null,
        ], fn ($valor) => trim((string) $valor) !== ''))));

        if ($nombre !== '') {
            return 'NOMBRE:'.preg_replace('/\s+/', ' ', $nombre);
        }

        return 'CLIENTE:'.$cliente->IDCliente;
    }
}
