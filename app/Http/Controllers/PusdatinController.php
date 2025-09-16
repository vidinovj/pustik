<?php

// app/Http/Controllers/PusdatinController.php - UPDATED FOR FILE UPLOADS

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Services\DocumentFilterService;
use Illuminate\Http\Request;

class PusdatinController extends Controller
{
    protected $documentFilterService;

    public function __construct(DocumentFilterService $documentFilterService)
    {
        $this->documentFilterService = $documentFilterService;
    }

    public function index(Request $request)
    {
        $query = LegalDocument::query()
            ->where('status', 'active')
            // Filter for internal documents - documents with uploaded files OR specific document types
            ->where(function ($q) {
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
                        'SOP',
                    ]);
            })
            // Optionally filter by document source type 'manual' for uploaded docs
            ->where(function ($q) {
                $q->whereHas('documentSource', function ($subQ) {
                    $subQ->where('type', 'manual');
                })
                    ->orWhereIn('document_type', [
                        'Nota Kesepahaman - MOU',
                        'Nota Kesepahaman - PKS',
                        'Dokumen Lainnya',
                    ]);
            });

        $query = $this->documentFilterService->apply($request, $query);

        // Default sorting if no sort_by is provided
        if (! $request->filled('sort_by')) {
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
            'stats' => $stats,
        ]);
    }
}
