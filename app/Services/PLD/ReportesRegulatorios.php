<?php

namespace App\Services\PLD;

use App\Models\CatTipoOperacion;
use App\Models\CatTipoReporte;
use App\Models\Clientes\CatNacionalidad;
use App\Models\Clientes\CatOcupacionesGiros;
use App\Models\Clientes\CatTipoPersona;
use App\Models\Clientes\TbClientesDomicilio;
use App\Models\TbReporteRegulatorioPLD;

class ReportesRegulatorios
{
    /**
     * Inserta un nuevo reporte regulatorio en la base de datos.
     *
     * @param array $data Los datos del reporte regulatorio. Deben corresponder a los campos fillable del modelo.
     * @return TbReporteRegulatorioPLD El modelo insertado.
     * @throws \Exception Si ocurre un error al guardar.
     */
    /**
     * Inserta un nuevo reporte regulatorio en la base de datos.
     *
     * @param $operacion La operación relacionada.
     * @param $cliente El cliente involucrado.
     * @param $alertaData Datos de la alerta generada.
     * @param $evidencias Evidencias generadas por el análisis.
     * @param $pagosOperacion Lista de pagos relacionados.
     * @param $resultadoAnalisis Resultado del análisis de pagos.
     * @return TbReporteRegulatorioPLD El modelo insertado.
     */
    public function insertarReporte($operacion, $cliente, $alertaData, $evidencias, $pagosOperacion, $resultadoAnalisis): ?TbReporteRegulatorioPLD
    {
        // Solo insertar si es por monto relevante
        if (!$resultadoAnalisis->esMontoRelevante) {
            return null;
        }

        $fechaAAAAMM = date('Y-m');
        $folio = str_pad($alertaData['IDRegistroAlerta'], 6, "0", STR_PAD_LEFT);
        $tipoReporte = 1; // Solo relevante
        $nacionalidad = CatNacionalidad::where('IDNacionalidad', $cliente->IDNacionalidad)->first()?->Nacionalidad ?? null;
        $tipoPersona = CatTipoPersona::where('IDTipoPersona', $cliente->IDTipoPersona)->first()?->TipoPersona ?? null;
        $domicilio = TbClientesDomicilio::where('IDCliente', $cliente->IDCliente)->first();

        if ($domicilio) {
            $estado = $domicilio->estado; // Relación con CatEstados
            $nombreEstado = $estado ? $estado->Estado : '';

            $partes = [];
            if ($domicilio->Calle) $partes[] = $domicilio->Calle;
            if ($domicilio->NoExterior) $partes[] = 'No. Ext: ' . $domicilio->NoExterior;
            if ($domicilio->NoInterior) $partes[] = 'No. Int: ' . $domicilio->NoInterior;
            // Colonia descartada
            if ($domicilio->Municipio) $partes[] = 'Mun. ' . $domicilio->Municipio;
            if ($domicilio->Localidad) $partes[] = 'Loc. ' . $domicilio->Localidad;
            if ($nombreEstado) $partes[] = $nombreEstado;
            if ($domicilio->CP) $partes[] = 'CP: ' . $domicilio->CP;

            $domicilioProcesadoStr = implode(', ', $partes);
        } else {
            $domicilioProcesadoStr = '';
        }

        $ocupacion = CatOcupacionesGiros::where('IDOcupacionGiro', $cliente->IDOcupacionGiro)->first()->OcupacionGiro;
        $tipoOperacion = CatTipoOperacion::where('IDTipoOperacion', 10)->first()->TipoOperacion;
        $tipoReporteStr = CatTipoReporte::where('IDTipoReporte', $tipoReporte)->first()->TipoReporte;

        try {
            $reporte = new TbReporteRegulatorioPLD();

            // Mapeo manual de los datos
            $reporte->IDRegistroAlerta      = $alertaData->IDRegistroAlerta  ?? null;
            $reporte->TipoReporte           = $tipoReporteStr                   ?? null;
            $reporte->PeriodoReporte        = $fechaAAAAMM                   ?? null;
            $reporte->Folio                 = $alertaData->Folio                         ?? null;
            $reporte->OrganoSupervisor      = '001003'                       ?? null;
            $reporte->CveSujetoObligado     = '022123'                       ?? null;
            $reporte->Localidad             = '03342009'                     ?? null;
            $reporte->Sucursal              = '0'                            ?? null;
            $reporte->TipoOperacion         = $tipoOperacion                             ?? null;
            $reporte->InstrumentoMonetario  = $alertaData->InstrumentoMonetario  ?? null;
            $reporte->NoPoliza              = $alertaData->Poliza              ?? null;
            $reporte->Monto                 = $alertaData->MontoOperacion           ?? null;
            $reporte->IDMoneda              = $operacion->IDMoneda              ?? null;
            $reporte->FechaOperacion        = $alertaData->FechaOperacion        ?? null;
            $reporte->FechaDeteccion        = $alertaData->FechaDeteccion       ?? null;
            $reporte->Nacionalidad          = $nacionalidad          ?? null;
            $reporte->TipoPersona           = $tipoPersona           ?? null;
            $reporte->RazonSocial           = $cliente->RazonSocial           ?? null;
            $reporte->Nombre                = $cliente->Nombre                ?? null;
            $reporte->APaterno              = $cliente->ApellidoPaterno              ?? null;
            $reporte->AMaterno              = $cliente->ApellidoMaterno              ?? null;
            $reporte->RFC                   = $cliente->RFC                   ?? null;
            $reporte->CURP                  = $cliente->CURP                  ?? null;
            $reporte->FechaNacimiento       = $cliente->FechaNacimiento       ?? null;
            $reporte->Domicilio             = $domicilioProcesadoStr             ?? null;
            $reporte->Colonia               = $domicilio->Colonia               ?? null;
            $reporte->Ciudad                = $domicilio->Municipio                ?? null;
            $reporte->Telefono              = $domicilio->Telefono              ?? null;
            $reporte->Ocupacion             = $ocupacion             ?? null;
            $reporte->NombreAgente          = $operacion->NombreAgente          ?? null;
            $reporte->APaternoAgente        = $operacion->APaternoAgente        ?? null;
            $reporte->AMaternoAgente        = $operacion->AMaternoAgente     ?? null;
            $reporte->RFCAgente             = $operacion->RFCAgente             ?? null;
            $reporte->CURPAgente            = $operacion->CURPAgente          ?? null;
            $reporte->Cuenta                = ''                ?? null;
            $reporte->NoPolizaCuenta        = ''                ?? null;
            $reporte->CveSujetoObl          = ''          ?? null;
            $reporte->NombreTitular         = ''         ?? null;
            $reporte->APaternoTitular       = ''       ?? null;
            $reporte->AMaternoTitular       = ''       ?? null;
            $reporte->Descripcion           = $alertaData->Descripcion           ?? null;
            $reporte->Razon                 = $alertaData->Razones                 ?? null;
            $reporte->Estatus               = $alertaData->Estatus               ?? null;

            // Campos añadidos por la migración
            $reporte->IDTipoReporte         = $tipoReporte         ?? null;
            $reporte->IDTipoOperacion       = 10       ?? null;

            $reporte->save();

            return $reporte;
        } catch (\Exception $e) {
            // Puedes agregar aquí manejo de log, personalización de la excepción, etc.
            throw $e;
        }
    }
}
