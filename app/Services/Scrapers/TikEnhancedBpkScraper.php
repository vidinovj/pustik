<?php
// app/Services/Scrapers/TikEnhancedBpkScraper.php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use App\Services\Scrapers\EnhancedDocumentScraper;
use App\Services\TikTermsService;
use App\Services\DocumentClassifierService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TikEnhancedBpkScraper extends BaseScraper
{
    private EnhancedDocumentScraper $enhancedScraper;
    private int $minTikScore = 5; // Minimum TIK relevance score
    
    private array $testUrls = [
        'https://peraturan.bpk.go.id/Details/274494/uu-no-11-tahun-2008',
        'https://peraturan.bpk.go.id/Details/37582/uu-no-19-tahun-2016', 
        'https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022',
    ];

    // TIK-focused search queries for BPK
    private array $tikSearchQueries = [
        // Core TIK Laws
        'undang-undang informasi transaksi elektronik',
        'UU ITE 11 2008',
        'undang-undang data pribadi',
        'UU 27 2022',
        'perlindungan data pribadi',
        
        // Digital Government
        'sistem pemerintahan berbasis elektronik',
        'SPBE',
        'transformasi digital',
        'layanan digital nasional',
        'satu data indonesia',
        
        // Security & Privacy
        'keamanan siber',
        'insiden siber',
        'cyber security',
        'manajemen krisis siber',
        
        // E-commerce & Fintech
        'perdagangan melalui sistem elektronik',
        'PMSE',
        'e-commerce',
        'teknologi finansial',
        'fintech',
        'pembayaran digital',
        
        // Digital Infrastructure
        'tanda tangan elektronik',
        'sertifikat elektronik',
        'telekomunikasi',
        'digitalisasi daerah',
        
        // Recent years for discovery
        'teknologi informasi 2024',
        'sistem elektronik 2024',
        'digital 2023',
        'informasi 2023',
        
        // Relationship queries for regulatory cascade
        'mencabut peraturan',
        'perubahan atas',
        'melaksanakan ketentuan'
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
        
        // Set minimum TIK score from config if available
        $this->minTikScore = $source->getConfig('min_tik_score', 5);
    }

    public function setMinTikScore(int $score): void
    {
        $this->minTikScore = $score;
    }

    public function scrape(): array
    {
        Log::channel('legal-documents')->info("TikEnhancedBpkScraper: Starting TIK-focused BPK scraping");
        
        $results = [];
        $strategies = $this->determineOptimalStrategies();
        
        try {
            // Test strategies with known working URLs
            $testResults = $this->runStrategyTest($strategies);
            $bestStrategy = $this->selectBestStrategy($testResults);
            
            Log::channel('legal-documents')->info("TIK BPK Scraper selected strategy: {$bestStrategy}");
            
            // Discover TIK-focused document URLs
            $documentUrls = array_merge(
                $this->getTikUrlsFromSearch(),
                $this->getTikUrlsFromCategories(),
                $this->getRecentTikUpdates()
            );
            
            // Remove duplicates and prioritize TIK relevance
            $documentUrls = $this->deduplicateAndPrioritizeTik($documentUrls);
            $documentUrls = array_slice($documentUrls, 0, 40);
            
            Log::channel('legal-documents')->info("TIK BPK Scraper found " . count($documentUrls) . " URLs to process");
            
            $tikDocumentCount = 0;
            $totalProcessed = 0;
            
            // Process each document URL with TIK filtering
            foreach ($documentUrls as $index => $url) {
                Log::channel('legal-documents')->info("TIK BPK Processing URL {$index}: {$url}");
                
                $strategies = [$bestStrategy];
                if ($index % 8 === 0) {
                    $strategies = ['stealth', 'basic', 'mobile'];
                }
                
                $documentData = $this->enhancedScraper->scrapeWithStrategies($url, $strategies);
                
                if ($documentData) {
                    $totalProcessed++;
                    
                    // Apply TIK filtering and scoring
                    $tikEnrichedData = $this->applyTikFiltering($documentData, $url);
                    
                    if ($tikEnrichedData && $tikEnrichedData['is_tik_related']) {
                        $document = $this->saveDocumentWithValidation($tikEnrichedData);
                        
                        if ($document) {
                            $results[] = $document;
                            $tikDocumentCount++;
                            $this->source->incrementDocumentCount();
                            
                            Log::channel('legal-documents')->info("TIK document saved: {$document->title} (Score: {$tikEnrichedData['tik_relevance_score']})");
                        }
                    } else {
                        Log::channel('legal-documents')->debug("Non-TIK document filtered out: " . ($documentData['title'] ?? 'Unknown'));
                    }
                } else {
                    Log::channel('legal-documents-errors')->warning("TIK BPK: Failed to extract data from: {$url}");
                }
                
                // Adaptive delay
                $this->adaptiveDelay($index, $tikDocumentCount);
                
                // Stop conditions - focused on TIK document count
                if ($tikDocumentCount >= 20) {
                    Log::channel('legal-documents')->info("TIK BPK: Reached target of 20 TIK documents");
                    break;
                }
                
                // Check success rate for TIK documents
                if ($totalProcessed > 10) {
                    $tikSuccessRate = $tikDocumentCount / $totalProcessed;
                    if ($tikSuccessRate < 0.15) {
                        Log::channel('legal-documents')->warning("TIK BPK: Low TIK success rate ({$tikSuccessRate}), adjusting strategy");
                        $this->minTikScore = max(3, $this->minTikScore - 2); // Lower threshold
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("TikEnhancedBpkScraper: Error during scrape: {$e->getMessage()}");
            throw $e;
        }
        
        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("TikEnhancedBpkScraper: Completed with {$tikDocumentCount} TIK documents out of {$totalProcessed} processed");
        
        return $results;
    }

    /**
     * Get TIK-focused URLs from BPK search
     */
    private function getTikUrlsFromSearch(): array
    {
        $urls = [];
        
        foreach ($this->tikSearchQueries as $query) {
            $searchUrls = $this->searchBpkForDocuments($query);
            $urls = array_merge($urls, $searchUrls);
            
            Log::channel('legal-documents')->debug("TIK search '{$query}' found " . count($searchUrls) . " URLs");
            
            // Rate limiting - important for BPK
            sleep(2);
            
            // Limit queries if we have enough URLs
            // if (count($urls) > 100) {
            //     break;
            // }
        }
        
        return array_unique($urls);
    }

    /**
     * Get TIK documents from specific BPK categories
     */
    private function getTikUrlsFromCategories(): array
    {
        $tikCategories = [
            'https://peraturan.bpk.go.id/search?jenis=uu&tahun=2024&q=informasi',
            'https://peraturan.bpk.go.id/search?jenis=uu&tahun=2023&q=data',
            'https://peraturan.bpk.go.id/search?jenis=uu&tahun=2022&q=elektronik',
            'https://peraturan.bpk.go.id/search?jenis=pp&tahun=2024&q=sistem',
            'https://peraturan.bpk.go.id/search?jenis=perpres&tahun=2024&q=digital',
            'https://peraturan.bpk.go.id/search?jenis=permen&tahun=2024&q=komunikasi',
        ];
        
        $urls = [];
        
        foreach ($tikCategories as $categoryUrl) {
            try {
                $response = Http::timeout(30)->get($categoryUrl);
                if ($response->successful()) {
                    $categoryUrls = $this->extractDetailUrlsFromSearchResults($response->body());
                    $urls = array_merge($urls, $categoryUrls);
                }
                sleep(2);
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("TIK BPK category failed: {$categoryUrl}");
            }
        }
        
        return array_unique($urls);
    }

    /**
     * Get recent TIK updates
     */
    private function getRecentTikUpdates(): array
    {
        // Try recent TIK-related searches
        $recentQueries = [
            'https://peraturan.bpk.go.id/search?tahun=2024&q=teknologi',
            'https://peraturan.bpk.go.id/search?tahun=2024&q=digital',
            'https://peraturan.bpk.go.id/search?tahun=2023&q=informasi',
        ];
        
        $urls = [];
        
        foreach ($recentQueries as $recentUrl) {
            try {
                $response = Http::timeout(30)->get($recentUrl);
                if ($response->successful()) {
                    $recentUrls = $this->extractDetailUrlsFromSearchResults($response->body());
                    $urls = array_merge($urls, $recentUrls);
                }
                sleep(2);
            } catch (\Exception $e) {
                // Silent fail for optional sources
            }
        }
        
        return array_unique($urls);
    }

    /**
     * Apply TIK filtering and scoring to document data
     */
    private function applyTikFiltering(array $data, string $url): ?array
    {
        $title = $data['title'] ?? '';
        $content = $data['full_text'] ?? '';
        $metadata = $data['metadata'] ?? [];
        
        // Calculate TIK relevance score
        $tikScore = TikTermsService::calculateTikScore($title . ' ' . $content);
        
        // Check if document meets minimum TIK threshold
        if ($tikScore < $this->minTikScore) {
            return null; // Filter out non-TIK documents
        }
        
        // Extract TIK keywords
        $tikKeywords = TikTermsService::extractTikKeywords($title . ' ' . $content);
        
        // Classify document
        $tempDocument = new \App\Models\LegalDocument([
            'title' => $title,
            'full_text' => $content,
            'metadata' => $metadata,
        ]);
        $documentCategory = DocumentClassifierService::classifyDocument($tempDocument);
        
        // Enrich data with TIK information
        $data['tik_relevance_score'] = $tikScore;
        $data['tik_keywords'] = $tikKeywords;
        $data['is_tik_related'] = true;
        $data['document_category'] = $documentCategory['category'] ?? 'general_technology';
        
        // Add TIK-specific metadata
        $data['metadata'] = array_merge($metadata, [
            'tik_classification' => $documentCategory,
            'tik_score_breakdown' => $this->getTikScoreBreakdown($title . ' ' . $content),
            'extraction_method' => 'tik_enhanced_bpk_scraper',
            'tik_filter_applied' => true,
            'min_tik_score_threshold' => $this->minTikScore
        ]);
        
        return $data;
    }

    /**
     * Get detailed TIK score breakdown for analysis
     */
    private function getTikScoreBreakdown(string $text): array
    {
        $breakdown = [];
        $textLower = strtolower($text);
        
        foreach (TikTermsService::getAllTikTerms() as $term => $score) {
            if (stripos($textLower, $term) !== false) {
                $breakdown[$term] = $score;
            }
        }
        
        return $breakdown;
    }

    /**
     * Prioritize URLs based on TIK relevance indicators
     */
    private function deduplicateAndPrioritizeTik(array $urls): array
    {
        $urls = array_unique($urls);
        
        $highPriority = [];
        $mediumPriority = [];
        $lowPriority = [];
        
        foreach ($urls as $url) {
            $urlLower = strtolower($url);
            
            // High priority: Contains strong TIK indicators
            if (preg_match('/(uu-no-11-tahun-2008|uu-no-19-tahun-2016|uu-no-27-tahun-2022|informasi|elektronik|data-pribadi)/', $urlLower)) {
                $highPriority[] = $url;
            }
            // Medium priority: Contains TIK-related terms
            elseif (preg_match('/(sistem|digital|teknologi|komunikasi|2024|2023)/', $urlLower)) {
                $mediumPriority[] = $url;
            }
            // Low priority: Others
            else {
                $lowPriority[] = $url;
            }
        }
        
        return array_merge($highPriority, $mediumPriority, $lowPriority);
    }

    // Reuse BPK search methods from parent scraper
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
            Log::channel('legal-documents-errors')->warning("TIK BPK search failed for query: {$query} - {$e->getMessage()}");
            return [];
        }
    }

    private function extractDetailUrlsFromSearchResults(string $html): array
    {
        $dom = $this->parseHtml($html);
        if (!$dom) return [];
        
        $xpath = $this->createXPath($dom);
        $urls = [];
        
        $linkPatterns = [
            '//a[contains(@href, "/Details/")]/@href',
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

    // Strategy and validation methods (similar to parent)
    private function runStrategyTest(array $strategies): array
    {
        Log::channel('legal-documents')->info("TIK BPK: Running strategy test");
        
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
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStrategy = $strategy;
            }
        }
        
        return $bestStrategy;
    }

    private function determineOptimalStrategies(): array
    {
        $lastSuccess = cache()->get('tik_bpk_scraper_last_success_strategy');
        
        if ($lastSuccess) {
            return [$lastSuccess, 'stealth', 'basic'];
        }
        
        return ['stealth', 'basic', 'mobile'];
    }

    private function saveDocumentWithValidation(array $data): ?\App\Models\LegalDocument
    {
        if (empty($data['title']) || strlen($data['title']) < 10) {
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
            'metadata' => $data['metadata'] ?? [],
            'tik_relevance_score' => $data['tik_relevance_score'] ?? 0,
            'tik_keywords' => $data['tik_keywords'] ?? [],
            'is_tik_related' => $data['is_tik_related'] ?? false,
            'document_category' => $data['document_category'] ?? null,
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
        sleep($delay);
    }

    // Required abstract methods from BaseScraper
    protected function extractDocumentData(\DOMDocument $dom, string $url): ?array
    {
        return null;
    }

    protected function getDocumentUrls(): array
    {
        return [];
    }
}