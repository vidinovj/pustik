<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\KebijakanTikKemlu;

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
        $query = KebijakanTikKemlu::query();

        // Apply filters
        if (!empty($filters['jenis_kebijakan'])) {
            $query->where('jenis_kebijakan', 'like', '%' . $filters['jenis_kebijakan'] . '%');
        }

        if (!empty($filters['nomor_kebijakan'])) {
            $query->where('nomor_kebijakan', 'like', '%' . $filters['nomor_kebijakan'] . '%');
        }

        if (!empty($filters['tahun_penerbitan'])) {
            $query->where('tahun_penerbitan', $filters['tahun_penerbitan']);
        }

        if (!empty($filters['tahun_penerbitan_from']) && !empty($filters['tahun_penerbitan_to'])) {
            $query->whereBetween('tahun_penerbitan', [
                $filters['tahun_penerbitan_from'], 
                $filters['tahun_penerbitan_to']
            ]);
        }

        // Apply sorting
        if (!empty($filters['sort_by']) && !empty($filters['sort_order'])) {
            $query->orderBy($filters['sort_by'], $filters['sort_order']);
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
