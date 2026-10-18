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
use App\Support\Curp;

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
            // Teléfonos: unir distintos con "/" según layout col.27 (si se cuenta con info)
            $tels = $cliente->domicilios
                ->map(fn ($dom) => trim((string) ($dom->Telefono ?? '')))
                ->filter(fn ($t) => $t !== '')
                ->unique()
                ->values()
                ->all();
            $telefono = $tels ? implode('/', $tels) : null;
        }

        $hoy = now()->format('Y-m-d');

        [$idTipoReporte, $tipoReporteNombre] = $this->mapearTipoReporte($alerta->Patron);

        $tipoOperacionNombre = CatTipoOperacion::where('IDTipoOperacion', 10)->value('TipoOperacion');

        $reporte = TbReporteRegulatorioPLD::firstOrNew([
            'IDRegistroAlerta' => $alerta->IDRegistroAlerta,
        ]);

        $reporte->TipoReporte         = $tipoReporteNombre;
        $reporte->IDTipoReporte       = $idTipoReporte;
        // Periodo: fecha de generación del reporte (hoy), igual para todos los registros.
        // Monto usa YYYYMM; Monto Inusual/Nuevo usan YYYYMMDD.
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
        // Instrumento: mapear a ID numerico de catFormaPagos si viene texto, fallback 1=Efectivo
        $rawInst = trim((string) ($alerta->InstrumentoMonetario ?? $operacion?->IDFormaPago ?? ''));
        if ($rawInst === '' || $rawInst === null) {
            $rawInst = '1';
        }
        $reporte->InstrumentoMonetario = $rawInst;
        $reporte->NoPoliza            = $alerta->Poliza;
        $reporte->Monto               = $this->resolverMonto($alerta, $operacion);
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
        // Fecha nacimiento/constitucion según #8.
        // En personas físicas se obtiene de la CURP; si no es válida, se usan
        // las fechas capturadas (el alta puede guardar la de nacimiento en
        // FechaConstitucion).
        if ($esPM) {
            $reporte->FechaNacimiento = $cliente?->FechaConstitucion ?? $cliente?->FechaNacimiento;
        } else {
            $reporte->FechaNacimiento = Curp::fechaNacimiento($cliente?->CURP)
                ?? $cliente?->FechaNacimiento
                ?? $cliente?->FechaConstitucion;
        }
        $reporte->Domicilio           = $domicilio;
        $reporte->Colonia             = $colonia;
        $reporte->Ciudad              = $ciudad;
        $reporte->Telefono            = $telefono;
        // Ocupación: guardar la clave CVE_GIRO. Si el ID del catálogo no existe,
        // el valor capturado se conserva como clave numérica para que no se pierda.
        $idOcupacion = $cliente?->IDOcupacionGiro;
        if ($idOcupacion) {
            $cveGiro = CatOcupacionesGiros::where('IDOcupacionGiro', $idOcupacion)->value('CVE_GIRO');
            if ($cveGiro === null) {
                $cveGiro = is_numeric($idOcupacion)
                    ? str_pad((string) $idOcupacion, 7, '0', STR_PAD_LEFT)
                    : (string) $idOcupacion;
            }
            $reporte->Ocupacion = $cveGiro;
        } else {
            $reporte->Ocupacion = null;
        }
        // Agente: priorizar desglose de tbOperaciones, fallback split del string completo (col.29-31)
        [$nomAg, $patAg, $matAg] = $this->fraccionarAgente(
            $alerta->Agente,
            $operacion?->NombreAgente,
            $operacion?->APaternoAgente,
            $operacion?->AMaternoAgente,
            $operacion?->RazonSocialAgente
        );
        $reporte->NombreAgente        = $nomAg ?: null;
        $reporte->APaternoAgente      = $patAg ?: null;
        $reporte->AMaternoAgente      = $matAg ?: null;
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
     * Monto a reportar (col.11). Regla cancelación: si la alerta trae monto
     * vacío/cero y la operación es cancelación, usar PrimaTotal de la póliza
     * a la que pertenece el endoso de cancelación (emisión con mismo FolioPoliza).
     */
    private function resolverMonto(TbAlertas $alerta, ?TbOperaciones $operacion)
    {
        $monto = $alerta->MontoOperacion;
        if ($monto !== null && (float) $monto != 0.0) {
            return $monto;
        }

        $esCancel = $operacion
            && ((bool) ($operacion->operacionCancelada ?? false)
                || (bool) ($operacion->EsEndosoCancelacion ?? false)
                || (bool) ($operacion->cancelaPoliza ?? false));

        if (! $esCancel || ! $operacion || empty($operacion->FolioPoliza)) {
            return $monto;
        }

        // 1) Emisión de la misma póliza (FolioEndoso vacío)
        $padre = TbOperaciones::where('FolioPoliza', $operacion->FolioPoliza)
            ->where(function ($q) {
                $q->whereNull('FolioEndoso')->orWhere('FolioEndoso', '');
            })
            ->orderBy('IDOperacion')
            ->first();

        if ($padre && $padre->PrimaTotal !== null && (float) $padre->PrimaTotal != 0.0) {
            return $padre->PrimaTotal;
        }

        // 2) Mayor PrimaTotal de la póliza excluyendo el endoso actual
        $max = TbOperaciones::where('FolioPoliza', $operacion->FolioPoliza)
            ->where('IDOperacion', '!=', $operacion->IDOperacion)
            ->max('PrimaTotal');

        if ($max !== null && (float) $max != 0.0) {
            return $max;
        }

        // 3) Fallback: PrimaTotal del propio endoso
        return $operacion->PrimaTotal ?? $monto;
    }

    private function fraccionarAgente($full = null, $nombre = null, $paterno = null, $materno = null, $razonSocial = null): array
    {
        $razonSocial = trim((string) ($razonSocial ?? ''));
        $nombre = trim((string) ($nombre ?? ''));
        $paterno = trim((string) ($paterno ?? ''));
        $materno = trim((string) ($materno ?? ''));
        $full = trim(preg_replace('/\s+/', ' ', (string) ($full ?? '')));

        if ($razonSocial !== '') {
            return [$razonSocial, '', ''];
        }

        if ($nombre !== '' && ($paterno !== '' || $materno !== '')) {
            if ($nombre !== '' && $paterno === '' && $materno !== '') {
                $paterno = 'XXXX';
            }
            return [$nombre, $paterno, $materno];
        }

        $base = $nombre !== '' ? $nombre : $full;
        if ($base === '') {
            return ['', '', ''];
        }

        $tokens = preg_split('/\s+/', $base, -1, PREG_SPLIT_NO_EMPTY);
        if (count($tokens) >= 3) {
            $mat = array_pop($tokens);
            $pat = array_pop($tokens);
            return [implode(' ', $tokens), $pat, $mat];
        }
        if (count($tokens) === 2) {
            return [$tokens[0], $tokens[1], ''];
        }
        return [$tokens[0], 'XXXX', ''];
    }

    /**
     * Devuelve [IDTipoReporte, nombre] a partir del patrón de la alerta.
     */
    private function mapearTipoReporte(?string $patron): array
    {
        $id = match ($patron) {
            'Monto' => 1,
            'Nuevo' => 3,
            default => 2,
        };

        return [$id, CatTipoReporte::where('IDTipoReporte', $id)->value('TipoReporte')];
    }
}
