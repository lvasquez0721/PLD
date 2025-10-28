<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class ParametriaPLDController extends Controller
{
    public function index()
    {
        return Inertia::render('ParametriaPLD/Index');
    }
}