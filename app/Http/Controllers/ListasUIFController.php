<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ListasUIFController extends Controller
{
    public function index()
    {
        return Inertia::render('ListasUIF/Index');
    }
}