<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogCorreo extends Model
{
    protected $table = 'logCorreos';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = true;

    public const TIPOS = ['OficialCumplimiento', 'RiesgoAlto', 'AlertaConsolidada'];

    public const ESTADOS = ['Enviado', 'Fallido', 'OmitidoSinConfig'];

    protected $fillable = [
        'Destinatario',
        'NombreDestinatario',
        'Asunto',
        'Tipo',
        'IDCliente',
        'IDOperacion',
        'Patrones',
        'AlertasCount',
        'CuerpoHtml',
        'Truncado',
        'Estado',
        'Error',
    ];

    protected $casts = [
        'AlertasCount' => 'integer',
        'Truncado' => 'boolean',
    ];
}
