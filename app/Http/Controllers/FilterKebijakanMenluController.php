<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LegalDocument;

use Illuminate\Support\Facades\Log;

class FilterKebijakanMenluController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Retrieve filter inputs from the request
        $filters = $request->only([
            'jenis_kebijakan', 
            'nomor_kebijakan', 
            'tahun_penerbitan', 
            'perihal_kebijakan',
            'tahun_penerbitan_from',
            'tahun_penerbitan_to',
            'sort_by', 
            'sort_order',
        ]);

        // Start a query builder
        // Filter for 'Peraturan Menteri' where metadata->agency is 'Kementerian Luar Negeri'
        $query = LegalDocument::where('document_type', 'Peraturan Menteri')
                              ->whereJsonContains('metadata->agency', 'Kementerian Luar Negeri');

        // Apply filters
        if (!empty($filters['jenis_kebijakan'])) {
            // This filter is now handled by the initial where clause on document_type
            // If you need to filter by original jenis_kebijakan, you'd query metadata JSON
            // For now, we assume 'Kebijakan TIK Kemlu' is specific enough
        }

        if (!empty($filters['nomor_kebijakan'])) {
            $query->where('document_number', 'like', '%' . $filters['nomor_kebijakan'] . '%');
        }

        if (!empty($filters['tahun_penerbitan'])) {
            $query->where('issue_year', $filters['tahun_penerbitan']);
        }

        if (!empty($filters['tahun_penerbitan_from']) && !empty($filters['tahun_penerbitan_to'])) {
            $query->whereBetween('issue_year', [
                $filters['tahun_penerbitan_from'], 
                $filters['tahun_penerbitan_to']
            ]);
        }

        if (!empty($filters['perihal_kebijakan'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['perihal_kebijakan'] . '%')
                  ->orWhere('full_text', 'like', '%' . $filters['perihal_kebijakan'] . '%');
            });
        }

        // Apply sorting
        if (!empty($filters['sort_by']) && !empty($filters['sort_order'])) {
            $sortByColumn = $filters['sort_by'];
            Log::info('Original sort_by: ' . $sortByColumn);
            if ($sortByColumn === 'nomor_kebijakan') {
                $sortByColumn = 'document_number';
            } elseif ($sortByColumn === 'tahun_penerbitan') {
                $sortByColumn = 'issue_year';
            }
            Log::info('Mapped sort_by: ' . $sortByColumn);
            Log::info('Sort order: ' . $filters['sort_order']);
            $query->orderBy($sortByColumn, $filters['sort_order']);
        }

        // Paginate the filtered results
        $kebijakan = $query->paginate(10);

        // Pass filters back to the view for better UX
        return view('ktbk', [
            'kebijakan' => $kebijakan,
            'filters' => $filters,
        ]);
    }
}
