<?php

namespace App\Services\ReporteOperaciones;

use Illuminate\Support\Facades\DB;

class ReporteOperacionesService
{
    /**
     * Obtiene los reportes RRPLD según filtros enviados.
     *
     * @param array $filtros
     * @return array
     */
    public function obtenerReporte(array $filtros): array
    {
        $query = DB::table('intranet.tbRRPLD')
            ->select([
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
                'IDRRPLD',
                'Estatus'
            ]);

        // --- Filtros ---
        if (!empty($filtros['export_regulatorio'])) {
            $query->whereIn('Folio', $filtros['reportes'] ?? []);
        } else {
            if (!empty($filtros['estatus'])) {
                $query->where('Estatus', $filtros['estatus']);
            }

            if (!empty($filtros['tipo'])) {
                $query->where('TipoReporte', $filtros['tipo']);
            }

            if (!empty($filtros['fecha_ini']) && !empty($filtros['fecha_fin'])) {
                $query->whereBetween('FechaDeteccion', [$filtros['fecha_ini'], $filtros['fecha_fin']]);
            }
        }

        $resultados = $query->get();

        // --- Formatear respuesta ---
        return $resultados->map(function ($r) {
            return [
                'tipoReporte' => $r->TipoReporte,
                'periodoReporte' => $r->PeriodoReporte,
                'folio' => $r->Folio,
                'organoSupervisor' => $r->OrganoSupervisor,
                'cveSujetoObligado' => $r->CveSujetoObligado,
                'localidad' => $r->Localidad,
                'sucursal' => $r->Sucursal,
                'tipoOperacion' => $r->TipoOperacion,
                'instrumentoMonetario' => $r->InstrumentoMonetario,
                'poliza' => $r->NoPoliza,
                'monto' => $r->Monto,
                'moneda' => $r->Moneda,
                'fechaOperacion' => $r->FechaOperacion,
                'fechaDeteccion' => $r->FechaDeteccion,
                'nacionalidad' => $r->Nacionalidad,
                'tipoPersona' => $r->TipoPersona,
                'razonSocial' => utf8_encode($r->RazonSocial ?? ''),
                'nombre' => utf8_encode($r->Nombre ?? ''),
                'aPaterno' => utf8_encode($r->APaterno ?? ''),
                'aMaterno' => utf8_encode($r->AMaterno ?? ''),
                'rfc' => $r->RFC,
                'curp' => $r->CURP,
                'fechaNacimiento' => $r->FechaNacimiento,
                'domicilio' => utf8_encode($r->Domicilio ?? ''),
                'colonia' => utf8_encode($r->Colonia ?? ''),
                'ciudad' => $r->Ciudad,
                'telefono' => $r->Telefono,
                'ocupacion' => $r->Ocupacion,
                'nombreAgente' => utf8_encode($r->NombreAgente ?? ''),
                'aPaternoAgente' => utf8_encode($r->APaternoAgente ?? ''),
                'aMaternoAgente' => utf8_encode($r->AMaternoAgente ?? ''),
                'rfcAgente' => $r->RFCAgente,
                'curpAgente' => $r->CURPAgente,
                'cuenta' => $r->Cuenta,
                'polizaCuenta' => $r->NoPolizaCuenta,
                'cveSujetoObl' => $r->CveSujetoObl,
                'nombreTitular' => $r->NombreTitular,
                'aPaternoTitular' => $r->APaternoTitular,
                'aMaternoTitular' => $r->AMaternoTitular,
                'descripcion' => utf8_encode($r->Descripcion ?? ''),
                'razon' => utf8_encode($r->Razon ?? ''),
                'IDRRPLD' => $r->IDRRPLD,
                'estatus' => $r->Estatus,
            ];
        })->toArray();
    }
}
