<?php
// app/Services/Scrapers/MultiSourceLegalScraper.php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

class MultiSourceLegalScraper
{
    private array $sources = [
        'peraturan_go_id' => [
            'name' => 'Database Peraturan Indonesia',
            'base_url' => 'https://peraturan.go.id',
            'priority' => 1,
            'categories' => ['uu', 'perpres', 'pp', 'permen'],
            'url_patterns' => [
                'uu' => '/id/uu-no-{number}-tahun-{year}',
                'perpres' => '/id/perpres-no-{number}-tahun-{year}',
                'pp' => '/id/pp-no-{number}-tahun-{year}',
            ]
        ],
        'peraturan_bpk' => [
            'name' => 'Database Peraturan BPK',
            'base_url' => 'https://peraturan.bpk.go.id',
            'priority' => 2,
            'reliable' => true
        ],
        'jdih_kemlu' => [
            'name' => 'JDIH Kemlu',
            'base_url' => 'https://jdih.kemlu.go.id',
            'priority' => 3,
            'focus' => 'diplomatic_laws'
        ],
        'jdih_kemnaker' => [
            'name' => 'JDIH Kemnaker', 
            'base_url' => 'https://jdih.kemnaker.go.id',
            'priority' => 4,
            'focus' => 'labor_laws'
        ],
        'jdih_kominfo' => [
            'name' => 'JDIH Kominfo',
            'base_url' => 'https://jdih.kominfo.go.id',
            'priority' => 2,
            'focus' => 'it_telecom_laws'
        ]
    ];

    private EnhancedDocumentScraper $scraper;

    public function __construct()
    {
        $this->scraper = new EnhancedDocumentScraper([
            'delay_min' => 4,
            'delay_max' => 10,
            'timeout' => 45,
            'retries' => 2
        ]);
    }

    public function scrapeMultipleSources(array $searchTerms = [], int $limit = 50): array
    {
        $allDocuments = collect();
        
        foreach ($this->sources as $sourceKey => $source) {
            Log::info("Scraping from: {$source['name']}");
            
            try {
                $documents = $this->scrapeFromSource($sourceKey, $source, $searchTerms, $limit);
                
                if (!empty($documents)) {
                    $allDocuments = $allDocuments->merge($documents);
                    Log::info("Retrieved " . count($documents) . " documents from {$source['name']}");
                }
                
                // Stop early if we have enough documents
                if ($allDocuments->count() >= $limit) {
                    break;
                }
                
                // Respectful delay between sources
                sleep(5);
                
            } catch (\Exception $e) {
                Log::error("Failed to scrape {$source['name']}: {$e->getMessage()}");
                continue;
            }
        }
        
        return $this->deduplicateAndPrioritize($allDocuments, $limit);
    }

    private function scrapeFromSource(string $sourceKey, array $source, array $searchTerms, int $limit): array
    {
        switch ($sourceKey) {
            case 'peraturan_go_id':
                return $this->scrapePeraturanGoId($source, $searchTerms, $limit);
            case 'peraturan_bpk':
                return $this->scrapePeraturanBpk($source, $searchTerms, $limit);
            case 'jdih_kemlu':
                return $this->scrapeJdihKemlu($source, $searchTerms, $limit);
            case 'jdih_kemnaker':
                return $this->scrapeJdihKemnaker($source, $searchTerms, $limit);
            case 'jdih_kominfo':
                return $this->scrapeJdihKominfo($source, $searchTerms, $limit);
            default:
                return [];
        }
    }

    private function scrapePeraturanGoId(array $source, array $searchTerms, int $limit): array
    {
        $documents = [];
        
        // Strategy 1: Use category pages with working URL patterns
        $categoryUrls = [
            'https://peraturan.go.id/uu?tahun=2024',
            'https://peraturan.go.id/uu?tahun=2023', 
            'https://peraturan.go.id/perpres?tahun=2024',
            'https://peraturan.go.id/pp?tahun=2024'
        ];
        
        foreach ($categoryUrls as $categoryUrl) {
            $html = $this->makeRequest($categoryUrl);
            if ($html) {
                $documentUrls = $this->extractDocumentUrls($html, $source['base_url']);
                
                foreach (array_slice($documentUrls, 0, 10) as $docUrl) {
                    $docData = $this->scraper->scrapeWithStrategies($docUrl, ['stealth', 'basic']);
                    
                    if ($docData) {
                        $docData['source'] = 'peraturan.go.id';
                        $docData['category'] = $this->extractCategoryFromUrl($docUrl);
                        $documents[] = $docData;
                        
                        if (count($documents) >= $limit) break 2;
                    }
                    
                    sleep(3);
                }
            }
        }
        
        // Strategy 2: Generate URLs for known recent documents
        if (count($documents) < $limit) {
            $recentUrls = $this->generateRecentDocumentUrls();
            
            foreach ($recentUrls as $url) {
                $docData = $this->scraper->scrapeWithStrategies($url, ['stealth', 'basic']);
                
                if ($docData) {
                    $docData['source'] = 'peraturan.go.id';
                    $documents[] = $docData;
                    
                    if (count($documents) >= $limit) break;
                }
                
                sleep(3);
            }
        }
        
        return $documents;
    }

