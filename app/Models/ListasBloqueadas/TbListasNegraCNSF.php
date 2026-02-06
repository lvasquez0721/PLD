<?php

namespace App\Models\ListasBloqueadas;

use Illuminate\Database\Eloquent\Model;

class TbListasNegraCNSF extends Model
{
    protected $table = 'tbListasNegraCNSF';

    protected $primaryKey = 'IDRegistroListaCNSF';

    public $incrementing = true; // El PK es autoincremental según la migración

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        // 'IDRegistroListaCNSF', // No incluir campo autoincremental en $fillable
        'Nombre',
        'Direccion',
        'RFC',
        'CURP',
        'Pais',
        'FechaNacimiento',
        'OficiosRelacionados',
        'UsuarioAlta',
        'TimeStampAlta',
        'UsuarioModif',
        'TimeStampModif',
    ];

    protected $casts = [
        'FechaNacimiento' => 'date',
        'TimeStampAlta' => 'datetime',
        'TimeStampModif' => 'datetime',
    ];

    // RELACIÓN (opcional, útil si luego quieres obtener oficios)
    public function oficios()
    {
        return $this->hasMany(TbControlOficios::class, 'IDListaN', 'IDRegistroListaCNSF');
    }
}
