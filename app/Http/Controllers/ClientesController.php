<?php

namespace App\Http\Controllers;

use App\Models\Clientes\TbClientes;

class ClientesController extends Controller
{
    //
    public function index()
    {
        $clientes = TbClientes::all();

        return inertia('Clientes/Index', [
            'clientes' => $clientes,
            'toast' => session('toast'),
        ]);
    }
}
