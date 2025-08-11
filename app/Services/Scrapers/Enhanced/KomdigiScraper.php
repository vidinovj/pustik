<?php

namespace App\Services\Scrapers\Enhanced;

use App\Services\Scrapers\Enhanced\KemluTikScraper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;

class KomdigiScraper extends KemluTikScraper
{
    protected array $tikKeywords = [
        'teknologi informasi', 'informatika', 'telekomunikasi', 'digital',
        'komunikasi', 'pos', 'broadcasting', 'frekuensi', 'spektrum',
        'internet', 'cyber', 'data', 'sistem elektronik', 'e-commerce',
        'fintech', 'platform digital', 'media sosial', 'startup'
    ];

    public function scrapeWithLimit(int $limit): array
    {
        Log::channel('legal-documents')->info("Komdigi Scraper: Starting with limit {$limit}");
        
        // Komdigi has broader TIK scope - all their regulations are relevant
        $documents = [];
        
        // Try multiple discovery methods
        $methods = [
            'searchByCategory',
            'browseRecent',
            'searchByKeywords'
        ];
        
        foreach ($methods as $method) {
            try {
                $methodDocs = $this->$method($limit);
                $documents = array_merge($documents, $methodDocs);
                
                if (count($documents) >= $limit) {
                    break;
                }
                
                sleep(2);
                
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("Komdigi {$method} failed: " . $e->getMessage());
            }
        }
        
        return array_slice($documents, 0, $limit);
    }

    protected function searchByCategory(int $limit): array
    {
        // Komdigi-specific category URLs
        $categoryUrls = [
            $this->source->base_url . '/peraturan-menteri',
            $this->source->base_url . '/keputusan-menteri',
            $this->source->base_url . '/peraturan-dirjen'
        ];
        
        $documents = [];
        
        foreach ($categoryUrls as $categoryUrl) {
            try {
                $response = Http::legalDocsScraper()->get($categoryUrl);
                
                if ($response->successful()) {
                    $html = $response->body();
                    $pageDocs = $this->extractDocumentLinksFromPage($html);
                    $documents = array_merge($documents, $pageDocs);
                }
                
                if (count($documents) >= $limit) {
                    break;
                }
                
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("Komdigi category browse failed: " . $e->getMessage());
            }
        }
        
        return $documents;
    }

    protected function searchByKeywords(int $limit): array
    {
        // Komdigi-specific keywords
        $keywords = ['digitalisasi', 'transformasi digital', 'e-government', 'smart city'];
        $documents = [];
        
        foreach ($keywords as $keyword) {
            try {
                $keywordDocs = $this->searchByTerm($keyword, $limit);
                $documents = array_merge($documents, $keywordDocs);
                
                if (count($documents) >= $limit) {
                    break;
                }
                
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("Komdigi keyword search failed for '{$keyword}': " . $e->getMessage());
            }
        }
        
        return $documents;
    }

    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        $data = parent::extractDocumentData($dom, $url);
        
        if ($data) {
            // Override agency for Komdigi
            $data['metadata']['agency'] = 'Kementerian Komunikasi dan Digital';
            $data['metadata']['source_site'] = 'JDIH Komdigi';
        }
        
        return $data;
    }
}
