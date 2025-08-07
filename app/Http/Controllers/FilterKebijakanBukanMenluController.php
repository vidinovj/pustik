<?php

namespace App\Http\Controllers;

use App\Models\KebijakanNonKemlu;
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
        $query = KebijakanNonKemlu::query();

        if (!empty($filters['jenis_kebijakan'])) {
            $query->where('jenis_kebijakan', 'like', '%' . $filters['jenis_kebijakan'] . '%');
        }
        if (!empty($filters['nomor_kebijakan'])) {
            $query->where('nomor_kebijakan', 'like', '%' . $filters['nomor_kebijakan'] . '%');
        }
        if (!empty($filters['tahun_penerbitan_min'])) {
            $query->where('tahun_penerbitan', '>=', $filters['tahun_penerbitan_min']);
        }
        if (!empty($filters['tahun_penerbitan_max'])) {
            $query->where('tahun_penerbitan', '<=', $filters['tahun_penerbitan_max']);
        }
        if (!empty($filters['perihal_kebijakan'])) {
            $query->where('perihal_kebijakan', 'like', '%' . $filters['perihal_kebijakan'] . '%');
        }
        if (!empty($filters['instansi'])) {
            $query->where('instansi', 'like', '%' . $filters['instansi'] . '%');
        }

        // Apply sorting if specified
        if (!empty($filters['sort_by']) && in_array($filters['sort_by'], ['nomor_kebijakan', 'tahun_penerbitan'])) {
            $sortOrder = $filters['sort_order'] ?? 'asc'; // Default to ascending order
            $query->orderBy($filters['sort_by'], $sortOrder);
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
