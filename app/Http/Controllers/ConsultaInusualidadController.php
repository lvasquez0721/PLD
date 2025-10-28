<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ConsultaInusualidadController extends Controller
{
    public function index()
    {
        return Inertia::render('ConsultaInusualidad/Index');
    }
}