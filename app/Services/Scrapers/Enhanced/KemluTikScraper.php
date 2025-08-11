<?php
// app/Services/Scrapers/Enhanced/KemluTikScraper.php

namespace App\Services\Scrapers\Enhanced;

use App\Services\Scrapers\BaseScraper;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;

class KemluTikScraper extends BaseScraper
{
    protected array $tikKeywords = [
        'teknologi informasi', 'informatika', 'telekomunikasi', 'digital',
        'diplomatik digital', 'cyber diplomacy', 'e-diplomacy', 
        'sistem informasi', 'tik', 'ict', 'internet', 'data'
    ];

    public function scrape(): array
    {
        return $this->scrapeWithLimit(50);
    }

    public function scrapeWithLimit(int $limit): array
    {
        Log::channel('legal-documents')->info("Kemlu TIK Scraper: Starting with limit {$limit}");
        
        $documents = [];
        $searchTerms = ['teknologi informasi', 'digital', 'informatika', 'cyber'];
        
        foreach ($searchTerms as $term) {
            try {
                $termDocs = $this->searchByTerm($term, $limit);
                $documents = array_merge($documents, $termDocs);
                
                if (count($documents) >= $limit) {
                    break;
                }
                
                sleep(2); // Respectful delay
                
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("Kemlu TIK search failed for term '{$term}': " . $e->getMessage());
            }
        }
        
        return array_slice($documents, 0, $limit);
    }

