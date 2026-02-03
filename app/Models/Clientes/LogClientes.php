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
    public $incrementing = true; // Continúa siendo autoincremental según la migración
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
        'IDNacionalidad', // Ahora debe ser tratado como string en los modelos y formularios
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

    /**
     * Relación con CatNacionalidad, SIN foreign key en base de datos,
     * ya que la migración elimina el constraint FK de IDNacionalidad!
     *
     * NOTA: Asegúrate de que 'IDNacionalidad' se maneje como string.
     */
    public function nacionalidad()
    {
        return $this->belongsTo(
            CatNacionalidad::class,
            'IDNacionalidad', // foreign key en logClientes (tipo string)
            'IDNacionalidad'  // local key en catNacionalidad (tipo string)
        );
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
