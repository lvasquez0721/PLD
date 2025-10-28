<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ReporteOperacionesController extends Controller
{
    public function index()
    {
        return Inertia::render('ReporteOperaciones/Index');
    }
}