    private function scrapePeraturanBpk(array $source, array $searchTerms, int $limit): array
    {
        $documents = [];
        $baseUrl = $source['base_url'];
        
        // BPK database is usually more accessible
        $searchUrl = "{$baseUrl}/Home/Pencarian";
        $listUrl = "{$baseUrl}/Home/PeraturanTerbaru";
        
        foreach ([$listUrl, $searchUrl] as $url) {
            $html = $this->makeRequest($url);
            if ($html) {
                $docUrls = $this->extractDocumentUrls($html, $baseUrl);
                
                foreach (array_slice($docUrls, 0, 15) as $docUrl) {
                    $docData = $this->scraper->scrapeWithStrategies($docUrl, ['basic', 'stealth']);
                    
                    if ($docData) {
                        $docData['source'] = 'peraturan.bpk.go.id';
                        $docData['verified_by'] = 'BPK';
                        $documents[] = $docData;
                        
                        if (count($documents) >= $limit) break 2;
                    }
                    
                    sleep(2);
                }
            }
        }
        
        return $documents;
    }

    private function scrapeJdihKemlu(array $source, array $searchTerms, int $limit): array
    {
        $documents = [];
        $baseUrl = $source['base_url'];
        
        // Focus on international/diplomatic regulations
        $diplomaticUrls = [
            "{$baseUrl}/portal/category/peraturan-menteri",
            "{$baseUrl}/portal/category/keputusan-menteri",
            "{$baseUrl}/portal/search?q=teknologi+informasi"
        ];
        
        foreach ($diplomaticUrls as $url) {
            $html = $this->makeRequest($url);
            if ($html) {
                $docUrls = $this->extractDocumentUrls($html, $baseUrl);
                
                foreach (array_slice($docUrls, 0, 10) as $docUrl) {
                    $docData = $this->scraper->scrapeWithStrategies($docUrl, ['basic', 'mobile']);
                    
                    if ($docData) {
                        $docData['source'] = 'jdih.kemlu.go.id';
                        $docData['category'] = 'diplomatic';
                        $documents[] = $docData;
                        
                        if (count($documents) >= $limit) break 2;
                    }
                    
                    sleep(3);
                }
            }
        }
        
        return $documents;
    }

    private function scrapeJdihKemnaker(array $source, array $searchTerms, int $limit): array
    {
        $documents = [];
        $baseUrl = $source['base_url'];
        
        // Labor and employment regulations
        $laborUrls = [
            "{$baseUrl}/peraturan/terbaru",
            "{$baseUrl}/search?kategori=peraturan-menteri"
        ];
        
        foreach ($laborUrls as $url) {
            $html = $this->makeRequest($url);
            if ($html) {
                $docUrls = $this->extractDocumentUrls($html, $baseUrl);
                
                foreach (array_slice($docUrls, 0, 10) as $docUrl) {
                    $docData = $this->scraper->scrapeWithStrategies($docUrl, ['basic']);
                    
                    if ($docData) {
                        $docData['source'] = 'jdih.kemnaker.go.id';
                        $docData['category'] = 'labor';
                        $documents[] = $docData;
                        
                        if (count($documents) >= $limit) break 2;
                    }
                    
                    sleep(2);
                }
            }
        }
        
        return $documents;
    }

