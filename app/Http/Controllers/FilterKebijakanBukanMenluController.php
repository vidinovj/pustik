<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;

class FilterKebijakanBukanMenluController extends Controller
{
    /**
     * Display a listing of filtered and sorted data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Retrieve query parameters for filtering and sorting
        $filters = $request->only(['jenis_kebijakan', 'nomor_kebijakan', 'tahun_penerbitan_min', 'tahun_penerbitan_max', 'perihal_kebijakan', 'instansi', 'sort_by', 'sort_order']);

        // Build query with filters
        $query = LegalDocument::whereIn('document_type', [
            'Undang-Undang',
            'Peraturan Pemerintah',
            'Peraturan Presiden',
            'Peraturan Menteri'
        ])->whereJsonDoesntContain('metadata->agency', 'Kementerian Luar Negeri');

        if (!empty($filters['jenis_kebijakan'])) {
            // This filter is now handled by the initial where clause on document_type
            // If you need to filter by original jenis_kebijakan, you'd query metadata JSON
        }
        if (!empty($filters['nomor_kebijakan'])) {
            $query->where('document_number', 'like', '%' . $filters['nomor_kebijakan'] . '%');
        }
        if (!empty($filters['tahun_penerbitan_min'])) {
            $query->whereYear('issue_date', '>=', $filters['tahun_penerbitan_min']);
        }
        if (!empty($filters['tahun_penerbitan_max'])) {
            $query->whereYear('issue_date', '<=', $filters['tahun_penerbitan_max']);
        }
        if (!empty($filters['perihal_kebijakan'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', '%' . $filters['perihal_kebijakan'] . '%')
                  ->orWhere('full_text', 'like', '%' . $filters['perihal_kebijakan'] . '%');
            });
        }
        if (!empty($filters['instansi'])) {
            $query->where('metadata->agency', 'like', '%' . $filters['instansi'] . '%');
        }

        // Apply sorting if specified
        if (!empty($filters['sort_by']) && in_array($filters['sort_by'], ['nomor_kebijakan', 'tahun_penerbitan'])) {
            $sortOrder = $filters['sort_order'] ?? 'asc'; // Default to ascending order
            $sortByColumn = $filters['sort_by'];
            if ($sortByColumn === 'nomor_kebijakan') {
                $sortByColumn = 'document_number';
            } elseif ($sortByColumn === 'tahun_penerbitan') {
                $sortByColumn = 'issue_date';
            }
            $query->orderBy($sortByColumn, $sortOrder);
        }

        // Paginate the filtered and sorted results
        $kebijakan = $query->paginate(10);

        // Return view with filtered and sorted data
        return view('ktbnk', [
            'title' => 'Kebijakan TIK Bukan Kemlu',
            'kebijakan' => $kebijakan
        ]);
    }
}
