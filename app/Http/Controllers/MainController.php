<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

class MainController extends Controller
{
    function index(Request $request) {
        App::setlocale(Auth::user()->lang);
        return view('folder');
    }
}
