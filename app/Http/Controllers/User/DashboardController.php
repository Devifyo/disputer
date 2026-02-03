<?php

namespace App\Http\Controllers\User; // Separated Namespace

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        return view('user.dashboard'); // Points to separated view folder
    }
}
