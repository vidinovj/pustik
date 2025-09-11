<?php
// app/Http/Controllers/PusdatinController.php - UPDATED FOR FILE UPLOADS

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;

class PusdatinController extends Controller
{
    public function index(Request $request)
    {
        $query = LegalDocument::query()
            ->where('status', 'active')
            // Filter for internal documents - documents with uploaded files OR specific document types
            ->where(function($q) {
                $q->whereNotNull('file_path') // Has uploaded file
                  ->orWhereIn('document_type', [
                      'Nota Kesepahaman - MOU', 
                      'Nota Kesepahaman - PKS', 
                      'Dokumen Lainnya',
                      'MoU',
                      'PKS',
                      'Agreement',
                      'Surat Edaran',
                      'Pedoman',
                      'SOP'
                  ]);
            })
            // Optionally filter by document source type 'manual' for uploaded docs
            ->where(function($q) {
                $q->whereHas('documentSource', function($subQ) {
                    $subQ->where('type', 'manual');
                })
                ->orWhereIn('document_type', [
                    'Nota Kesepahaman - MOU', 
                    'Nota Kesepahaman - PKS', 
                    'Dokumen Lainnya'
                ]);
            });

        // Apply existing filters
        if ($request->filled('jenis_dokumen')) {
            $query->where('document_type', 'like', '%' . $request->jenis_dokumen . '%');
        }

        if ($request->filled('perihal_dokumen')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->perihal_dokumen . '%')
                  ->orWhere('full_text', 'like', '%' . $request->perihal_dokumen . '%');
            });
        }

        if ($request->filled('satker_kemlu_terkait')) {
            $query->where('metadata->satker_kemlu_terkait', 'like', '%' . $request->satker_kemlu_terkait . '%');
        }

        if ($request->filled('kl_external_terkait')) {
            $query->where('metadata->kl_external_terkait', 'like', '%' . $request->kl_external_terkait . '%');
        }

        if ($request->filled('start_date_disahkan') && $request->filled('end_date_disahkan')) {
            $query->whereBetween('issue_year', [
                $request->start_date_disahkan,
                $request->end_date_disahkan
            ]);
        }

        if ($request->filled('start_date_berakhir') && $request->filled('end_date_berakhir')) {
            $query->whereBetween('metadata->tanggal_berakhir', [
                $request->start_date_berakhir,
                $request->end_date_berakhir
            ]);
        }

        // Enhanced sorting - include uploaded_at for internal documents
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
                case 'upload_terbaru':
                    $query->orderBy('uploaded_at', 'desc')->orderBy('updated_at', 'desc');
                    break;
            }
        } else {
            // Default: uploaded documents first (by upload date), then others by updated date
            $query->orderByRaw('uploaded_at IS NULL ASC')
                  ->orderBy('uploaded_at', 'desc')
                  ->orderBy('updated_at', 'desc');
        }

        // Pagination with relationships
        $documents = $query->with(['documentSource'])->paginate(15);

        // Enhanced statistics
        $stats = [
            'total_documents' => $documents->total(),
            'total_uploaded' => LegalDocument::whereNotNull('file_path')->count(),
            'total_mou' => LegalDocument::where('document_type', 'like', '%MoU%')->count(),
            'total_pks' => LegalDocument::where('document_type', 'like', '%PKS%')->count(),
            'this_year_uploaded' => LegalDocument::whereNotNull('file_path')
                ->whereYear('uploaded_at', date('Y'))->count(),
        ];

        return view('pusdatin', [
            'title' => 'Dokumen Internal Pusdatin',
            'documents' => $documents,
            'stats' => $stats
        ]);
    }
}