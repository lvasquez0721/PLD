<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ListaNegraController extends Controller
{
    public function index()
    {
        return Inertia::render('ListaNegra/Index');
    }
}