<?php
// app/Services/Scrapers/ImprovedPeraturanScraper.php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use App\Services\Scrapers\EnhancedDocumentScraper;
use Illuminate\Support\Facades\Log;

class ImprovedPeraturanScraper extends BaseScraper
{
    private EnhancedDocumentScraper $enhancedScraper;
    private array $testUrls = [
        'https://peraturan.go.id/id/undang-undang-no-11-tahun-2008',
        'https://peraturan.go.id/id/peraturan-pemerintah-no-71-tahun-2019',
        'https://peraturan.go.id/id/undang-undang-no-19-tahun-2016',
        'https://peraturan.go.id/id/peraturan-presiden-no-95-tahun-2018',
        'https://peraturan.go.id/id/peraturan-menteri-komunikasi-dan-informatika-no-20-tahun-2016',
    ];

    public function __construct(DocumentSource $source)
    {
        parent::__construct($source);
        
        $this->enhancedScraper = new EnhancedDocumentScraper([
            'delay_min' => 4,
            'delay_max' => 10,
            'timeout' => 60,
            'retries' => 3
        ]);
    }

    public function scrape(): array
    {
        Log::channel('legal-documents')->info("ImprovedPeraturanScraper: Starting enhanced scrape");
        
        $results = [];
        $strategies = $this->determineOptimalStrategies();
        
        try {
            // First, test with a small set to determine best strategy
            $testResults = $this->runStrategyTest($strategies);
            $bestStrategy = $this->selectBestStrategy($testResults);
            
            Log::channel('legal-documents')->info("Selected strategy: {$bestStrategy}");
            
            // Get document URLs from different sources
            $documentUrls = array_merge(
                $this->getUrlsFromCategoryPages(),
                $this->getUrlsFromSitemap(),
                $this->getUrlsFromSearch()
            );
            
            // Remove duplicates and limit
            $documentUrls = array_unique($documentUrls);
            $documentUrls = array_slice($documentUrls, 0, 50); // Limit for testing
            
            Log::channel('legal-documents')->info("Found " . count($documentUrls) . " URLs to process");
            
            foreach ($documentUrls as $index => $url) {
                Log::channel('legal-documents')->info("Processing URL {$index}: {$url}");
                
                $strategies = [$bestStrategy];
                if ($index % 10 === 0) {
                    // Every 10th request, try fallback strategies
                    $strategies = ['stealth', 'basic', 'mobile'];
                }
                
                $documentData = $this->enhancedScraper->scrapeWithStrategies($url, $strategies);
                
                if ($documentData) {
                    $document = $this->saveDocumentWithValidation($documentData);
                    if ($document) {
                        $results[] = $document;
                        $this->source->incrementDocumentCount();
                    }
                } else {
                    Log::channel('legal-documents-errors')->warning("Failed to extract data from: {$url}");
                }
                
                // Progressive delay - longer delays if we're getting blocked
                $this->adaptiveDelay($index, count($results));
                
                // Stop if we have enough results or are getting too many failures
                if (count($results) >= 30) {
                    Log::channel('legal-documents')->info("Reached target of 30 documents");
                    break;
                }
                
                if ($index > 0 && $index % 20 === 0) {
                    $successRate = count($results) / ($index + 1);
                    if ($successRate < 0.3) {
                        Log::channel('legal-documents')->warning("Low success rate ({$successRate}), stopping");
                        break;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("ImprovedPeraturanScraper: Error during scrape: {$e->getMessage()}");
            throw $e;
        }
        
        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("ImprovedPeraturanScraper: Completed with " . count($results) . " documents");
        
        return $results;
    }

    private function runStrategyTest(array $strategies): array
    {
        Log::channel('legal-documents')->info("Running strategy test with " . count($this->testUrls) . " URLs");
        
        $results = [];
        
        foreach ($strategies as $strategy) {
            $results[$strategy] = [
                'success_count' => 0,
                'total_time' => 0,
                'error_count' => 0
            ];
            
            foreach (array_slice($this->testUrls, 0, 3) as $testUrl) {
                $startTime = microtime(true);
                
                $data = $this->enhancedScraper->scrapeWithStrategies($testUrl, [$strategy]);
                
                $duration = microtime(true) - $startTime;
                $results[$strategy]['total_time'] += $duration;
                
                if ($data) {
                    $results[$strategy]['success_count']++;
                } else {
                    $results[$strategy]['error_count']++;
                }
                
                sleep(2); // Small delay between tests
            }
        }
        
        return $results;
    }

    private function selectBestStrategy(array $testResults): string
    {
        $bestStrategy = 'stealth';
        $bestScore = 0;
        
        foreach ($testResults as $strategy => $results) {
            $testCount = 3; // We tested 3 URLs
            $successRate = $results['success_count'] / $testCount;
            $avgTime = $results['total_time'] / $testCount;
            
            // Score based on success rate and speed (prefer success over speed)
            $score = ($successRate * 100) - ($avgTime * 2);
            
            Log::channel('legal-documents')->info("Strategy {$strategy}: {$successRate} success rate, {$avgTime}s avg time, score: {$score}");
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestStrategy = $strategy;
            }
        }
        
        return $bestStrategy;
    }

    private function determineOptimalStrategies(): array
    {
        // Check if we have previous success data
        $lastSuccess = cache()->get('peraturan_scraper_last_success_strategy');
        
        if ($lastSuccess) {
            return [$lastSuccess, 'stealth', 'basic'];
        }
        
        return ['stealth', 'basic', 'mobile'];
    }

    private function getUrlsFromCategoryPages(): array
    {
        $categoryUrls = [
            'https://peraturan.go.id/peraturan/list/category/undang-undang',
            'https://peraturan.go.id/peraturan/list/category/peraturan-pemerintah',
            'https://peraturan.go.id/peraturan/list/category/peraturan-presiden',
        ];
        
        $documentUrls = [];
        
        foreach ($categoryUrls as $categoryUrl) {
            $html = $this->makeRequestWithFallback($categoryUrl);
            if ($html) {
                $urls = $this->extractDocumentUrlsFromHtml($html);
                $documentUrls = array_merge($documentUrls, $urls);
            }
        }
        
        return $documentUrls;
    }

    private function getUrlsFromSitemap(): array
    {
        $sitemapUrls = [
            'https://peraturan.go.id/sitemap.xml',
            'https://peraturan.go.id/sitemap_index.xml'
        ];
        
        $documentUrls = [];
        
        foreach ($sitemapUrls as $sitemapUrl) {
            $xml = $this->makeRequestWithFallback($sitemapUrl);
            if ($xml) {
                $urls = $this->extractUrlsFromSitemap($xml);
                $documentUrls = array_merge($documentUrls, $urls);
            }
        }
        
        return $documentUrls;
    }

    private function getUrlsFromSearch(): array
    {
        // Search for recent documents
        $searchQueries = [
            'undang-undang 2023',
            'peraturan pemerintah 2023',
            'informasi elektronik',
            'teknologi informasi'
        ];
        
        $documentUrls = [];
        
        foreach ($searchQueries as $query) {
            $searchUrl = 'https://peraturan.go.id/search?q=' . urlencode($query);
            $html = $this->makeRequestWithFallback($searchUrl);
            
            if ($html) {
                $urls = $this->extractDocumentUrlsFromHtml($html);
                $documentUrls = array_merge($documentUrls, $urls);
            }
        }
        
        return $documentUrls;
    }

    private function makeRequestWithFallback(string $url): ?string
    {
        // Try enhanced scraper first
        $result = $this->enhancedScraper->scrapeWithStrategies($url, ['basic', 'stealth']);
        
        if ($result && !empty($result['html'])) {
            return $result['html'];
        }
        
        // Fallback to parent method
        return $this->makeRequest($url);
    }

    private function extractDocumentUrlsFromHtml(string $html): array
    {
        $dom = $this->parseHtml($html);
        if (!$dom) return [];
        
        $xpath = $this->createXPath($dom);
        $urls = [];
        
        // Common patterns for document links on peraturan.go.id
        $linkPatterns = [
            '//a[contains(@href, "/id/")]/@href',
            '//a[contains(@href, "undang-undang")]/@href',
            '//a[contains(@href, "peraturan")]/@href',
        ];
        
        foreach ($linkPatterns as $pattern) {
            $nodes = $xpath->query($pattern);
            foreach ($nodes as $node) {
                $href = $node->nodeValue;
                if ($this->isValidDocumentUrl($href)) {
                    $urls[] = $this->resolveUrl($href, 'https://peraturan.go.id');
                }
            }
        }
        
        return array_unique($urls);
    }

    private function extractUrlsFromSitemap(string $xml): array
    {
        $urls = [];
        
        if (preg_match_all('/<loc>(.*?)<\/loc>/', $xml, $matches)) {
            foreach ($matches[1] as $url) {
                if ($this->isValidDocumentUrl($url)) {
                    $urls[] = $url;
                }
            }
        }
        
        return $urls;
    }

    private function isValidDocumentUrl(string $url): bool
    {
        // Check if URL points to a document page
        $patterns = [
            '/\/id\/undang-undang-/',
            '/\/id\/peraturan-pemerintah-/',
            '/\/id\/peraturan-presiden-/',
            '/\/id\/peraturan-menteri-/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        return false;
    }

    private function resolveUrl(string $url, string $base): string
    {
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    private function saveDocumentWithValidation(array $data): ?\App\Models\LegalDocument
    {
        // Validate required fields
        if (empty($data['title']) || strlen($data['title']) < 10) {
            Log::channel('legal-documents-errors')->warning("Invalid title: " . ($data['title'] ?? 'empty'));
            return null;
        }
        
        // Clean and format data
        $cleanData = [
            'title' => $this->cleanText($data['title']),
            'document_type' => $data['document_type'] ?? $this->extractDocumentType($data['title']),
            'document_number' => $data['document_number'] ?? null,
            'issue_date' => $data['issue_date'] ?? null,
            'source_url' => $data['source_url'],
            'pdf_url' => $data['pdf_url'] ?? null,
            'source_id' => $this->source->id,
            'metadata' => array_merge($data, [
                'extraction_method' => 'enhanced_multi_strategy',
                'scraped_at' => now()->toISOString()
            ])
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
        
        // Increase delay if success rate is low
        if ($index > 5) {
            $successRate = $successCount / $index;
            if ($successRate < 0.5) {
                $baseDelay = 8;
            } elseif ($successRate < 0.7) {
                $baseDelay = 5;
            }
        }
        
        // Add randomization
        $delay = $baseDelay + rand(1, 3);
        
        Log::channel('legal-documents')->debug("Delaying {$delay} seconds");
        sleep($delay);
    }

    protected function extractDocumentData(\DOMDocument $dom, string $url): ?array
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