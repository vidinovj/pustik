<?php
// app/Http/Controllers/DocumentController.php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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

        // If it's an external URL, redirect to it
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

        // Process metadata to handle arrays
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

        // Process full_text to handle arrays
        $fullText = $document->full_text;
        if (is_array($fullText)) {
            $fullText = implode("\n", $fullText);
        }

        return response()->json([
            'title' => $document->title,
            'document_type' => $document->document_type,
            'document_number' => $document->document_number,
            'issue_date' => $document->issue_date?->format('d-m-Y'),
            'full_text' => $fullText,
            'source_url' => $document->source_url,
            'metadata' => $processedMetadata,
        ]);
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
    private function generateFilename(LegalDocument $document): string
    {
        $title = preg_replace('/[^a-zA-Z0-9\s]/', '', $document->title);
        $title = preg_replace('/\s+/', '_', trim($title));
        $title = substr($title, 0, 50); // Limit length
        
        $date = $document->issue_date ? $document->issue_date->format('Y-m-d') : 'no-date';
        
        return "{$title}_{$date}.txt";
    }
}