<?php

namespace App\Http\Controllers;

use App\Models\BuzonPreocupante;
use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use App\Models\TbPerfilTransaccional;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function dashboard()
    {
        // --- Alertas ---
        $totalAlertas = TbAlertas::count();
        $alertasHoy = TbAlertas::whereDate('created_at', Carbon::today())->count();
        $alertasAbiertas = TbAlertas::where('Estatus', '!=', 'Cerrado')->count();

        $estatusPosibles = ['Generado', 'Analizado', 'Cerrado', 'Reportado', 'Enviado'];
        $alertasPorEstatus = [];
        foreach ($estatusPosibles as $estatus) {
            $alertasPorEstatus[$estatus] = TbAlertas::where('Estatus', $estatus)->count();
        }

        // Alertas por patrón PLD (Relevante, Inusual, Preocupante)
        $patronesPLD = ['Relevante', 'Inusual', 'Preocupante'];
        $alertasPorPatron = [];
        foreach ($patronesPLD as $patron) {
            $alertasPorPatron[$patron] = TbAlertas::where('Patron', $patron)->count();
        }

        // Alertas regulatorias pendientes de reporte
        $alertasPorReportar = TbAlertas::whereIn('Patron', $patronesPLD)
            ->where('Estatus', 'Por reportar')
            ->count();

        // Últimas 8 alertas
        $ultimasAlertas = TbAlertas::orderBy('created_at', 'desc')
            ->take(8)
            ->get(['IDRegistroAlerta', 'Cliente', 'Patron', 'Descripcion', 'created_at', 'Estatus']);

        // --- Buzón de Preocupantes ---
        $buzonPendiente = BuzonPreocupante::whereNull('Estatus')->count();

        // --- Clientes ---
        $cantClientes = TbClientes::count();
        $cantClientesActivos = TbClientes::where('Activo', true)->count();
        $cantClientesNacionalidadMX = TbClientes::where('IDNacionalidad', 'MX')->count();
        $cantClientesExtranjeros = TbClientes::where('IDNacionalidad', '!=', 'MX')->count();
        $cantClientesPPE = TbClientes::where('EsPPEActivo', true)->count();
        $cantClientesEnListaNegra = TbClientes::where('CoincideEnListasNegras', 1)->count();

        // --- Perfiles de riesgo transaccional ---
        $perfilesPorRiesgo = TbPerfilTransaccional::selectRaw('Perfil, COUNT(*) as total')
            ->whereNotNull('Perfil')
            ->groupBy('Perfil')
            ->pluck('total', 'Perfil')
            ->toArray();

        return Inertia::render('Dashboard', [
            // Alertas
            'totalAlertas'        => $totalAlertas,
            'alertasHoy'          => $alertasHoy,
            'alertasAbiertas'     => $alertasAbiertas,
            'alertasPorEstatus'   => $alertasPorEstatus,
            'alertasPorPatron'    => $alertasPorPatron,
            'alertasPorReportar'  => $alertasPorReportar,
            'ultimasAlertas'      => $ultimasAlertas,
            // Buzón
            'buzonPendiente'      => $buzonPendiente,
            // Clientes
            'cantClientes'               => $cantClientes,
            'cantClientesActivos'        => $cantClientesActivos,
            'cantClientesNacionalidadMX' => $cantClientesNacionalidadMX,
            'cantClientesExtranjeros'    => $cantClientesExtranjeros,
            'cantClientesPPE'            => $cantClientesPPE,
            'cantClientesEnListaNegra'   => $cantClientesEnListaNegra,
            // Perfiles
            'perfilesPorRiesgo'   => $perfilesPorRiesgo,
        ]);
    }
}
