<?php

namespace App\Services\Scrapers;

use App\Services\Scrapers\KemluTikScraper;
use Illuminate\Support\Facades\Log;
use DOMDocument;

class KemenkoScraper extends KemluTikScraper  
{
    protected array $tikKeywords = [
        'teknologi informasi', 'digital', 'transformasi digital',
        'ekonomi digital', 'revolusi industri', 'industri 4.0',
        'fintech', 'e-commerce', 'startup', 'unicorn',
        'smart city', 'big data', 'artificial intelligence'
    ];

    public function scrapeWithLimit(int $limit): array
    {
        Log::channel('legal-documents')->info("Kemenko Scraper: Starting with limit {$limit}");
        
        // Kemenko focuses on coordinating digital economy policies
        $documents = [];
        
        $digitalEconomyTerms = [
            'ekonomi digital', 
            'transformasi digital', 
            'fintech', 
            'e-commerce',
            'startup'
        ];
        
        foreach ($digitalEconomyTerms as $term) {
            try {
                $termDocs = $this->searchByTerm($term, $limit);
                $documents = array_merge($documents, $termDocs);
                
                if (count($documents) >= $limit) {
                    break;
                }
                
                sleep(2);
                
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("Kemenko search failed for '{$term}': " . $e->getMessage());
            }
        }
        
        return array_slice($documents, 0, $limit);
    }

    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        $data = parent::extractDocumentData($dom, $url);
        
        if ($data) {
            // Override agency for Kemenko
            $data['metadata']['agency'] = 'Kementerian Koordinator Bidang Perekonomian';
            $data['metadata']['source_site'] = 'JDIH Kemenko';
        }
        
        return $data;
    }
}
