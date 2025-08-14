<?php
// app/Services/Scrapers/BpkScraper.php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use App\Services\Scrapers\EnhancedDocumentScraper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use DOMDocument;

class BpkScraper extends BaseScraper
{
    private EnhancedDocumentScraper $enhancedScraper;
    private array $testUrls = [
        'https://peraturan.bpk.go.id/Details/274494/uu-no-11-tahun-2008',
        'https://peraturan.bpk.go.id/Details/37582/uu-no-19-tahun-2016', 
        'https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022',
    ];

    private array $searchPatterns = [
        'uu' => [
            'search_term' => 'undang-undang',
            'url_pattern' => '/Details/{id}/uu-no-{number}-tahun-{year}',
            'category_url' => 'https://peraturan.bpk.go.id/search?category=uu'
        ],
        'pp' => [
            'search_term' => 'peraturan pemerintah',
            'url_pattern' => '/Details/{id}/pp-no-{number}-tahun-{year}',
            'category_url' => 'https://peraturan.bpk.go.id/search?category=pp'
        ],
        'perpres' => [
            'search_term' => 'peraturan presiden',
            'url_pattern' => '/Details/{id}/perpres-no-{number}-tahun-{year}',
            'category_url' => 'https://peraturan.bpk.go.id/search?category=perpres'
        ],
        'permen' => [
            'search_term' => 'peraturan menteri',
            'url_pattern' => '/Details/{id}/permen-{ministry}-no-{number}-tahun-{year}',
            'category_url' => 'https://peraturan.bpk.go.id/search?category=permen'
        ]
    ];

    public function __construct(DocumentSource $source)
    {
        parent::__construct($source);
        
        $this->enhancedScraper = new EnhancedDocumentScraper([
            'delay_min' => 3,
            'delay_max' => 7,
            'timeout' => 45,
            'retries' => 3
        ]);
    }

