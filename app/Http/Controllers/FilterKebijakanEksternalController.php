<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Services\DocumentFilterService;
use Illuminate\Http\Request;

class FilterKebijakanEksternalController extends Controller
{
    protected $documentFilterService;

    public function __construct(DocumentFilterService $documentFilterService)
    {
        $this->documentFilterService = $documentFilterService;
    }

    /**
     * Display a listing of filtered and sorted data.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Build query with filters
        $query = LegalDocument::whereIn('document_type', [
            'Undang-Undang',
            'Peraturan Pemerintah',
            'Peraturan Presiden',
            'Peraturan Menteri',
        ])
            ->where(function ($q) {
                $q->whereJsonDoesntContain('metadata->agency', 'Kementerian Luar Negeri')
                    ->orWhereNull('metadata->agency');
            });

        $query = $this->documentFilterService->apply($request, $query);

        // Paginate the filtered and sorted results
        $kebijakan = $query->paginate(10);

        // Return view with filtered and sorted data
        return view('kebijakan_eksternal', [
            'title' => 'Kebijakan TIK Eksternal',
            'kebijakan' => $kebijakan,
        ]);
    }
}
