<?php

namespace App\Http\Controllers;

use App\Models\CatParametriaPLD;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteadorPLDController extends Controller
{
    public function index()
    {
        $montoAcumulado = CatParametriaPLD::getReporteadorMontoAcumulado();

        return Inertia::render('ReporteadorPLD/Index', [
            'montoAcumulado' => $montoAcumulado,
        ]);
    }

    public function buscar(Request $request)
    {
        $resultados = $this->construirConsulta($request)->get();

        return response()->json(['resultados' => $resultados]);
    }

    public function detalle(Request $request)
    {
        $idCliente = $request->query('noCliente');
        $fecha1    = $request->query('fecha1');
        $fecha2    = $request->query('fecha2');
        $moneda    = $request->query('moneda', '');

        if (! $idCliente) {
            return response()->json(['resultados' => []]);
        }

        $nombreExpr = "CASE WHEN c.IDTipoPersona = 1
            THEN TRIM(CONCAT(COALESCE(c.Nombre,''), ' ', COALESCE(c.ApellidoPaterno,''), ' ', COALESCE(c.ApellidoMaterno,'')))
            ELSE COALESCE(c.RazonSocial,'') END";

        $query = DB::table('tbOperacionesPagos as op')
            ->join('tbOperaciones as o', 'op.IDOperacion', '=', 'o.IDOperacion')
            ->join('tbClientes as c', 'o.IDCliente', '=', 'c.IDCliente')
            ->leftJoin('catFormaPagos as cf', 'op.IDFormaPago', '=', 'cf.IDFormaPago')
            ->leftJoin('catTipoPagos as ct', 'o.IDTipoPago', '=', 'ct.IDTipoPago')
            ->select([
                DB::raw('c.IDCliente as Ncliente'),
                DB::raw("$nombreExpr as Nombre"),
                'o.FolioPoliza as NoPoliza',
                'ct.TipoPago',
                'cf.FormaPago',
                'op.FechaPago',
                DB::raw("CASE WHEN op.IDMoneda = 'MXN' THEN op.Monto ELSE op.Monto * op.TipoCambio END as MontoPagadoMXD"),
                DB::raw("CASE WHEN op.IDMoneda = 'USD' THEN op.Monto ELSE CASE WHEN op.TipoCambio > 0 THEN op.Monto / op.TipoCambio ELSE 0 END END as EquivalentePagadoUSD"),
            ])
            ->where('o.IDCliente', $idCliente);

        if ($fecha1 && $fecha2) {
            $query->whereBetween('op.FechaPago', [$fecha1, $fecha2]);
        }

        if ($moneda === 'MXN') {
            $query->where('op.IDMoneda', 'MXN');
        } elseif ($moneda === 'USD') {
            $query->where('op.IDMoneda', 'USD');
        }

        $resultados = $query->orderBy('op.FechaPago', 'desc')->get();

        return response()->json(['resultados' => $resultados]);
    }

    public function exportarCSV(Request $request)
    {
        $agrupado     = $request->query('agrupado', '0') === '1';
        $monedaLabel  = $this->resolverMonedaLabel($request);
        $montoField   = $monedaLabel === 'USD' ? 'EquivalentePagadoUSD' : 'MontoPagadoMXD';
        $montoHeader  = $monedaLabel === 'USD' ? 'Equivalente USD' : 'Monto MXD';

        $resultados = $this->construirConsulta($request)->get();

        if ($resultados->isEmpty()) {
            return response()->json(['message' => 'No hay datos para exportar'], 404);
        }

        $fileName = 'reporteador_pld_' . date('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ];

        $callback = function () use ($resultados, $agrupado, $montoHeader, $montoField) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if ($agrupado) {
                fputcsv($file, ['No. Cliente', 'Nombre', $montoHeader]);
                foreach ($resultados as $row) {
                    fputcsv($file, [
                        $row->Ncliente,
                        $row->Nombre,
                        number_format((float) $row->$montoField, 2),
                    ]);
                }
            } else {
                fputcsv($file, ['No. Cliente', 'Nombre', 'Póliza', 'Tipo de Pago', 'Forma de Pago', 'Fecha Pago', $montoHeader]);
                foreach ($resultados as $row) {
                    fputcsv($file, [
                        $row->Ncliente,
                        $row->Nombre,
                        $row->NoPoliza  ?? '',
                        $row->TipoPago  ?? '',
                        $row->FormaPago ?? '',
                        $row->FechaPago ?? '',
                        number_format((float) $row->$montoField, 2),
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function resolverMonedaLabel(Request $request): string
    {
        $mxn = $request->input('monedaMXN', '0') === '1';
        $usd = $request->input('monedaUSD', '0') === '1';

        return ($usd && ! $mxn) ? 'USD' : 'MXD';
    }

    private function construirConsulta(Request $request): \Illuminate\Database\Query\Builder
    {
        $agrupado    = $request->input('agrupado', '0') === '1';
        $tipoPersona = $request->input('tipoPersona', '');
        $fechaInicio = $request->input('fechaInicio', '');
        $fechaFin    = $request->input('fechaFin', '');
        $monedaMXN   = $request->input('monedaMXN', '0') === '1';
        $monedaUSD   = $request->input('monedaUSD', '0') === '1';

        $filtros = [
            'usd500'   => $request->input('usd500',   '0') === '1',
            'usd10k'   => $request->input('usd10k',   '0') === '1',
            'usd50k'   => $request->input('usd50k',   '0') === '1',
            'usdAcum'  => $request->input('usdAcum',  '0') === '1',
            'mxn300k'  => $request->input('mxn300k',  '0') === '1',
            'mxn500k'  => $request->input('mxn500k',  '0') === '1',
            'mxnAcum'  => $request->input('mxnAcum',  '0') === '1',
        ];

        $montoAcumUSD = CatParametriaPLD::getReporteadorMontoAcumulado();

        $mxdExpr = "CASE WHEN op.IDMoneda = 'MXN' THEN op.Monto ELSE op.Monto * op.TipoCambio END";
        $usdExpr = "CASE WHEN op.IDMoneda = 'USD' THEN op.Monto ELSE CASE WHEN op.TipoCambio > 0 THEN op.Monto / op.TipoCambio ELSE 0 END END";
        $nombreExpr = "CASE WHEN c.IDTipoPersona = 1
            THEN TRIM(CONCAT(COALESCE(c.Nombre,''), ' ', COALESCE(c.ApellidoPaterno,''), ' ', COALESCE(c.ApellidoMaterno,'')))
            ELSE COALESCE(c.RazonSocial,'') END";

        $query = DB::table('tbOperacionesPagos as op')
            ->join('tbOperaciones as o', 'op.IDOperacion', '=', 'o.IDOperacion')
            ->join('tbClientes as c', 'o.IDCliente', '=', 'c.IDCliente')
            ->leftJoin('catFormaPagos as cf', 'op.IDFormaPago', '=', 'cf.IDFormaPago')
            ->leftJoin('catTipoPagos as ct', 'o.IDTipoPago', '=', 'ct.IDTipoPago');

        // ── Filtros base ─────────────────────────────────────────────────────────

        if ($tipoPersona === '1') {
            $query->where('c.IDTipoPersona', 1);
        } elseif ($tipoPersona === '2') {
            $query->where('c.IDTipoPersona', 2);
        }

        if ($monedaMXN && ! $monedaUSD) {
            $query->where('op.IDMoneda', 'MXN');
        } elseif ($monedaUSD && ! $monedaMXN) {
            $query->where('op.IDMoneda', 'USD');
        }

        if ($fechaInicio && $fechaFin) {
            $query->whereBetween('op.FechaPago', [$fechaInicio, $fechaFin]);
        }

        // ── Umbrales individuales (WHERE) ────────────────────────────────────────

        if ($filtros['usd500']) {
            $query->whereRaw("$usdExpr >= 500");
        }
        if ($filtros['usd10k']) {
            $query->whereRaw("$usdExpr >= 10000");
        }
        if ($filtros['usd50k']) {
            $query->whereRaw("$usdExpr >= 50000");
        }
        if ($filtros['mxn300k']) {
            $query->whereRaw("$mxdExpr >= 300000");
        }
        if ($filtros['mxn500k']) {
            $query->whereRaw("$mxdExpr >= 500000");
        }

        // ── Modo agrupado ────────────────────────────────────────────────────────

        if ($agrupado) {
            $query->select([
                DB::raw('c.IDCliente as Ncliente'),
                DB::raw("$nombreExpr as Nombre"),
                DB::raw("SUM($mxdExpr) as MontoPagadoMXD"),
                DB::raw("SUM($usdExpr) as EquivalentePagadoUSD"),
            ])
            ->groupBy('c.IDCliente', 'c.IDTipoPersona', 'c.Nombre', 'c.ApellidoPaterno', 'c.ApellidoMaterno', 'c.RazonSocial')
            ->orderBy('c.IDCliente');

            if ($filtros['usdAcum']) {
                $query->havingRaw("SUM($usdExpr) >= ?", [$montoAcumUSD]);
            }
            if ($filtros['mxnAcum']) {
                $query->havingRaw("SUM($mxdExpr) >= 1000000");
            }

            return $query;
        }

        // ── Modo individual ──────────────────────────────────────────────────────

        $query->select([
            DB::raw('c.IDCliente as Ncliente'),
            DB::raw("$nombreExpr as Nombre"),
            'o.FolioPoliza as NoPoliza',
            'ct.TipoPago',
            'cf.FormaPago',
            'op.FechaPago',
            DB::raw("$mxdExpr as MontoPagadoMXD"),
            DB::raw("$usdExpr as EquivalentePagadoUSD"),
        ])
        ->orderBy('op.FechaPago', 'desc');

        // Umbrales acumulados en modo individual → subquery por cliente calificado
        if ($filtros['usdAcum']) {
            $acumUSD = $montoAcumUSD;
            $query->whereIn('o.IDCliente', function ($sub) use ($acumUSD, $tipoPersona, $monedaMXN, $monedaUSD, $fechaInicio, $fechaFin) {
                $sub->select('o2.IDCliente')
                    ->from('tbOperacionesPagos as op2')
                    ->join('tbOperaciones as o2', 'op2.IDOperacion', '=', 'o2.IDOperacion')
                    ->join('tbClientes as c2', 'o2.IDCliente', '=', 'c2.IDCliente')
                    ->groupBy('o2.IDCliente');

                if ($tipoPersona === '1') $sub->where('c2.IDTipoPersona', 1);
                elseif ($tipoPersona === '2') $sub->where('c2.IDTipoPersona', 2);
                if ($monedaMXN && ! $monedaUSD) $sub->where('op2.IDMoneda', 'MXN');
                elseif ($monedaUSD && ! $monedaMXN) $sub->where('op2.IDMoneda', 'USD');
                if ($fechaInicio && $fechaFin) $sub->whereBetween('op2.FechaPago', [$fechaInicio, $fechaFin]);

                $sub->havingRaw(
                    "SUM(CASE WHEN op2.IDMoneda = 'USD' THEN op2.Monto ELSE CASE WHEN op2.TipoCambio > 0 THEN op2.Monto / op2.TipoCambio ELSE 0 END END) >= ?",
                    [$acumUSD]
                );
            });
        }

        if ($filtros['mxnAcum']) {
            $query->whereIn('o.IDCliente', function ($sub) use ($tipoPersona, $monedaMXN, $monedaUSD, $fechaInicio, $fechaFin) {
                $sub->select('o2.IDCliente')
                    ->from('tbOperacionesPagos as op2')
                    ->join('tbOperaciones as o2', 'op2.IDOperacion', '=', 'o2.IDOperacion')
                    ->join('tbClientes as c2', 'o2.IDCliente', '=', 'c2.IDCliente')
                    ->groupBy('o2.IDCliente');

                if ($tipoPersona === '1') $sub->where('c2.IDTipoPersona', 1);
                elseif ($tipoPersona === '2') $sub->where('c2.IDTipoPersona', 2);
                if ($monedaMXN && ! $monedaUSD) $sub->where('op2.IDMoneda', 'MXN');
                elseif ($monedaUSD && ! $monedaMXN) $sub->where('op2.IDMoneda', 'USD');
                if ($fechaInicio && $fechaFin) $sub->whereBetween('op2.FechaPago', [$fechaInicio, $fechaFin]);

                $sub->havingRaw(
                    "SUM(CASE WHEN op2.IDMoneda = 'MXN' THEN op2.Monto ELSE op2.Monto * op2.TipoCambio END) >= 1000000"
                );
            });
        }

        return $query;
    }
}