    private function scrapeJdihKominfo(array $source, array $searchTerms, int $limit): array
    {
        $documents = [];
        $baseUrl = $source['base_url'];
        
        // IT and telecommunications regulations - perfect for TIK catalog
        $itUrls = [
            "{$baseUrl}/peraturan-menteri/terbaru",
            "{$baseUrl}/search?q=teknologi+informasi",
            "{$baseUrl}/search?q=elektronik",
            "{$baseUrl}/kategori/telekomunikasi"
        ];
        
        foreach ($itUrls as $url) {
            $html = $this->makeRequest($url);
            if ($html) {
                $docUrls = $this->extractDocumentUrls($html, $baseUrl);
                
                foreach (array_slice($docUrls, 0, 15) as $docUrl) {
                    $docData = $this->scraper->scrapeWithStrategies($docUrl, ['basic', 'stealth']);
                    
                    if ($docData) {
                        $docData['source'] = 'jdih.kominfo.go.id';
                        $docData['category'] = 'it_telecom';
                        $docData['relevance_score'] = $this->calculateTikRelevance($docData['title'] ?? '');
                        $documents[] = $docData;
                        
                        if (count($documents) >= $limit) break 2;
                    }
                    
                    sleep(2);
                }
            }
        }
        
        return $documents;
    }

    private function generateRecentDocumentUrls(): array
    {
        $urls = [];
        $currentYear = date('Y');
        $lastYear = $currentYear - 1;
        
        // Generate URLs for recent IT-related laws
        $patterns = [
            "https://peraturan.go.id/id/uu-no-{number}-tahun-{year}",
            "https://peraturan.go.id/id/perpres-no-{number}-tahun-{year}", 
            "https://peraturan.go.id/id/pp-no-{number}-tahun-{year}"
        ];
        
        foreach ([$currentYear, $lastYear] as $year) {
            foreach ($patterns as $pattern) {
                for ($i = 1; $i <= 20; $i++) {
                    $urls[] = str_replace(['{number}', '{year}'], [$i, $year], $pattern);
                }
            }
        }
        
        return $urls;
    }

    private function makeRequest(string $url): ?string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                ])
                ->get($url);
                
            return $response->successful() ? $response->body() : null;
            
        } catch (\Exception $e) {
            Log::error("Request failed for {$url}: {$e->getMessage()}");
            return null;
        }
    }

    private function extractDocumentUrls(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);
        
        $urls = [];
        
        // Common link patterns for Indonesian legal sites
        $linkPatterns = [
            '//a[contains(@href, "/id/")]/@href',
            '//a[contains(@href, "detail")]/@href',
            '//a[contains(@href, "peraturan")]/@href',
            '//a[contains(@href, "undang")]/@href',
            '//a[contains(@href, "pp-no")]/@href',
            '//a[contains(@href, "perpres")]/@href'
        ];
        
        foreach ($linkPatterns as $pattern) {
            $nodes = $xpath->query($pattern);
            foreach ($nodes as $node) {
                $href = $node->nodeValue;
                if ($this->isValidLegalDocumentUrl($href)) {
                    $urls[] = $this->resolveUrl($href, $baseUrl);
                }
            }
        }
        
        return array_unique($urls);
    }

    private function isValidLegalDocumentUrl(string $url): bool
    {
        $validPatterns = [
            '/\/id\/uu-no-\d+/',
            '/\/id\/perpres-no-\d+/',
            '/\/id\/pp-no-\d+/',
            '/detail.*peraturan/',
            '/peraturan.*\d{4}/',
            '/undang.*tahun.*\d{4}/'
        ];
        
        foreach ($validPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        return false;
    }

    private function resolveUrl(string $url, string $baseUrl): string
    {
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    private function extractCategoryFromUrl(string $url): string
    {
        if (preg_match('/\/(uu|perpres|pp|permen)-/', $url, $matches)) {
            return $matches[1];
        }
        
        return 'unknown';
    }

    private function calculateTikRelevance(string $title): int
    {
        $tikKeywords = [
            'teknologi informasi' => 10,
            'elektronik' => 8,
            'digital' => 7,
            'cyber' => 9,
            'data' => 6,
            'sistem informasi' => 9,
            'telekomunikasi' => 8,
            'internet' => 7,
            'komputerisasi' => 8
        ];
        
        $score = 0;
        $titleLower = strtolower($title);
        
        foreach ($tikKeywords as $keyword => $points) {
            if (stripos($titleLower, $keyword) !== false) {
                $score += $points;
            }
        }
        
        return $score;
    }

    private function deduplicateAndPrioritize(Collection $documents, int $limit): array
    {
        return $documents
            ->unique(function ($doc) {
                return $doc['title'] ?? $doc['source_url'] ?? '';
            })
            ->sortByDesc(function ($doc) {
                return ($doc['relevance_score'] ?? 0) + ($doc['source'] === 'peraturan.go.id' ? 5 : 0);
            })
            ->take($limit)
            ->values()
            ->toArray();
    }
}