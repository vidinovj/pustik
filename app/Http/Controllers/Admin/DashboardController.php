<?php

// app/Http/Controllers/Admin/DashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;

class DashboardController extends Controller
{
    public function index()
    {
        $data = [
            'title' => 'Dashboard Admin',
            'total_kebijakan_kemlu' => LegalDocument::where('document_type', 'Kebijakan TIK Kemlu')->count(),
            'total_kebijakan_non_kemlu' => LegalDocument::where('document_type', 'Kebijakan TIK Non Kemlu')->count(),
            'total_nota_kesepahaman' => LegalDocument::where('document_type', 'Nota Kesepahaman')->count(),
        ];

        return view('dashboard', $data);
    }
}
