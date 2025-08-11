<?php
// app/Services/Scrapers/SessionAwarePeraturanScraper.php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use DOMDocument;
use DOMXPath;

class SessionAwarePeraturanScraper extends BaseScraper
{
    protected array $sessionCookies = [];
    protected string $baseUrl = 'https://peraturan.go.id';
    
    public function scrape(): array
    {
        Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Starting enhanced scraping session");
        
        // Step 1: Establish session by visiting homepage
        if (!$this->establishSession()) {
            Log::channel('legal-documents-errors')->error("Session-Aware Peraturan.go.id: Failed to establish session");
            return [];
        }
        
        // Step 2: Try different URL patterns to find accessible documents
        $documents = [];
        $urlPatterns = $this->getUrlPatterns();
        
        foreach ($urlPatterns as $pattern) {
            $patternDocs = $this->scrapeUrlPattern($pattern);
            $documents = array_merge($documents, $patternDocs);
            
            // Add delay between pattern attempts
            $this->addRandomDelay();
        }
        
        Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Scraped " . count($documents) . " documents");
        return $documents;
    }
    
    protected function establishSession(): bool
    {
        try {
            Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Establishing session");
            
            // Visit homepage first
            $response = Http::sessionAwareScraper()
                ->withHeaders(['Referer' => 'https://google.com/'])
                ->get($this->baseUrl);
            
            if (!$response->successful()) {
                Log::channel('legal-documents-errors')->warning("Session-Aware Peraturan.go.id: Homepage request failed: " . $response->status());
                return false;
            }
            
            // Store cookies for subsequent requests
            $this->sessionCookies = $this->extractCookies($response);
            
            // Try visiting a browse/category page to establish deeper session
            $categoryUrl = $this->baseUrl . '/common/dokumen/ln';
            $categoryResponse = Http::sessionAwareScraper()
                ->withHeaders([
                    'Referer' => $this->baseUrl,
                ])
                ->withCookies($this->sessionCookies, parse_url($this->baseUrl, PHP_URL_HOST))
                ->get($categoryUrl);
                
            if ($categoryResponse->successful()) {
                // Update cookies
                $newCookies = $this->extractCookies($categoryResponse);
                $this->sessionCookies = array_merge($this->sessionCookies, $newCookies);
                Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Session established successfully");
                return true;
            }
            
            Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Basic session established");
            return true;
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Session-Aware Peraturan.go.id: Session establishment failed: " . $e->getMessage());
            return false;
        }
    }
    
    protected function getUrlPatterns(): array
    {
        // Different URL patterns to try
        return [
            // Browse by category
            [
                'type' => 'browse',
                'base_url' => $this->baseUrl . '/common/dokumen/ln',
                'description' => 'Browse Lembaran Negara'
            ],
            // Direct document access patterns
            [
                'type' => 'direct',
                'urls' => [
                    'https://peraturan.go.id/id/uu-no-1-tahun-2025',
                    'https://peraturan.go.id/id/pp-no-1-tahun-2025',
                    'https://peraturan.go.id/id/perpres-no-1-tahun-2025',
                    'https://peraturan.go.id/id/permenpar-no-1-tahun-2025',
                    'https://peraturan.go.id/id/permenkes-no-1-tahun-2025',
                ],
                'description' => 'Direct document URLs'
            ],
            // Alternative access points
            [
                'type' => 'alternative',
                'urls' => [
                    'https://peraturan.go.id/ln/2025',
                    'https://peraturan.go.id/common/dokumen',
                ],
                'description' => 'Alternative access points'
            ]
        ];
    }
    
    protected function scrapeUrlPattern(array $pattern): array
    {
        $documents = [];
        
        Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Trying pattern: " . $pattern['description']);
        
        if ($pattern['type'] === 'browse') {
            // Browse category pages for document links
            $documents = $this->scrapeBrowsePage($pattern['base_url']);
        } elseif ($pattern['type'] === 'direct' || $pattern['type'] === 'alternative') {
            // Try direct URLs
            foreach ($pattern['urls'] as $url) {
                $doc = $this->scrapeDocument($url);
                if ($doc) {
                    $documents[] = $doc;
                }
                $this->addRandomDelay();
            }
        }
        
        return $documents;
    }
    
