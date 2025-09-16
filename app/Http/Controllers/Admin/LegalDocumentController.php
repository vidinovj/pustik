<?php

// app/Http/Controllers/Admin/LegalDocumentController.php - ENHANCED VERSION

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentSource;
use App\Models\LegalDocument;
use App\Services\DocumentFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LegalDocumentController extends Controller
{
    protected $documentFilterService;

    public function __construct(DocumentFilterService $documentFilterService)
    {
        $this->documentFilterService = $documentFilterService;
    }

    public function index(Request $request)
    {
        $query = LegalDocument::with('documentSource')->latest();

        $query = $this->documentFilterService->apply($request, $query);

        $legal_documents = $query->paginate(15);

        return view('admin.legal-documents.index', compact('legal_documents'));
    }

    public function create()
    {
        $documentSources = DocumentSource::where('is_active', true)->get();

        return view('admin.legal-documents.create', compact('documentSources'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'issue_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 1),
            'document_source_id' => 'required|exists:document_sources,id',
            'source_url' => 'nullable|url|max:500',
            'full_text' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,pending,draft',
            'document_type_code' => 'nullable|string|max:255',
            'metadata.tanggal_berakhir' => 'nullable|date',

            // File upload validation
            'document_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240', // 10MB max
        ], [
            'document_file.mimes' => 'Only PDF, DOC, and DOCX files are allowed.',
            'document_file.max' => 'File size cannot exceed 10MB.',
            'document_source_id.required' => 'Please select a document source.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Must have either file upload OR source URL
        if (! $request->hasFile('document_file') && empty($request->source_url)) {
            return redirect()->back()
                ->withErrors(['document_file' => 'Please upload a file or provide a source URL.'])
                ->withInput();
        }

        $data = $request->only([
            'title', 'document_type', 'document_number', 'issue_year',
            'document_source_id', 'source_url', 'full_text', 'status', 'document_type_code', 'metadata',
        ]);

        // Handle file upload
        if ($request->hasFile('document_file')) {
            $file = $request->file('document_file');

            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'_'.time().'.'.$extension;

            // Store file in storage/app/documents
            $filePath = $file->storeAs('documents', $filename, 'local');

            // Add file data
            $data['file_path'] = $filePath;
            $data['file_name'] = $originalName;
            $data['file_type'] = strtolower($extension);
            $data['file_size'] = $file->getSize();
            $data['uploaded_by'] = Auth::user()->name ?? 'System';
            $data['uploaded_at'] = now();

            // For PDF files, set pdf_url to our file serve route
            if (strtolower($extension) === 'pdf') {
                $data['pdf_url'] = null; // Will be set after document is created
            }
        }

        // Generate checksum
        $data['checksum'] = md5($data['title'].$data['document_type'].$data['document_number'].$data['issue_year']);

        $document = LegalDocument::create($data);

        // Set pdf_url for uploaded PDF files
        if (isset($data['file_path']) && $data['file_type'] === 'pdf') {
            $document->update([
                'pdf_url' => route('documents.serve-file', $document->id),
            ]);
        }

        return redirect()->route('admin.legal-documents.index')
            ->with('success', 'Document created successfully.');
    }

    public function show(LegalDocument $legal_document)
    {
        $legal_document->load('documentSource');

        return view('admin.legal-documents.show', ['document' => $legal_document]);
    }

    public function edit(LegalDocument $legal_document)
    {
        $documentSources = DocumentSource::where('is_active', true)->get();

        return view('admin.legal-documents.edit', [
            'document' => $legal_document,
            'documentSources' => $documentSources,
        ]);
    }

    public function update(Request $request, LegalDocument $legal_document)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'document_type' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:255',
            'issue_year' => 'nullable|integer|min:1900|max:'.(date('Y') + 1),
            'document_source_id' => 'required|exists:document_sources,id',
            'source_url' => 'nullable|url|max:500',
            'full_text' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,pending,draft',
            'document_type_code' => 'nullable|string|max:255',
            'metadata.tanggal_berakhir' => 'nullable|date',

            // File upload validation for updates
            'document_file' => 'nullable|file|mimes:pdf,doc,docx|max:10240',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->only([
            'title', 'document_type', 'document_number', 'issue_year',
            'document_source_id', 'source_url', 'full_text', 'status', 'document_type_code', 'metadata',
        ]);

        // Handle new file upload (replaces existing file)
        if ($request->hasFile('document_file')) {
            // Delete old file if exists
            if ($legal_document->file_path && Storage::disk('local')->exists($legal_document->file_path)) {
                Storage::disk('local')->delete($legal_document->file_path);
            }

            $file = $request->file('document_file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)).'_'.time().'.'.$extension;

            $filePath = $file->storeAs('documents', $filename, 'local');

            $data['file_path'] = $filePath;
            $data['file_name'] = $originalName;
            $data['file_type'] = strtolower($extension);
            $data['file_size'] = $file->getSize();
            $data['uploaded_by'] = Auth::user()->name ?? 'System';
            $data['uploaded_at'] = now();

            // Update pdf_url for PDF files
            if (strtolower($extension) === 'pdf') {
                $data['pdf_url'] = route('documents.serve-file', $legal_document->id);
            } else {
                $data['pdf_url'] = null;
            }
        }

        // Update checksum
        $data['checksum'] = md5($data['title'].$data['document_type'].$data['document_number'].$data['issue_year']);

        $legal_document->update($data);

        return redirect()->route('admin.legal-documents.index')
            ->with('success', 'Document updated successfully.');
    }

    public function destroy(LegalDocument $legal_document)
    {
        // Delete associated file
        if ($legal_document->file_path && Storage::disk('local')->exists($legal_document->file_path)) {
            Storage::disk('local')->delete($legal_document->file_path);
        }

        $legal_document->delete();

        return redirect()->route('admin.legal-documents.index')
            ->with('success', 'Document deleted successfully.');
    }
}
