<?php

namespace App\Http\Controllers;

class HomeControllerAwal extends Controller
{
    /**
     * Show the home page with the appropriate title.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $title = ''; // Title for the home page

        // Return the home view with the title
        return view('home', compact('title'));
    }
}
