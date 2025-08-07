<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeControllerAwal extends Controller
{
    /**
     * Show the home page with the appropriate title.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $title = "Selamat Datang di Pustik Kemlu"; // Title for the home page

        // Return the home view with the title
        return view('home', compact('title'));
    }
}
