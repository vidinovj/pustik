<?php

namespace App\Services\Scrapers;

use App\Models\LegalDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JdihPerpusnasApiScraper extends BaseScraper
{
    /**
     * Main scraping method for JDIH Perpusnas API.
     */
    public function scrape(): array
    {
        $results = [];
        
        Log::channel('legal-documents')->info("JDIH Perpusnas API: Starting scrape");

        try {
            // Search for technology-related legal documents
            $keywords = [
                'teknologi informasi',
                'telekomunikasi', 
                'data pribadi',
                'keamanan siber',
                'digital',
                'sistem informasi',
                'elektronik'
            ];

            foreach ($keywords as $keyword) {
                $documents = $this->searchDocuments($keyword);
                
                foreach ($documents as $docData) {
                    $document = $this->saveDocument($docData);
                    if ($document) {
                        $results[] = $document;
                        $this->source->incrementDocumentCount();
                    }
                }

                // Log progress
                Log::channel('legal-documents')->info("JDIH Perpusnas API: Processed keyword '{$keyword}' - found " . count($documents) . " documents");
                
                // Respect rate limiting
                sleep(1);
            }

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("JDIH Perpusnas API: Scraping failed: {$e->getMessage()}");
            throw $e;
        }

        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("JDIH Perpusnas API: Completed scrape with " . count($results) . " documents");
        
        return $results;
    }

    /**
     * Search documents via API.
     */
    protected function searchDocuments(string $keyword, int $limit = 50): array
    {
        $documents = [];
        
        try {
            $response = Http::jdihPerpusnas()->get('/list-artikel', [
                'q' => $keyword,
                'limit' => $limit,
                'offset' => 0
            ]);

            if (!$response->successful()) {
                Log::channel('legal-documents-errors')->warning("JDIH Perpusnas API: Search failed for '{$keyword}' - HTTP {$response->status()}");
                return [];
            }

            $data = $response->json();
            
            if (!isset($data['data']) || !is_array($data['data'])) {
                Log::channel('legal-documents')->info("JDIH Perpusnas API: No data found for keyword '{$keyword}'");
                return [];
            }

            foreach ($data['data'] as $item) {
                $documentData = $this->transformApiResponse($item, $keyword);
                if ($documentData) {
                    $documents[] = $documentData;
                }
            }

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("JDIH Perpusnas API: Search error for '{$keyword}': {$e->getMessage()}");
        }

        return $documents;
    }

    /**
     * Transform API response to standard document format.
     */
    protected function transformApiResponse(array $item, string $searchKeyword): ?array
    {
        try {
            // Extract title
            $title = $item['title'] ?? $item['judul'] ?? '';
            if (empty($title)) {
                return null;
            }

            // Extract document number
            $documentNumber = $item['number'] ?? $item['nomor'] ?? $item['document_number'] ?? '';

            // Extract and parse date
            $issueDate = null;
            $dateFields = ['date', 'tanggal', 'issue_date', 'publish_date', 'created_at'];
            foreach ($dateFields as $field) {
                if (isset($item[$field]) && !empty($item[$field])) {
                    $issueDate = $this->parseDate($item[$field]);
                    break;
                }
            }

            // Extract document type
            $documentType = $item['type'] ?? $item['jenis'] ?? $item['category'] ?? 'Dokumen Hukum';

            // Extract URL
            $sourceUrl = $item['url'] ?? $item['link'] ?? $item['source_url'] ?? '';

            // Extract content/description
            $fullText = $item['content'] ?? $item['description'] ?? $item['abstract'] ?? $item['ringkasan'] ?? '';

            // Build metadata
            $metadata = [
                'source_api' => 'JDIH Perpusnas',
                'search_keyword' => $searchKeyword,
                'scraped_at' => now()->toISOString(),
                'api_response' => $item, // Store original response for debugging
            ];

            return [
                'title' => $this->cleanText($title),
                'document_type' => $this->cleanText($documentType),
                'document_number' => $this->cleanText($documentNumber),
                'issue_date' => $issueDate,
                'source_url' => $sourceUrl,
                'metadata' => $metadata,
                'full_text' => $this->cleanText($fullText),
            ];

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("JDIH Perpusnas API: Error transforming item: {$e->getMessage()}", $item);
            return null;
        }
    }

    /**
     * Parse various date formats.
     */
    protected function parseDate(string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Try standard formats first
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
                return substr($dateString, 0, 10);
            }

            // Try to parse with Carbon
            $date = \Carbon\Carbon::parse($dateString);
            return $date->format('Y-m-d');

        } catch (\Exception $e) {
            // Try Indonesian date parsing
            return $this->parseIndonesianDate($dateString);
        }
    }

    /**
     * Required by BaseScraper but not used for API.
     */
    protected function extractDocumentData(\DOMDocument $dom, string $url): ?array
    {
        return null;
    }

    /**
     * Required by BaseScraper but not used for API.
     */
    protected function getDocumentUrls(): array
    {
        return [];
    }
}