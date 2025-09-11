<?php
// app/Http/Controllers/DocumentController.php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Display the specified document in a viewer.
     */
    public function show(LegalDocument $document)
    {
        // Check if document is active/published
        if ($document->status !== 'active') {
            abort(404, 'Document not found or not available.');
        }

        // Debug: Check what we're dealing with
        \Log::info('Document data:', [
            'id' => $document->id,
            'title' => $document->title,
            'metadata_type' => gettype($document->metadata),
            'metadata' => $document->metadata,
            'full_text_type' => gettype($document->full_text),
            'full_text_length' => is_string($document->full_text) ? strlen($document->full_text) : 'not_string'
        ]);

        return view('documents.show', [
            'document' => $document,
            'hasFullText' => !empty($document->full_text),
            'hasSourceUrl' => !empty($document->source_url),
            'hasPdfUrl' => !empty($document->pdf_url),
        ]);
    }

    /**
     * Download the document (if available).
     */
    public function download(LegalDocument $document)
    {
        if ($document->status !== 'active') {
            abort(404, 'Document not found or not available.');
        }

        // Priority: Local file > PDF URL > Source URL > Full Text
        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            return response()->download(
                Storage::disk('local')->path($document->file_path), 
                $document->file_name
            );
        }

        if ($document->pdf_url) {
            return redirect($document->pdf_url);
        }

        if ($document->source_url) {
            return redirect($document->source_url);
        }

        // If only full text is available, create a text file
        if ($document->full_text) {
            $filename = $this->generateFilename($document);
            
            return response($document->full_text, 200)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
        }

        abort(404, 'Document content not available for download.');
    }

    /**
     * Get document content via AJAX for modal viewing.
     */
    public function content(LegalDocument $document)
    {
        if ($document->status !== 'active') {
            return response()->json(['error' => 'Document not available'], 404);
        }

        // Process metadata
        $processedMetadata = [];
        if ($document->metadata) {
            foreach ($document->metadata as $key => $value) {
                if (is_array($value)) {
                    $processedMetadata[$key] = implode(', ', $value);
                } else {
                    $processedMetadata[$key] = $value;
                }
            }
        }

        // Process full_text
        $fullText = $document->full_text;
        if (is_array($fullText)) {
            $fullText = implode("\n", $fullText);
        }

        // Determine the best URL for PDF viewing
        $pdfUrl = null;
        if ($document->file_path && $document->file_type === 'pdf') {
            $pdfUrl = route('documents.serve-file', $document->id);
        } elseif ($document->pdf_url) {
            $pdfUrl = $document->pdf_url;
        }

        return response()->json([
            'title' => $document->title,
            'document_type' => $document->document_type,
            'document_number' => $document->document_number,
            'issue_year' => $document->issue_year,
            'full_text' => $fullText,
            'source_url' => $document->source_url,
            'pdf_url' => $pdfUrl,
            'metadata' => $processedMetadata,
            
            // File information
            'has_file' => !empty($document->file_path),
            'file_name' => $document->file_name,
            'file_type' => $document->file_type,
            'file_size' => $document->file_size,
            'uploaded_at' => $document->uploaded_at,
        ]);
    }

    /**
     * Serve PDF with proxy to handle CORS issues and download headers
     */
    public function proxyPdf(LegalDocument $document)
    {
        if ($document->status !== 'active' || !$document->pdf_url) {
            abort(404, 'PDF not available.');
        }

        try {
            // Create HTTP context to handle BPK download URLs
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => [
                        'User-Agent: Mozilla/5.0 (compatible; Laravel PDF Proxy)',
                        'Accept: application/pdf,*/*',
                    ],
                    'timeout' => 30,
                    'follow_location' => true,
                    'max_redirects' => 5
                ]
            ]);

            // Fetch the PDF content
            $pdfContent = file_get_contents($document->pdf_url, false, $context);
            
            if ($pdfContent === false) {
                Log::warning('PDF Proxy: Could not fetch PDF', [
                    'document_id' => $document->id,
                    'pdf_url' => $document->pdf_url
                ]);
                abort(404, 'Could not retrieve PDF.');
            }

            // Verify it's actually a PDF
            if (substr($pdfContent, 0, 4) !== '%PDF') {
                Log::warning('PDF Proxy: Retrieved content is not a PDF', [
                    'document_id' => $document->id,
                    'content_start' => substr($pdfContent, 0, 50)
                ]);
                abort(404, 'Retrieved content is not a valid PDF.');
            }

            $filename = $this->generateFilename($document, 'pdf');

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"') // Force inline display
                ->header('X-Frame-Options', 'SAMEORIGIN')
                ->header('Cache-Control', 'public, max-age=3600'); // Cache for 1 hour
                
        } catch (\Exception $e) {
            Log::error('PDF Proxy Error: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'pdf_url' => $document->pdf_url
            ]);
            abort(404, 'Could not retrieve PDF.');
        }
    }

    /**
     * Debug view for troubleshooting document display issues.
     */
    public function debug(LegalDocument $document)
    {
        return view('documents.debug', [
            'document' => $document,
        ]);
    }

    /**
     * Generate a filename for document download.
     */
    private function generateFilename(LegalDocument $document, string $extension = 'txt'): string
    {
        $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $document->title);
        $title = preg_replace('/\s+/', '_', trim($title));
        $title = substr($title, 0, 50); // Limit length
        
        $date = $document->issue_year ?? 'no-year';
        
        return "{$title}_{$date}.{$extension}";
    }
   
    /**
     * PDF Proxy - Convert BPK download URLs to viewable PDFs
     */
    public function pdfProxy(LegalDocument $document)
    {
        if ($document->status !== 'active' || !$document->pdf_url) {
            abort(404, 'PDF not available.');
        }

        try {
            Log::info('PDF Proxy: Fetching PDF', [
                'document_id' => $document->id,
                'pdf_url' => $document->pdf_url
            ]);

            // Use Laravel HTTP client with proper headers
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'application/pdf,*/*',
                    'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
                ])
                ->get($document->pdf_url);

            if (!$response->successful()) {
                Log::warning('PDF Proxy: HTTP request failed', [
                    'status' => $response->status(),
                    'document_id' => $document->id
                ]);
                abort(404, 'Could not retrieve PDF from source.');
            }

            $pdfContent = $response->body();

            // Verify it's actually a PDF
            if (substr($pdfContent, 0, 4) !== '%PDF') {
                Log::warning('PDF Proxy: Content is not a PDF', [
                    'document_id' => $document->id,
                    'content_start' => substr($pdfContent, 0, 100)
                ]);
                abort(404, 'Retrieved content is not a valid PDF.');
            }

            $filename = $this->generateFilename($document, 'pdf');

            // Return PDF with INLINE headers to prevent download
            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->header('Content-Length', strlen($pdfContent))
                ->header('Accept-Ranges', 'bytes')
                ->header('X-Frame-Options', 'SAMEORIGIN')
                ->header('Cache-Control', 'public, max-age=3600');

        } catch (\Exception $e) {
            Log::error('PDF Proxy Error: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'pdf_url' => $document->pdf_url
            ]);
            abort(500, 'Error retrieving PDF.');
        }
    }

    /**
     * Serve uploaded files (for internal documents)
     */
    public function serveFile(LegalDocument $document)
    {
        if ($document->status !== 'active') {
            abort(404, 'Document not available.');
        }

        if (!$document->file_path || !Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        try {
            $filePath = Storage::disk('local')->path($document->file_path);
            $mimeType = Storage::disk('local')->mimeType($document->file_path);
            
            // Log file access
            Log::info('File served', [
                'document_id' => $document->id,
                'file_name' => $document->file_name,
                'user_ip' => request()->ip()
            ]);

            // For PDF files, serve inline for preview
            if ($document->file_type === 'pdf') {
                return response()->file($filePath, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }

            // For other files, force download
            return response()->download($filePath, $document->file_name, [
                'Content-Type' => $mimeType,
            ]);

        } catch (\Exception $e) {
            Log::error('File serving error: ' . $e->getMessage(), [
                'document_id' => $document->id,
                'file_path' => $document->file_path
            ]);
            abort(500, 'Error serving file.');
        }
    }
}