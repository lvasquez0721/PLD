<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TbReporteRegulatorioPLD extends Model
{
    protected $table = 'tbReporteRegulatorioPLD';
    protected $primaryKey = 'IDReporte';
    public $incrementing = true; // El PK ES autoincremental según la migración
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        // 'IDReporte', // No incluir campo autoincremental en $fillable
        'IDRegistroAlerta',
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
        'IDMoneda',
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

    /**
     * Relación con la alerta (tbAlertas)
     */
    public function alerta()
    {
        return $this->belongsTo(TbAlertas::class, 'IDRegistroAlerta', 'IDRegistroAlerta');
    }

    /**
     * Relación con la moneda (catMonedas)
     */
    public function moneda()
    {
        return $this->belongsTo(CatMonedas::class, 'IDMoneda', 'IDMoneda');
    }
}