    protected function searchByTerm(string $term, int $limit): array
    {
        $searchUrl = $this->source->base_url . '/search';
        $documents = [];
        
        try {
            // Try search functionality
            $response = Http::legalDocsScraper()
                ->post($searchUrl, [
                    'q' => $term,
                    'limit' => $limit
                ]);
                
            if ($response->successful()) {
                $html = $response->body();
                $documents = $this->extractSearchResults($html);
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->warning("Kemlu search request failed: " . $e->getMessage());
        }
        
        // Fallback: Try browse recent documents
        if (empty($documents)) {
            $documents = $this->browseRecentDocuments($limit);
        }
        
        return $documents;
    }

    protected function extractSearchResults(string $html): array
    {
        $documents = [];
        $dom = $this->parseHtml($html);
        if (!$dom) return $documents;
        
        $xpath = $this->createXPath($dom);
        
        // Look for document links in search results
        $linkElements = $xpath->query('//a[contains(@href, "dokumen") or contains(@href, "peraturan")]');
        
        foreach ($linkElements as $link) {
            $url = $this->extractHref($link, $this->source->base_url);
            if ($url && $this->isValidDocumentUrl($url)) {
                $docData = $this->scrapeDocumentPage($url);
                if ($docData && $this->isTikRelated($docData)) {
                    $document = $this->saveDocument($docData);
                    if ($document) {
                        $documents[] = $document;
                    }
                }
            }
        }
        
        return $documents;
    }

    protected function browseRecentDocuments(int $limit): array
    {
        $documents = [];
        $browseUrls = [
            $this->source->base_url . '/peraturan',
            $this->source->base_url . '/keputusan',
            $this->source->base_url . '/dokumen'
        ];
        
        foreach ($browseUrls as $browseUrl) {
            try {
                $response = Http::legalDocsScraper()->get($browseUrl);
                
                if ($response->successful()) {
                    $html = $response->body();
                    $pageDocs = $this->extractDocumentLinksFromPage($html);
                    $documents = array_merge($documents, $pageDocs);
                    
                    if (count($documents) >= $limit) {
                        break;
                    }
                }
                
                sleep(1);
                
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("Kemlu browse failed for {$browseUrl}: " . $e->getMessage());
            }
        }
        
        return array_slice($documents, 0, $limit);
    }

    protected function extractDocumentLinksFromPage(string $html): array
    {
        $documents = [];
        $dom = $this->parseHtml($html);
        if (!$dom) return $documents;
        
        $xpath = $this->createXPath($dom);
        
        // Enhanced link detection for Kemlu site
        $linkPatterns = [
            '//a[contains(@href, "/dokumen/")]',
            '//a[contains(@href, "/peraturan/")]',
            '//a[contains(@href, "/keputusan/")]',
            '//a[contains(text(), "Peraturan")]',
            '//a[contains(text(), "Keputusan")]'
        ];
        
        foreach ($linkPatterns as $pattern) {
            $links = $xpath->query($pattern);
            
            foreach ($links as $link) {
                $url = $this->extractHref($link, $this->source->base_url);
                $title = trim($link->textContent);
                
                if ($url && $title && strlen($title) > 15) {
                    // Quick TIK relevance check before full scraping
                    if ($this->quickTikCheck($title)) {
                        $docData = $this->scrapeDocumentPage($url);
                        if ($docData) {
                            $document = $this->saveDocument($docData);
                            if ($document) {
                                $documents[] = $document;
                            }
                        }
                    }
                }
            }
        }
        
        return $documents;
    }

    protected function scrapeDocumentPage(string $url): ?array
    {
        try {
            $response = Http::legalDocsScraper()->get($url);
            
            if (!$response->successful()) {
                return null;
            }
            
            $html = $response->body();
            $dom = $this->parseHtml($html);
            if (!$dom) return null;
            
            return $this->extractDocumentData($dom, $url);
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->warning("Failed to scrape Kemlu document {$url}: " . $e->getMessage());
            return null;
        }
    }

    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        $xpath = $this->createXPath($dom);
        
        try {
            // Extract title
            $titleElement = $xpath->query('//h1 | //h2 | //title')->item(0);
            $title = $titleElement ? $this->cleanText($this->extractText($titleElement)) : '';
            
            if (empty($title) || strlen($title) < 15) {
                return null;
            }
            
            // Extract document type
            $documentType = 'Peraturan Kemlu';
            $typePatterns = [
                '//span[contains(text(), "Peraturan")]',
                '//span[contains(text(), "Keputusan")]',
                '//div[contains(@class, "type")]'
            ];
            
            foreach ($typePatterns as $pattern) {
                $typeElement = $xpath->query($pattern)->item(0);
                if ($typeElement) {
                    $type = $this->cleanText($this->extractText($typeElement));
                    if (!empty($type)) {
                        $documentType = $type;
                        break;
                    }
                }
            }
            
            // Extract document number
            $documentNumber = '';
            $numberPatterns = [
                '//span[contains(text(), "Nomor")]',
                '//div[contains(text(), "No.")]'
            ];
            
            foreach ($numberPatterns as $pattern) {
                $numberElement = $xpath->query($pattern)->item(0);
                if ($numberElement) {
                    $numberText = $this->cleanText($this->extractText($numberElement));
                    if (preg_match('/(\d+[\/\w\-\d]*)/i', $numberText, $matches)) {
                        $documentNumber = $matches[1];
                        break;
                    }
                }
            }
            
            // Extract issue date
            $issueDate = null;
            $datePatterns = [
                '//span[contains(text(), "tanggal") or contains(text(), "Tanggal")]',
                '//div[contains(@class, "date")]'
            ];
            
            foreach ($datePatterns as $pattern) {
                $dateElement = $xpath->query($pattern)->item(0);
                if ($dateElement) {
                    $dateString = $this->cleanText($this->extractText($dateElement));
                    $issueDate = $this->parseIndonesianDate($dateString);
                    if ($issueDate) {
                        break;
                    }
                }
            }
            
            // Extract full text content
            $contentPatterns = [
                '//div[contains(@class, "content")]',
                '//div[contains(@class, "body")]',
                '//main',
                '//article'
            ];
            
            $fullText = '';
            foreach ($contentPatterns as $pattern) {
                $contentElement = $xpath->query($pattern)->item(0);
                if ($contentElement) {
                    $fullText = $this->cleanText($this->extractText($contentElement));
                    if (strlen($fullText) > 100) {
                        break;
                    }
                }
            }
            
            return [
                'title' => $title,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'issue_date' => $issueDate,
                'source_url' => $url,
                'metadata' => [
                    'source_site' => 'JDIH Kemlu',
                    'agency' => 'Kementerian Luar Negeri',
                    'extraction_method' => 'enhanced_http',
                    'scraped_at' => now()->toISOString(),
                    'tik_related' => true,
                ],
                'full_text' => $fullText ?: substr($title, 0, 500),
            ];
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Kemlu document extraction failed for {$url}: " . $e->getMessage());
            return null;
        }
    }

    protected function isValidDocumentUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && 
               (stripos($url, 'jdih.kemlu.go.id') !== false) &&
               (stripos($url, '/dokumen/') !== false || stripos($url, '/peraturan/') !== false);
    }

    protected function quickTikCheck(string $text): bool
    {
        $text = strtolower($text);
        foreach ($this->tikKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    protected function isTikRelated(array $docData): bool
    {
        $searchText = strtolower(
            ($docData['title'] ?? '') . ' ' . 
            ($docData['full_text'] ?? '') . ' ' .
            ($docData['metadata']['subject'] ?? '')
        );
        
        foreach ($this->tikKeywords as $keyword) {
            if (stripos($searchText, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    protected function getDocumentUrls(): array
    {
        return [];
    }
}
