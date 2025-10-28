<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BuzonPreocupantesController extends Controller
{
    public function index()
    {
        return Inertia::render('BuzonPreocupantes/Index');
    }
}