<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IDRRPLD extends Model
{
    protected $table = "IDRRPLD";

    protected $fillable = [
        'TipoReporte',
        'PeriodoReporte',
        'Folio',
        'OrganoSupervisor',
        'CveSujetoObligado',
        'Localidad',
        'Sucursal',
        'TipoOperacion',
        'InstrumentoMonetario',
        'NoPoliza',
        'Monto',
        'Moneda',
        'FechaOperacion',
        'FechaDeteccion',
        'Nacionalidad',
        'TipoPersona',
        'RazonSocial',
        'Nombre',
        'APaterno',
        'AMaterno',
        'RFC',
        'CURP',
        'FechaNacimiento',
        'Domicilio',
        'Colonia',
        'Ciudad',
        'Telefono',
        'Ocupacion',
        'NombreAgente',
        'APaternoAgente',
        'AMaternoAgente',
        'RFCAgente',
        'CURPAgente',
        'Cuenta',
        'NoPolizaCuenta',
        'CveSujetoObl',
        'NombreTitular',
        'APaternoTitular',
        'AMaternoTitular',
        'Descripcion',
        'Razon',
        'Estatus',
    ];

    protected $casts = [
        'Monto' => 'decimal:6',
        'FechaOperacion' => 'datetime',
        'FechaDeteccion' => 'datetime',
        'FechaNacimiento' => 'datetime',
    ];
}
