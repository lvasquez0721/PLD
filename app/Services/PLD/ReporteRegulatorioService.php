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
            // Ciudad texto segun respuesta #9
            $ciudad   = $d->Municipio ?? $d->Localidad ?? null;
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
        // Periodo: YYMM para relevantes y YYMMDD para inusuales/preocupantes, tomado de FechaDeteccion
        $fechaPeriodo = $alerta->FechaDeteccion ?? $alerta->FechaOperacion ?? $hoy;
        $tsPeriodo = strtotime((string) $fechaPeriodo);
        $reporte->PeriodoReporte      = $idTipoReporte === 1
            ? ($tsPeriodo ? date('ym', $tsPeriodo) : now()->format('ym'))
            : ($tsPeriodo ? date('ymd', $tsPeriodo) : now()->format('ymd'));
        $reporte->Folio               = $alerta->Folio;
        $reporte->OrganoSupervisor    = '001003';
        $reporte->CveSujetoObligado   = '022123';
        $reporte->Localidad           = '03342009';
        $reporte->Sucursal            = '0';
        $reporte->TipoOperacion       = $tipoOperacionNombre;
        $reporte->IDTipoOperacion     = 10;
        // Instrumento: mapear a ID numerico de catFormaPagos si viene texto, fallback 1=Efectivo
        $rawInst = trim((string) ($alerta->InstrumentoMonetario ?? $operacion?->IDFormaPago ?? ''));
        if ($rawInst === '' || $rawInst === null) {
            $rawInst = '1';
        }
        $reporte->InstrumentoMonetario = $rawInst;
        $reporte->NoPoliza            = $alerta->Poliza;
        $reporte->Monto               = $alerta->MontoOperacion;
        // Moneda nunca en blanco: default 001/MXN
        $monedaRaw = $alerta->IDMoneda ?: $operacion?->IDMoneda;
        if (empty($monedaRaw)) {
            $monedaRaw = 'MXN';
        }
        $reporte->IDMoneda            = $monedaRaw;
        $reporte->FechaOperacion      = $alerta->FechaOperacion ?? $hoy;
        $reporte->FechaDeteccion      = $alerta->FechaDeteccion ?? $hoy;
        $reporte->Nacionalidad        = $cliente?->IDNacionalidad
            ? CatNacionalidad::where('IDNacionalidad', $cliente->IDNacionalidad)->value('Nacionalidad')
            : null;
        // Fallback nacionalidad MX si viene vacio
        if (empty($reporte->Nacionalidad)) {
            $reporte->Nacionalidad = $cliente?->IDNacionalidad ? null : 'Mexicana';
            if (empty($reporte->Nacionalidad)) {
                $reporte->Nacionalidad = 'Mexicana';
            }
        }
        // Tipo persona: guardar 1/2 numerico segun IDTipoPersona, coherente con catálogo
        $idTipoPersona = $cliente?->IDTipoPersona;
        if ($idTipoPersona == 1 || $idTipoPersona == 2) {
            $reporte->TipoPersona = (string) $idTipoPersona;
        } else {
            $reporte->TipoPersona = $cliente?->IDTipoPersona
                ? CatTipoPersona::where('IDTipoPersona', $cliente->IDTipoPersona)->value('TipoPersona')
                : null;
            // Normalizar texto a 1/2
            $upper = mb_strtoupper(trim((string) $reporte->TipoPersona));
            if (str_contains($upper, 'FISICA')) {
                $reporte->TipoPersona = '1';
            } elseif (str_contains($upper, 'MORAL')) {
                $reporte->TipoPersona = '2';
            }
        }
        // Coherencia PF/PM: RazonSocial vs Nombre según respuesta #7
        $esPF = ((string) $reporte->TipoPersona === '1');
        $esPM = ((string) $reporte->TipoPersona === '2');
        if ($esPF) {
            $reporte->RazonSocial = null;
            $reporte->Nombre      = $cliente?->Nombre;
            $reporte->APaterno    = $cliente?->ApellidoPaterno;
            $reporte->AMaterno    = $cliente?->ApellidoMaterno;
            $reporte->CURP        = $cliente?->CURP;
        } elseif ($esPM) {
            $reporte->RazonSocial = $cliente?->RazonSocial;
            $reporte->Nombre      = null;
            $reporte->APaterno    = null;
            $reporte->AMaterno    = null;
            $reporte->CURP        = null;
        } else {
            // Sin cliente o tipo desconocido: mantener datos pero limpiar incoherencia
            $reporte->RazonSocial = $cliente?->RazonSocial;
            $reporte->Nombre      = $cliente?->Nombre ?? $alerta->Cliente;
            $reporte->APaterno    = $cliente?->ApellidoPaterno;
            $reporte->AMaterno    = $cliente?->ApellidoMaterno;
            $reporte->CURP        = $cliente?->CURP;
        }
        $reporte->RFC                 = $cliente?->RFC ?? $alerta->RFCAgente;
        // Fecha nacimiento/constitucion según #8
        if ($esPM) {
            $reporte->FechaNacimiento = $cliente?->FechaConstitucion ?? $cliente?->FechaNacimiento;
        } else {
            $reporte->FechaNacimiento = $cliente?->FechaNacimiento;
        }
        $reporte->Domicilio           = $domicilio;
        $reporte->Colonia             = $colonia;
        $reporte->Ciudad              = $ciudad;
        $reporte->Telefono            = $telefono;
        $reporte->Ocupacion           = $cliente?->IDOcupacionGiro
            ? CatOcupacionesGiros::where('IDOcupacionGiro', $cliente->IDOcupacionGiro)->value('OcupacionGiro')
            : null;
        // Si ocupacion viene como nombre, se mapea a CVE en exportar; aqui guardamos texto tal cual
        // Nombre agente tal cual viene de alerta (respuesta #11) - full en col 29, ap. vacíos
        $reporte->NombreAgente        = $alerta->Agente;
        $reporte->APaternoAgente      = null;
        $reporte->AMaternoAgente      = null;
        $reporte->RFCAgente           = $alerta->RFCAgente ?: $operacion?->RFCAgente;
        $reporte->CURPAgente          = $operacion?->CURPAgente;
        // Cuenta vacía si no hay (respuesta #4)
        $reporte->Cuenta              = null;
        $reporte->NoPolizaCuenta      = null;
        $reporte->CveSujetoObl        = null;
        $reporte->NombreTitular       = null;
        $reporte->APaternoTitular     = null;
        $reporte->AMaternoTitular     = null;
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
