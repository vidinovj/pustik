<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobStatusController extends Controller
{
    public function index()
    {
        $jobs = DB::table('jobs')->orderBy('id', 'desc')->take(5)->get();

        return response()->json($jobs);
    }
}