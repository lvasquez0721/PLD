<?php

namespace App\Models;

use App\Models\Clientes\TbClientes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TbOperaciones extends Model
{
    protected $table = 'tbOperaciones';
    protected $primaryKey = 'IDOperacion';
    public $incrementing = false; // El PK no es autoincrement según la migración
    protected $keyType = 'int';
    public $timestamps = false; // No hay timestamps en la migración

    protected $fillable = [
        'IDOperacion',
        'IDCliente',
        'FolioPoliza',
        'FolioEndoso',
        'FechaEmision',
        'PrimaTotal',
        'GastosEmision',
        'RFCAgente',
        'CURPAgente',
        'NombreAgente',
        'APaternoAgente',
        'AMaternoAgente',
    ];

    /**
     * Relación con el Cliente (tbClientes)
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(TbClientes::class, 'IDCliente', 'IDCliente');
    }

    /**
     * Relación con los Beneficiarios (tbOperacionesBeneficiarios)
     */
    public function beneficiarios()
    {
        return $this->hasMany(TbOperacionesBeneficiarios::class, 'IDOperacion', 'IDOperacion');
    }

    /**
     * Relación con el Asegurado (tbOperacionesAsegurado)
     */
    public function asegurado()
    {
        return $this->hasOne(TbOperacionesAsegurado::class, 'IDOperacion', 'IDOperacion');
    }

    /**
     * Relación con los Pagos (tbOperacionesPagos)
     */
    public function pagos()
    {
        return $this->hasMany(TbOperacionesPagos::class, 'IDOperacion', 'IDOperacion');
    }
}
