<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;
use App\Models\TbAlertas;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function dashboard()
    {
        // Contar alertas y clientes
        $totalAlertas = TbAlertas::count();
        $alertasHoy = TbAlertas::whereDate('created_at', Carbon::today())->count();

        // Recuento por estatus de alertas
        $estatusPosibles = [
            'Generado',
            'Analizado',
            'Cerrado',
            'Reportado',
            'Enviado',
        ];
        $alertasPorEstatus = [];
        foreach ($estatusPosibles as $estatus) {
            $alertasPorEstatus[$estatus] = TbAlertas::where('Estatus', $estatus)->count();
        }
        $alertasAbiertas = TbAlertas::where('Estatus', '!=', 'Cerrado')->count();

        // Últimas alertas destacadas (condensado)
        $ultimasAlertas = TbAlertas::orderBy('created_at', 'desc')
            ->take(5)
            ->get(['IDRegistroAlerta', 'Cliente', 'Descripcion', 'created_at', 'Estatus']);

        // Clientes
        $cantClientes = TbClientes::count();

        // Variables adicionales solicitadas sobre clientes
        $cantClientesActivos = TbClientes::where('Activo', true)->count();
        $cantClientesNacionalidadMX = TbClientes::where('IDNacionalidad', 'MX')->count();
        $cantClientesExtranjeros = TbClientes::where('IDNacionalidad', '!=', 'MX')->count();
        $cantClientesPPE = TbClientes::where('EsPPEActivo', true)->count();



        return Inertia::render('Dashboard', [
            'totalAlertas' => $totalAlertas,
            'alertasHoy' => $alertasHoy,
            'alertasPorEstatus' => $alertasPorEstatus,
            'alertasAbiertas' => $alertasAbiertas,
            'ultimasAlertas' => $ultimasAlertas,

            'cantClientes' => $cantClientes,
            'cantClientesActivos' => $cantClientesActivos,
            'cantClientesNacionalidadMX' => $cantClientesNacionalidadMX,
            'cantClientesExtranjeros' => $cantClientesExtranjeros,
            'cantClientesPPE' => $cantClientesPPE,
        ]);
    }
}
