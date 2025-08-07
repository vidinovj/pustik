<?php
// app/Http/Controllers/Admin/DashboardController.php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KebijakanTikKemlu;
use App\Models\KebijakanTikNonKemlu;
use App\Models\NotaKesepahaman;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'title' => 'Dashboard Admin',
            'total_kebijakan_kemlu' => KebijakanTikKemlu::count(),
            'total_kebijakan_non_kemlu' => KebijakanTikNonKemlu::count(),
            'total_nota_kesepahaman' => NotaKesepahaman::count(),
        ];

        return view('dashboard', $data);
    }
}
