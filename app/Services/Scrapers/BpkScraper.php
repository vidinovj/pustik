<?php
// app/Services/Scrapers/BpkScraper.php
// ENHANCEMENT: Add advanced entity-based search to existing working BpkScraper

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use App\Services\Scrapers\EnhancedDocumentScraper;
use App\Services\TikTermsService;
use App\Services\DocumentClassifierService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BpkScraper extends BaseScraper
{
    private EnhancedDocumentScraper $enhancedScraper;
    private int $minRelevanceScore = 5;
    protected ?int $limit = null; // Added property
    protected bool $dryRun = false; // Added property

    // TIK-focused search queries for BPK
    private array $searchQueries = [
        'undang-undang informasi transaksi elektronik', 'UU ITE 11 2008', 'undang-undang data pribadi',
        'UU 27 2022', 'perlindungan data pribadi', 'sistem pemerintahan berbasis elektronik', 'SPBE',
        'transformasi digital', 'layanan digital nasional', 'satu data indonesia', 'keamanan siber',
        'insiden siber', 'cyber security', 'manajemen krisis siber', 'perdagangan melalui sistem elektronik',
        'PMSE', 'e-commerce', 'teknologi finansial', 'fintech', 'pembayaran digital', 'tanda tangan elektronik',
        'sertifikat elektronik', 'telekomunikasi', 'digitalisasi daerah', 'teknologi informasi 2024',
        'sistem elektronik 2024', 'digital 2023', 'informasi 2023', 'mencabut peraturan', 'perubahan atas',
        'melaksanakan ketentuan'
    ];
    
    // TIK-focused government entities (from your discovery)
    private array $entities = [
        '661' => [
            'name' => 'Kementerian Luar Negeri',
            'short' => 'Kemlu',
            'focus' => 'diplomatic_technology'
        ],
        '568' => [
            'name' => 'Kementerian Riset, Teknologi, dan Pendidikan Tinggi',
            'short' => 'Kemristekdikti', 
            'focus' => 'research_technology'
        ],
        '676' => [
            'name' => 'Kementerian Komunikasi dan Digital',
            'short' => 'Komdigi',
            'focus' => 'digital_infrastructure'
        ],
        '603' => [
            'name' => 'Kementerian Komunikasi dan Informatika',
            'short' => 'Kominfo',
            'focus' => 'telecommunications'
        ],
        '557' => [
            'name' => 'Badan Siber dan Sandi Negara',
            'short' => 'BSSN',
            'focus' => 'cybersecurity'
        ],
        '607' => [
            'name' => 'Badan Riset dan Inovasi Nasional',
            'short' => 'BRIN',
            'focus' => 'innovation_research'
        ]
    ];

    // Advanced search keywords for entity-based searches
    private array $advancedSearchKeywords = [
        'teknologi', 'digital', 'sistem informasi', 'komunikasi', 'cyber', 'elektronik', 'data'
    ];

    private array $testUrls = [
        'https://peraturan.bpk.go.id/Details/274494/uu-no-11-tahun-2008',
        'https://peraturan.bpk.go.id/Details/37582/uu-no-19-tahun-2016', 
        'https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022',
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
        
        $this->minRelevanceScore = $source->config['min_tik_score'] ?? 5;
    }

    // Added setLimit method
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    // Added setDryRun method
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function scrape(): array
    {
        Log::channel('legal-documents')->info("BpkScraper: Starting enhanced BPK scraping with advanced entity search");
        
        $results = [];
        $strategies = $this->determineOptimalStrategies();
        
        try {
            $testResults = $this->runStrategyTest($strategies);
            $bestStrategy = $this->selectBestStrategy($testResults);
            
            Log::channel('legal-documents')->info("BPK Scraper selected strategy: {$bestStrategy}");
            
            $documentUrls = $this->getUrlsFromAdvancedEntitySearch();
            
            $documentUrls = array_slice($documentUrls, 0, 40);
            
            Log::channel('legal-documents')->info("BPK Scraper found " . count($documentUrls) . " URLs to process (including advanced entity search)");
            
            $documentCount = 0;
            $totalProcessed = 0;
            
            foreach ($documentUrls as $index => $url) {
                Log::channel('legal-documents')->info("BPK Processing URL {$index}: {$url}");
                
                $strategies = [$bestStrategy];
                if ($index % 8 === 0) {
                    $strategies = ['stealth', 'basic', 'mobile'];
                }
                
                $documentData = $this->enhancedScraper->scrapeWithStrategies($url, $strategies);
                
                if ($documentData) {
                    $totalProcessed++;
                    
                    $enrichedData = $this->applyFiltering($documentData, $url);
                    
                    if ($enrichedData && $enrichedData['is_tik_related']) {
                        $document = $this->saveDocumentWithValidation($enrichedData);
                        
                        if ($document) {
                            $results[] = $document;
                            $documentCount++;
                            $this->source->increment('total_documents');
                            
                            Log::channel('legal-documents')->info("Relevant document saved: {$document->title} (Score: {$enrichedData['tik_relevance_score']})");
                        }
                    } else {
                        Log::channel('legal-documents')->debug("Non-relevant document filtered out: " . ($documentData['title'] ?? 'Unknown'));
                    }
                } else {
                    Log::channel('legal-documents-errors')->warning("BPK: Failed to extract data from: {$url}");
                }
                
                $this->adaptiveDelay($index, $documentCount);
                
                // Modified to respect $this->limit
                if ($this->limit !== null && $documentCount >= $this->limit) {
                    Log::channel('legal-documents')->info("BPK: Reached target limit of {$this->limit} relevant documents");
                    break;
                }
                
                if ($totalProcessed > 10) {
                    $successRate = $documentCount / $totalProcessed;
                    if ($successRate < 0.15) {
                        Log::channel('legal-documents')->warning("BPK: Low relevance success rate ({$successRate}), adjusting strategy");
                        $this->minRelevanceScore = max(3, $this->minRelevanceScore - 2);
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("BpkScraper: Error during scrape: {$e->getMessage()}");
            throw $e;
        }
        
        $this->source->update(['last_scraped_at' => now()]);
        Log::channel('legal-documents')->info("BpkScraper: Completed with {$documentCount} relevant documents out of {$totalProcessed} processed");
        
        return $results;
    }

    private function getUrlsFromAdvancedEntitySearch(): array
    {
        $urls = [];
        Log::channel('legal-documents')->info("🎯 Starting Advanced Entity Search for relevant documents");
        foreach ($this->advancedSearchKeywords as $keyword) {
            $searchUrl = $this->buildAdvancedEntitySearchUrl($keyword);
            Log::channel('legal-documents')->info("🔍 Advanced Entity Search: {$searchUrl}");
            try {
                $response = Http::timeout(45)->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
                ])->get($searchUrl);
                if ($response->successful()) {
                    $entityUrls = $this->extractDetailUrlsFromSearchResults($response->body());
                    $urls = array_merge($urls, $entityUrls);
                    Log::channel('legal-documents')->info("Advanced search for '{$keyword}' found " . count($entityUrls) . " URLs from entities");
                    foreach ($entityUrls as $url) {
                        $this->markUrlWithEntitySource($url, $keyword);
                    }
                } else {
                    Log::channel('legal-documents-errors')->warning("Advanced entity search failed for keyword '{$keyword}': HTTP {$response->status()}");
                }
                sleep(3);
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->warning("Advanced entity search failed for keyword '{$keyword}': " . $e->getMessage());
                continue;
            }
        }
        Log::channel('legal-documents')->info("🎯 Advanced Entity Search completed. Found " . count(array_unique($urls)) . " unique URLs");
        return array_unique($urls);
    }

    private function buildAdvancedEntitySearchUrl(string $keyword): string
    {
        $baseUrl = 'https://peraturan.bpk.go.id/Search';
        $params = ['keywords' => '', 'tentang' => $keyword, 'nomor' => ''];
        $entityParams = [];
        foreach (array_keys($this->entities) as $entityId) {
            $entityParams[] = "entitas={$entityId}";
        }
        $queryString = http_build_query($params) . '&' . implode('&', $entityParams);
        return $baseUrl . '?' . $queryString;
    }

    private function markUrlWithEntitySource(string $url, string $keyword): void
    {
        $cacheKey = 'bpk_entity_url_' . md5($url);
        cache()->put($cacheKey, [
            'search_keyword' => $keyword,
            'search_type' => 'advanced_entity_search',
            'entities_searched' => array_keys($this->entities),
            'searched_at' => now()->toISOString()
        ], 86400);
    }

    private function applyFiltering(array $data, string $url): ?array
    {
        $title = $data['title'] ?? '';
        $content = $data['full_text'] ?? '';
        $metadata = $data['metadata'] ?? [];
        $relevanceScore = TikTermsService::calculateTikScore($title . ' ' . $content);

        Log::channel('legal-documents')->info("Filtering: Document '{$title}' - Relevance Score: {$relevanceScore}, Min Score: {$this->minRelevanceScore}"); // Added log

        if ($relevanceScore < $this->minRelevanceScore) {
            Log::channel('legal-documents')->debug("Filtering: Document '{$title}' filtered out due to low relevance score."); // Added log
            return null;
        }
        $keywords = TikTermsService::extractTikKeywords($title . ' ' . $content);
        $tempDocument = new \App\Models\LegalDocument(['title' => $title, 'full_text' => $content, 'metadata' => $metadata]);
        $documentCategory = DocumentClassifierService::classifyDocument($tempDocument);
        $entityInfo = $this->getEntitySourceInfo($url);
        $data['tik_relevance_score'] = $relevanceScore;
        $data['tik_keywords'] = $keywords;
        $data['is_tik_related'] = true; // Assuming it's always true if it passes relevance score
        Log::channel('legal-documents')->info("Filtering: Document '{$title}' - is_tik_related: {$data['is_tik_related']}"); // Added log
        $data['document_category'] = $documentCategory['category'] ?? 'general_technology';
        $data['metadata'] = array_merge($metadata, [
            'classification' => $documentCategory,
            'score_breakdown' => $this->getScoreBreakdown($title . ' ' . $content),
            'extraction_method' => 'bpk_scraper_with_entities',
            'filter_applied' => true,
            'min_score_threshold' => $this->minRelevanceScore,
            'entity_search_info' => $entityInfo,
            'searched_entities' => $this->getEntityNames()
        ]);
        return $data;
    }

    private function getEntitySourceInfo(string $url): ?array
    {
        $cacheKey = 'bpk_entity_url_' . md5($url);
        return cache()->get($cacheKey);
    }

    private function getEntityNames(): array
    {
        $names = [];
        foreach ($this->entities as $id => $info) {
            $names[$id] = $info['name'];
        }
        return $names;
    }

    public function getEntitySearchAnalytics(): array
    {
        $analytics = [
            'entities_configured' => count($this->entities),
            'search_keywords' => count($this->advancedSearchKeywords),
            'entity_details' => $this->entities,
            'last_search_urls' => []
        ];
        foreach (array_slice($this->advancedSearchKeywords, 0, 3) as $keyword) {
            $analytics['last_search_urls'][] = $this->buildAdvancedEntitySearchUrl($keyword);
        }
        return $analytics;
    }

    private function getScoreBreakdown(string $text): array
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

    private function searchBpkForDocuments(string $query): array
    {
        $searchUrl = "https://peraturan.bpk.go.id/Search?keywords=" . urlencode($query) . "&tentang=&nomor=";
        try {
            $response = Http::timeout(30)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            ])->get($searchUrl);
            if (!$response->successful()) {
                return [];
            }
            return $this->extractDetailUrlsFromSearchResults($response->body());
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->warning("BPK search failed for query: {$query} - {$e->getMessage()}");
            return [];
        }
    }

    private function extractDetailUrlsFromSearchResults(string $html): array
    {
        $dom = $this->parseHtml($html);
        if (!$dom) return [];
        $xpath = $this->createXPath($dom);
        $urls = [];
        $linkPatterns = ['//a[contains(@href, "/Details/")]/@href'];
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

    private function runStrategyTest(array $strategies): array
    {
        Log::channel('legal-documents')->info("BPK: Running strategy test");
        $results = [];
        foreach ($strategies as $strategy) {
            $results[$strategy] = ['success_count' => 0, 'total_time' => 0, 'error_count' => 0];
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
        $lastSuccess = cache()->get('bpk_scraper_last_success_strategy');
        if ($lastSuccess) {
            return [$lastSuccess, 'stealth', 'basic'];
        }
        return ['stealth', 'basic', 'mobile'];
    }

    private function saveDocumentWithValidation(array $data): ?\App\Models\LegalDocument
    {
        if ($this->dryRun) {
            Log::channel('legal-documents')->info("DRY RUN: Not saving document: {$data['title']}");
            return new \App\Models\LegalDocument($data);
        }

        if (empty($data['title']) || strlen($data['title']) < 10) {
            return null;
        }
        
        // Prioritize URL-extracted document_type_code over title extraction
        $documentTypeCode = $data['document_type_code'] ?? null;
        $documentType = $data['document_type'] ?? $this->extractDocumentType($data['title']);
        
        // If we have type code but no full type name, derive it
        if ($documentTypeCode && $documentType === 'Lainnya') {
            $typeMapping = [
                'uu' => 'Undang-undang',
                'pp' => 'Peraturan Pemerintah',
                'perpres' => 'Peraturan Presiden',
                'permen' => 'Peraturan Menteri',
                'keppres' => 'Keputusan Presiden',
                'kepmen' => 'Keputusan Menteri',
                'inpres' => 'Instruksi Presiden'
            ];
            $documentType = $typeMapping[$documentTypeCode] ?? 'Lainnya';
        }
        
        $cleanData = [
            'title' => $this->cleanText($data['title']),
            'document_type' => $documentType,
            'document_number' => $data['document_number'] ?? null,
            'issue_year' => $data['issue_year'] ?? null,
            'source_url' => $data['source_url'],
            'pdf_url' => $data['pdf_url'] ?? null,
            'full_text' => $data['full_text'] ?? $data['title'],
            'document_source_id' => $this->source->id,
            'status' => 'active',
            'metadata' => $data['metadata'] ?? [],
            'tik_relevance_score' => $data['tik_relevance_score'] ?? 0,
            'tik_keywords' => $data['tik_keywords'] ?? [],
            'is_tik_related' => $data['is_tik_related'] ?? false,
            'document_type_code' => $documentTypeCode,
            'checksum' => md5($data['title'] . ($data['document_number'] ?? '') . ($data['issue_year'] ?? ''))
        ];
        
        Log::info("Saving document with type_code: {$documentTypeCode}, type: {$documentType}");
        
        return $this->saveDocument($cleanData);
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

    // FIX: Implement required abstract methods from BaseScraper
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

    public function debugPdfExtraction(string $url): array
    {
        Log::info("=== DEBUG PDF EXTRACTION ===");
        Log::info("URL: {$url}");
        
        $documentData = $this->enhancedScraper->scrapeWithStrategies($url, ['basic']);
        
        if ($documentData) {
            Log::info("Raw extracted data:");
            Log::info("- Title: " . ($documentData['title'] ?? 'None'));
            Log::info("- PDF URL: " . ($documentData['pdf_url'] ?? 'None'));
            Log::info("- Source URL: " . ($documentData['source_url'] ?? 'None'));
            
            $enrichedData = $this->applyFiltering($documentData, $url);
            if ($enrichedData) {
                Log::info("Enriched data:");
                Log::info("- PDF URL: " . ($enrichedData['pdf_url'] ?? 'None'));
            }
        } else {
            Log::info("No document data extracted");
        }
        
        return $documentData ?? [];
    }

    private function extractDocumentType(string $title): string
    {
        $titleLower = strtolower($title);
        
        // Enhanced patterns for Indonesian document types
        $types = [
            'uu' => [
                'patterns' => ['undang-undang', 'uu no', 'uu nomor', 'law no'],
                'name' => 'Undang-undang'
            ],
            'pp' => [
                'patterns' => ['peraturan pemerintah', 'pp no', 'pp nomor', 'government regulation'],
                'name' => 'Peraturan Pemerintah'
            ],
            'perpres' => [
                'patterns' => ['peraturan presiden', 'perpres no', 'perpres nomor', 'presidential regulation'],
                'name' => 'Peraturan Presiden'
            ],
            'permen' => [
                'patterns' => [
                    'peraturan menteri', 'permen', 'peraturan mentri',
                    'menteri komunikasi', 'menteri luar negeri', 'menteri dalam negeri',
                    'menteri keuangan', 'menteri kesehatan', 'menteri pendidikan',
                    'ministerial regulation'
                ],
                'name' => 'Peraturan Menteri'
            ],
            'keppres' => [
                'patterns' => ['keputusan presiden', 'keppres no', 'keppres nomor', 'presidential decree'],
                'name' => 'Keputusan Presiden'
            ],
            'kepmen' => [
                'patterns' => ['keputusan menteri', 'kepmen', 'ministerial decree'],
                'name' => 'Keputusan Menteri'
            ],
            'inpres' => [
                'patterns' => ['instruksi presiden', 'inpres no', 'inpres nomor', 'presidential instruction'],
                'name' => 'Instruksi Presiden'
            ]
        ];
        
        foreach ($types as $code => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (stripos($titleLower, $pattern) !== false) {
                    Log::info("Document type extracted from title: {$config['name']} (code: {$code}) via pattern: {$pattern}");
                    return $config['name'];
                }
            }
        }
        
        Log::warning("Could not extract document type from title: {$title}");
        return 'Lainnya';
    }
}