    public function scrape(): array
    {
        Log::channel('legal-documents')->info("BpkScraper: Starting BPK document discovery");
        
        $results = [];
        $strategies = $this->determineOptimalStrategies();
        
        try {
            // Test strategies with known working URLs
            $testResults = $this->runStrategyTest($strategies);
            $bestStrategy = $this->selectBestStrategy($testResults);
            
            Log::channel('legal-documents')->info("BPK Scraper selected strategy: {$bestStrategy}");
            
            // Discover document URLs from multiple sources
            $documentUrls = array_merge(
                $this->getUrlsFromSearch(),
                $this->getUrlsFromCategories(),
                $this->getUrlsFromRecentUpdates()
            );
            
            // Remove duplicates and prioritize
            $documentUrls = $this->deduplicateAndPrioritize($documentUrls);
            $documentUrls = array_slice($documentUrls, 0, 40); // Limit for testing
            
            Log::channel('legal-documents')->info("BPK Scraper found " . count($documentUrls) . " URLs to process");
            
            // Process each document URL
            foreach ($documentUrls as $index => $url) {
                Log::channel('legal-documents')->info("BPK Processing URL {$index}: {$url}");
                
                $strategies = [$bestStrategy];
                if ($index % 8 === 0) {
                    // Every 8th request, try fallback strategies
                    $strategies = ['stealth', 'basic', 'mobile'];
                }
                
                $documentData = $this->enhancedScraper->scrapeWithStrategies($url, $strategies);
                
                if ($documentData) {
                    // BPK-specific data enrichment
                    $enrichedData = $this->enrichBpkData($documentData, $url);
                    $document = $this->saveDocumentWithValidation($enrichedData);
                    
                    if ($document) {
                        $results[] = $document;
                        $this->source->incrementDocumentCount();
                    }
                } else {
                    Log::channel('legal-documents-errors')->warning("BPK: Failed to extract data from: {$url}");
                }
                
                // Adaptive delay based on success rate
                $this->adaptiveDelay($index, count($results));
                
                // Stop conditions
                if (count($results) >= 25) {
                    Log::channel('legal-documents')->info("BPK: Reached target of 25 documents");
                    break;
                }
                
                if ($index > 0 && $index % 15 === 0) {
                    $successRate = count($results) / ($index + 1);
                    if ($successRate < 0.25) {
                        Log::channel('legal-documents')->warning("BPK: Low success rate ({$successRate}), stopping");
                        break;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("BpkScraper: Error during scrape: {$e->getMessage()}");
            throw $e;
        }
        
        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("BpkScraper: Completed with " . count($results) . " documents");
        
        return $results;
    }

    /**
     * Search BPK for documents and extract detail URLs
     */
    private function getUrlsFromSearch(): array
    {
        $searchQueries = [
            'undang-undang informasi transaksi elektronik',
            'peraturan pemerintah sistem elektronik',
            'undang-undang data pribadi',
            'peraturan presiden tik',
            'undang-undang 2024',
            'undang-undang 2023'
        ];
        
        $urls = [];
        
        foreach ($searchQueries as $query) {
            $searchUrls = $this->searchBpkForDocuments($query);
            $urls = array_merge($urls, $searchUrls);
            
            // Rate limiting
            sleep(2);
        }
        
        return array_unique($urls);
    }

    /**
     * Search BPK and extract document detail URLs from results
     */
    private function searchBpkForDocuments(string $query): array
    {
        $searchUrl = "https://peraturan.bpk.go.id/Search?keywords=" . urlencode($query) . "&tentang=&nomor=";
        
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
                ])
                ->get($searchUrl);
                
            if (!$response->successful()) {
                return [];
            }
            
            return $this->extractDetailUrlsFromSearchResults($response->body());
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->warning("BPK search failed for query: {$query} - {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Extract BPK detail URLs from search results HTML
     */
    private function extractDetailUrlsFromSearchResults(string $html): array
    {
        $dom = $this->parseHtml($html);
        if (!$dom) return [];
        
        $xpath = $this->createXPath($dom);
        $urls = [];
        
        // BPK uses specific patterns for document links
        $linkPatterns = [
            '//a[contains(@href, "/Details/")]/@href',
            '//a[contains(@href, "/Home/Details/")]/@href',
            '//a[contains(@class, "document-link")]/@href',
            '//a[contains(@class, "result-title")]/@href'
        ];
        
        foreach ($linkPatterns as $pattern) {
            $elements = $xpath->query($pattern);
            foreach ($elements as $element) {
                $href = $element->nodeValue;
                $fullUrl = $this->normalizeUrl($href, 'https://peraturan.bpk.go.id');
                
                if ($this->isValidBpkDetailUrl($fullUrl)) {
                    $urls[] = $fullUrl;
                }
            }
        }
        
        return array_unique($urls);
    }

    /**
     * Get URLs from BPK category pages
     */
    private function getUrlsFromCategories(): array
    {
        $categoryUrls = [
            'https://peraturan.bpk.go.id/search?jenis=uu&tahun=2024',
            'https://peraturan.bpk.go.id/search?jenis=uu&tahun=2023',
            'https://peraturan.bpk.go.id/search?jenis=pp&tahun=2024',
            'https://peraturan.bpk.go.id/search?jenis=perpres&tahun=2024',
        ];
        
        $urls = [];
        
        foreach ($categoryUrls as $categoryUrl) {
            try {
                $response = Http::timeout(30)->get($categoryUrl);
                if ($response->successful()) {
                    $categoryUrls = $this->extractDetailUrlsFromSearchResults($response->body());
                    $urls = array_merge($urls, $categoryUrls);
                }
                sleep(2); // Rate limiting
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("BPK category failed: {$categoryUrl}");
            }
        }
        
        return array_unique($urls);
    }

    /**
     * Get URLs from recent updates/news section
     */
    private function getUrlsFromRecentUpdates(): array
    {
        // BPK might have recent updates or featured documents section
        $recentUrls = [
            'https://peraturan.bpk.go.id/recent',
            'https://peraturan.bpk.go.id/terbaru',
            'https://peraturan.bpk.go.id/'
        ];
        
        $urls = [];
        
        foreach ($recentUrls as $recentUrl) {
            try {
                $response = Http::timeout(30)->get($recentUrl);
                if ($response->successful()) {
                    $recentDocUrls = $this->extractDetailUrlsFromSearchResults($response->body());
                    $urls = array_merge($urls, $recentDocUrls);
                }
                sleep(2);
            } catch (\Exception $e) {
                // Silent fail for optional sources
            }
        }
        
        return array_unique($urls);
    }

    /**
     * Check if URL is a valid BPK detail URL
     */
    private function isValidBpkDetailUrl(string $url): bool
    {
        $patterns = [
            '/peraturan\.bpk\.go\.id\/Details\/\d+\/[a-z]+-no-\d+/',
            '/peraturan\.bpk\.go\.id\/Home\/Details\/\d+/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Normalize URL to absolute format
     */
    private function normalizeUrl(string $href, string $baseUrl): string
    {
        if (filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }
        
        if (strpos($href, '/') === 0) {
            return $baseUrl . $href;
        }
        
        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }

    /**
     * Enrich document data with BPK-specific information
     */
    private function enrichBpkData(array $data, string $url): array
    {
        // Extract BPK ID from URL
        if (preg_match('/\/Details\/(\d+)\//', $url, $matches)) {
            $data['bpk_id'] = $matches[1];
        }
        
        // Add BPK-specific metadata
        $data['metadata'] = array_merge($data['metadata'] ?? [], [
            'source_type' => 'bpk_legal_database',
            'bpk_url' => $url,
            'extraction_method' => 'bpk_enhanced_scraper',
            'scraped_at' => now()->toISOString()
        ]);
        
        return $data;
    }

    /**
     * Remove duplicates and prioritize important documents
     */
    private function deduplicateAndPrioritize(array $urls): array
    {
        $urls = array_unique($urls);
        
        // Prioritize recent years and important document types
        $prioritized = [];
        $regular = [];
        
        foreach ($urls as $url) {
            if (preg_match('/(2024|2023|uu-no|undang-undang)/', $url)) {
                $prioritized[] = $url;
            } else {
                $regular[] = $url;
            }
        }
        
        return array_merge($prioritized, $regular);
    }

    // Strategy testing methods (similar to ImprovedPeraturanScraper)
    private function runStrategyTest(array $strategies): array
    {
        Log::channel('legal-documents')->info("BPK: Running strategy test with " . count($this->testUrls) . " URLs");
        
        $results = [];
        
        foreach ($strategies as $strategy) {
            $results[$strategy] = [
                'success_count' => 0,
                'total_time' => 0,
                'error_count' => 0
            ];
            
            foreach (array_slice($this->testUrls, 0, 2) as $testUrl) {
                $startTime = microtime(true);
                
                $data = $this->enhancedScraper->scrapeWithStrategies($testUrl, [$strategy]);
                
                $duration = microtime(true) - $startTime;
                $results[$strategy]['total_time'] += $duration;
                
                if ($data) {
                    $results[$strategy]['success_count']++;
                } else {
                    $results[$strategy]['error_count']++;
                }
                
                sleep(2);
            }
        }
        
        return $results;
    }

    private function selectBestStrategy(array $testResults): string
    {
        $bestStrategy = 'stealth';
        $bestScore = 0;
        
        foreach ($testResults as $strategy => $results) {
            $testCount = 2;
            $successRate = $results['success_count'] / $testCount;
            $avgTime = $results['total_time'] / $testCount;
            
            $score = ($successRate * 100) - ($avgTime * 1.5);
            
            Log::channel('legal-documents')->info("BPK Strategy {$strategy}: {$successRate} success rate, {$avgTime}s avg time, score: {$score}");
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStrategy = $strategy;
            }
        }
        
        return $bestStrategy;
    }

    private function determineOptimalStrategies(): array
    {
        $lastSuccess = cache()->get('bpk_scraper_last_success_strategy');
        
        if ($lastSuccess) {
            return [$lastSuccess, 'stealth', 'basic'];
        }
        
        return ['stealth', 'basic', 'mobile'];
    }

    private function saveDocumentWithValidation(array $data): ?\App\Models\LegalDocument
    {
        if (empty($data['title']) || strlen($data['title']) < 10) {
            Log::channel('legal-documents-errors')->warning("BPK: Invalid title: " . ($data['title'] ?? 'empty'));
            return null;
        }
        
        $cleanData = [
            'title' => $this->cleanText($data['title']),
            'document_type' => $data['document_type'] ?? $this->extractDocumentType($data['title']),
            'document_number' => $data['document_number'] ?? null,
            'issue_date' => $data['issue_date'] ?? null,
            'source_url' => $data['source_url'],
            'pdf_url' => $data['pdf_url'] ?? null,
            'source_id' => $this->source->id,
            'metadata' => $data['metadata'] ?? []
        ];
        
        return $this->saveDocument($cleanData);
    }

    private function extractDocumentType(string $title): string
    {
        $types = [
            'undang-undang' => 'Undang-Undang',
            'peraturan pemerintah' => 'Peraturan Pemerintah',
            'peraturan presiden' => 'Peraturan Presiden',
            'peraturan menteri' => 'Peraturan Menteri',
            'keputusan presiden' => 'Keputusan Presiden',
        ];
        
        $titleLower = strtolower($title);
        
        foreach ($types as $pattern => $type) {
            if (stripos($titleLower, $pattern) !== false) {
                return $type;
            }
        }
        
        return 'Unknown';
    }

    private function adaptiveDelay(int $index, int $successCount): void
    {
        $baseDelay = 3;
        
        if ($index > 5) {
            $successRate = $successCount / $index;
            if ($successRate < 0.4) {
                $baseDelay = 8;
            } elseif ($successRate < 0.6) {
                $baseDelay = 5;
            }
        }
        
        $delay = $baseDelay + rand(1, 2);
        
        Log::channel('legal-documents')->debug("BPK: Delaying {$delay} seconds");
        sleep($delay);
    }

    // Required abstract methods from BaseScraper
    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        // This method is required by BaseScraper but we use the enhanced scraper instead
        return null;
    }

    protected function getDocumentUrls(): array
    {
        // This method is required by BaseScraper but we handle URL discovery differently
        return [];
    }
}