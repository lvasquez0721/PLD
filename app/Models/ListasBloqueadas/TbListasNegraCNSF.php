<?php

namespace App\Models\ListasBloqueadas;

use Illuminate\Database\Eloquent\Model;

class TbListasNegraCNSF extends Model
{
    protected $table = 'tbListasNegraCNSF';

    protected $primaryKey = 'IDRegistroListaCNSF';

    public $incrementing = true; // El PK es autoincremental según la migración de 2026_03_11_183318_fix_tb_listas_negra_c_n_s_f

    protected $keyType = 'int';

    public $timestamps = true;

    protected $fillable = [
        // 'IDRegistroListaCNSF', // No incluir campo autoincremental en $fillable
        'Nombre',
        'Direccion',
        'RFC',
        'CURP',
        'Observaciones',
        'Pais',
        'FechaNacimiento',
        'Acuerdo',
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

    // Relación para obtener los oficios relacionados con la lista negra CNSF
    public function oficios()
    {
        return $this->hasMany(TbControlOficios::class, 'IDListaN', 'IDRegistroListaCNSF');
    }
}