    protected function scrapeBrowsePage(string $url): array
    {
        try {
            $response = Http::sessionAwareScraper()
                ->withHeaders(['Referer' => $this->baseUrl])
                ->withCookies($this->sessionCookies, parse_url($this->baseUrl, PHP_URL_HOST))
                ->get($url);
                
            if (!$response->successful()) {
                Log::channel('legal-documents-errors')->warning("Session-Aware Peraturan.go.id: Browse page failed: {$url} - " . $response->status());
                return [];
            }
            
            $html = $response->body();
            
            // Check if we hit a login page
            if ($this->isLoginPage($html)) {
                Log::channel('legal-documents-errors')->warning("Session-Aware Peraturan.go.id: Browse page redirected to login: {$url}");
                return [];
            }
            
            // Extract document links from browse page
            $documentUrls = $this->extractDocumentLinks($html, $url);
            
            Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Found " . count($documentUrls) . " document links in browse page");
            
            $documents = [];
            foreach ($documentUrls as $docUrl) {
                $doc = $this->scrapeDocument($docUrl);
                if ($doc) {
                    $documents[] = $doc;
                }
                $this->addRandomDelay();
                
                // Limit to prevent overwhelming
                if (count($documents) >= 10) {
                    break;
                }
            }
            
            return $documents;
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Session-Aware Peraturan.go.id: Browse page error: " . $e->getMessage());
            return [];
        }
    }
    
    protected function scrapeDocument(string $url): ?LegalDocument
    {
        try {
            Log::channel('legal-documents')->info("Session-Aware Peraturan.go.id: Scraping document: {$url}");
            
            $response = Http::sessionAwareScraper()
                ->withHeaders(['Referer' => $this->baseUrl])
                ->withCookies($this->sessionCookies, parse_url($this->baseUrl, PHP_URL_HOST))
                ->get($url);
                
            if (!$response->successful()) {
                Log::channel('legal-documents-errors')->warning("Session-Aware Peraturan.go.id: Document request failed: {$url} - " . $response->status());
                return null;
            }
            
            $html = $response->body();
            
            // Check for login page
            if ($this->isLoginPage($html)) {
                Log::channel('legal-documents-errors')->warning("Session-Aware Peraturan.go.id: Document redirected to login: {$url}");
                return null;
            }
            
            // Parse and extract document data
            $dom = $this->parseHtml($html);
            if (!$dom) {
                return null;
            }
            
            $documentData = $this->extractDocumentData($dom, $url);
            if (!$documentData) {
                return null;
            }
            
            return $this->saveDocument($documentData);
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Session-Aware Peraturan.go.id: Document scraping error: " . $e->getMessage());
            return null;
        }
    }
    
    protected function updateSessionState($response): void
    {
        // Could extract CSRF tokens or other session data from headers if needed
        // For now, just maintain consistent referer/origin headers
    }

    
    protected function extractDocumentLinks(string $html, string $baseUrl): array
    {
        $dom = $this->parseHtml($html);
        if (!$dom) {
            return [];
        }
        
        $xpath = $this->createXPath($dom);
        $links = [];
        
        // Look for various link patterns
        $linkPatterns = [
            '//a[contains(@href, "/id/")]/@href',
            '//a[contains(@href, "peraturan")]/@href',
            '//a[contains(@href, "/ln/")]/@href',
        ];
        
        foreach ($linkPatterns as $pattern) {
            $elements = $xpath->query($pattern);
            foreach ($elements as $element) {
                $href = $element->nodeValue;
                $fullUrl = $this->normalizeUrl($href, $baseUrl);
                if ($fullUrl && $this->isValidDocumentUrl($fullUrl)) {
                    $links[] = $fullUrl;
                }
            }
        }
        
        return array_unique($links);
    }
    
    protected function isValidDocumentUrl(string $url): bool
    {
        // Check if URL looks like a document URL
        $patterns = [
            '/\/id\/(uu|pp|perpres|permen|perda|kepres|keppres)-/',
            '/peraturan\.go\.id\/id\/[a-z]+-no-\d+/',
            '/peraturan\.go\.id\/ln\/\d{4}\/\d+/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function normalizeUrl(string $href, string $baseUrl): string
    {
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }
        
        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }
    
    protected function addRandomDelay(): void
    {
        $config = config('legal_documents.http_client.anti_detection');
        
        if ($config['random_delays']) {
            $delay = rand($config['min_delay'], $config['max_delay']);
            sleep($delay);
        } else {
            sleep(2); // Default delay
        }
    }
    
    // Implement required abstract methods
    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        // Use the same extraction logic as FixedPeraturanScraper
        // (implementation would be the same as in FixedPeraturanScraper)
        return null; // Placeholder
    }
    
    protected function getDocumentUrls(): array
    {
        return [];
    }
}