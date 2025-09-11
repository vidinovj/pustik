<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;

class PusdatinController extends Controller
{
    public function index(Request $request)
    {
        $query = LegalDocument::whereIn('document_type', ['Nota Kesepahaman - MOU', 'Nota Kesepahaman - PKS', 'Dokumen Lainnya']);

        // Filter berdasarkan jenis dokumen
        if ($request->filled('jenis_dokumen')) {
            $query->where('document_type', 'like', '%' . $request->jenis_dokumen . '%');
        }

        // Filter berdasarkan perihal dokumen
        if ($request->filled('perihal_dokumen')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->perihal_dokumen . '%')
                  ->orWhere('full_text', 'like', '%' . $request->perihal_dokumen . '%');
            });
        }

        // Filter berdasarkan satker kemlu terkait
        if ($request->filled('satker_kemlu_terkait')) {
            $query->where('metadata->satker_kemlu_terkait', 'like', '%' . $request->satker_kemlu_terkait . '%');
        }

        // Filter berdasarkan K/L/I external terkait
        if ($request->filled('kl_external_terkait')) {
            $query->where('metadata->kl_external_terkait', 'like', '%' . $request->kl_external_terkait . '%');
        }

        // Filter berdasarkan rentang tanggal disahkan
        if ($request->filled('start_date_disahkan') && $request->filled('end_date_disahkan')) {
            $query->whereBetween('issue_year', [
                $request->start_date_disahkan,
                $request->end_date_disahkan
            ]);
        }

        // Filter berdasarkan rentang tanggal berakhir
        if ($request->filled('start_date_berakhir') && $request->filled('end_date_berakhir')) {
            $query->whereBetween('metadata->tanggal_berakhir', [
                $request->start_date_berakhir,
                $request->end_date_berakhir
            ]);
        }

        // Sortir berdasarkan kolom tanggal
        if ($request->filled('sort_by')) {
            switch ($request->sort_by) {
                case 'tanggal_disahkan_asc':
                    $query->orderBy('issue_year', 'asc');
                    break;
                case 'tanggal_disahkan_desc':
                    $query->orderBy('issue_year', 'desc');
                    break;
                case 'tanggal_berakhir_asc':
                    $query->orderBy('metadata->tanggal_berakhir', 'asc');
                    break;
                case 'tanggal_berakhir_desc':
                    $query->orderBy('metadata->tanggal_berakhir', 'desc');
                    break;
            }
        }

        // Pagination
        $documents = $query->paginate(10);

        return view('pusdatin', [
            'title' => 'Dokumen Internal Pusdatin',
            'documents' => $documents,
        ]);
    }
}