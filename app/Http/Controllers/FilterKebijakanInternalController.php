<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Services\DocumentFilterService;
use Illuminate\Http\Request;

class FilterKebijakanInternalController extends Controller
{
    protected $documentFilterService;

    public function __construct(DocumentFilterService $documentFilterService)
    {
        $this->documentFilterService = $documentFilterService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Start a query builder
        // Filter for 'Peraturan Menteri' where metadata->agency is 'Kementerian Luar Negeri'
        $query = LegalDocument::where('document_type', 'Peraturan Menteri')
            ->whereJsonContains('metadata->agency', 'Kementerian Luar Negeri');

        $query = $this->documentFilterService->apply($request, $query);

        // Paginate the filtered results
        $kebijakan = $query->paginate(10);

        // Pass filters back to the view for better UX
        return view('kebijakan_internal', [
            'kebijakan' => $kebijakan,
            'filters' => $request->all(),
        ]);
    }
}
