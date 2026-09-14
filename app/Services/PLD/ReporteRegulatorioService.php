<?php

namespace App\Services\PLD;

use App\Models\CatTipoOperacion;
use App\Models\CatTipoReporte;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\CatTipoPersona;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbOperaciones;
use App\Models\TbReporteRegulatorioPLD;

class ReporteRegulatorioService
{
    /**
     * Genera (o actualiza) el reporte regulatorio asociado a una alerta
     * y lo deja con estatus "Enviado".
     */
    public function emitirDesdeAlerta(TbAlertas $alerta): TbReporteRegulatorioPLD
    {
        $cliente = $alerta->IDCliente
            ? TbClientes::with(['domicilios'])->find($alerta->IDCliente)
            : null;

        $operacion = $alerta->IDOperacion
            ? TbOperaciones::find($alerta->IDOperacion)
            : null;

        $domicilio = null;
        $colonia   = null;
        $ciudad    = null;
        $telefono  = null;

        if ($cliente && $cliente->domicilios->isNotEmpty()) {
            $d = $cliente->domicilios->first();

            $domicilio = trim(implode(' ', array_filter([
                $d->Calle ?? '',
                $d->NoExterior ?? '',
                $d->NoInterior ?? '',
                $d->CP ? 'CP: ' . $d->CP : '',
            ]))) ?: null;

            $colonia  = $d->Colonia ?? null;
            $ciudad   = $d->Municipio ?? null;
            $telefono = $d->Telefono ?? null;
        }

        $hoy = now()->format('Y-m-d');

        [$idTipoReporte, $tipoReporteNombre] = $this->mapearTipoReporte($alerta->Patron);

        $tipoOperacionNombre = CatTipoOperacion::where('IDTipoOperacion', 10)->value('TipoOperacion');

        $reporte = TbReporteRegulatorioPLD::firstOrNew([
            'IDRegistroAlerta' => $alerta->IDRegistroAlerta,
        ]);

        $reporte->TipoReporte         = $tipoReporteNombre;
        $reporte->IDTipoReporte       = $idTipoReporte;
        // Periodo: AAAAMM para relevantes y AAAAMMDD para inusuales/preocupantes.
        $reporte->PeriodoReporte      = $idTipoReporte === 1
            ? now()->format('Ym')
            : now()->format('Ymd');
        $reporte->Folio               = $alerta->Folio;
        $reporte->OrganoSupervisor    = '001003';
        $reporte->CveSujetoObligado   = '022123';
        $reporte->Localidad           = '03342009';
        $reporte->Sucursal            = '0';
        $reporte->TipoOperacion       = $tipoOperacionNombre;
        $reporte->IDTipoOperacion     = 10;
        $reporte->InstrumentoMonetario = $alerta->InstrumentoMonetario;
        $reporte->NoPoliza            = $alerta->Poliza;
        $reporte->Monto               = $alerta->MontoOperacion;
        $reporte->IDMoneda            = $alerta->IDMoneda ?: $operacion?->IDMoneda;
        $reporte->FechaOperacion      = $alerta->FechaOperacion ?? $hoy;
        $reporte->FechaDeteccion      = $alerta->FechaDeteccion ?? $hoy;
        $reporte->Nacionalidad        = $cliente?->IDNacionalidad
            ? CatNacionalidad::where('IDNacionalidad', $cliente->IDNacionalidad)->value('Nacionalidad')
            : null;
        $reporte->TipoPersona         = $cliente?->IDTipoPersona
            ? CatTipoPersona::where('IDTipoPersona', $cliente->IDTipoPersona)->value('TipoPersona')
            : null;
        $reporte->RazonSocial         = $cliente?->RazonSocial;
        $reporte->Nombre              = $cliente?->Nombre ?? $alerta->Cliente;
        $reporte->APaterno            = $cliente?->ApellidoPaterno;
        $reporte->AMaterno            = $cliente?->ApellidoMaterno;
        $reporte->RFC                 = $cliente?->RFC ?? $alerta->RFCAgente;
        $reporte->CURP                = $cliente?->CURP;
        $reporte->FechaNacimiento     = $cliente?->FechaNacimiento;
        $reporte->Domicilio           = $domicilio;
        $reporte->Colonia             = $colonia;
        $reporte->Ciudad              = $ciudad;
        $reporte->Telefono            = $telefono;
        $reporte->Ocupacion           = $cliente?->IDOcupacionGiro
            ? CatOcupacionesGiros::where('IDOcupacionGiro', $cliente->IDOcupacionGiro)->value('OcupacionGiro')
            : null;
        $reporte->NombreAgente        = $alerta->Agente;
        $reporte->RFCAgente           = $alerta->RFCAgente;
        $reporte->Descripcion         = $alerta->Descripcion;
        $reporte->Razon               = $alerta->Razones;
        $reporte->Estatus             = 'Enviado';

        $reporte->save();

        return $reporte;
    }

    /**
     * Devuelve [IDTipoReporte, nombre] a partir del patrón de la alerta.
     */
    private function mapearTipoReporte(?string $patron): array
    {
        $id = match ($patron) {
            'Relevante'   => 1,
            'Preocupante' => 3,
            default       => 2,
        };

        return [$id, CatTipoReporte::where('IDTipoReporte', $id)->value('TipoReporte')];
    }
}
