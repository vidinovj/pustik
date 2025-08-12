<?php
// app/Console/Commands/TikFocusedScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\EnhancedDocumentScraper;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Http;

class TikFocusedScraper extends Command
{
    protected $signature = 'scraper:tik-focused 
                           {--limit=30 : Number of documents to retrieve}
                           {--years=2023,2024,2025 : Years to focus on}';

    protected $description = 'Scrape TIK/IT focused legal documents from Indonesian government sources';

    private array $tikSources = [
        'kominfo' => [
            'base_url' => 'https://jdih.kominfo.go.id',
            'search_paths' => [
                '/search?q=teknologi+informasi',
                '/search?q=sistem+elektronik',
                '/search?q=data+pribadi',
                '/search?q=cyber+security',
                '/kategori/telekomunikasi',
                '/peraturan-menteri'
            ]
        ],
        'peraturan_go_id' => [
            'base_url' => 'https://peraturan.go.id',
            'tik_urls' => [
                // Known TIK regulations with correct URL format
                '/id/uu-no-11-tahun-2008',  // ITE Law (if exists in new format)
                '/id/uu-no-19-tahun-2016',  // ITE Amendment (if exists)
                '/id/pp-no-71-tahun-2019',  // E-Government (if exists)
            ],
            'search_paths' => [
                '/search?q=teknologi+informasi',
                '/search?q=elektronik',
                '/search?q=cyber'
            ]
        ],
        'bssn' => [
            'base_url' => 'https://jdih.bssn.go.id',
            'focus' => 'cyber_security'
        ]
    ];

    private array $tikKeywords = [
        'teknologi informasi' => 10,
        'sistem elektronik' => 9,
        'transaksi elektronik' => 9,
        'data pribadi' => 8,
        'cyber security' => 9,
        'keamanan siber' => 9,
        'telekomunikasi' => 8,
        'informatika' => 7,
        'digital' => 6,
        'internet' => 6,
        'komputer' => 5,
        'jaringan' => 5,
        'e-government' => 8,
        'e-commerce' => 7,
        'blockchain' => 6,
        'artificial intelligence' => 8,
        'big data' => 7
    ];

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $years = explode(',', $this->option('years'));

        $this->info("🔍 TIK-FOCUSED LEGAL DOCUMENT SCRAPER");
        $this->info("====================================");
        $this->info("Target: IT/Technology regulations from Indonesian government");
        $this->info("Years: " . implode(', ', $years));
        $this->newLine();

        $scraper = new EnhancedDocumentScraper([
            'delay_min' => 2,
            'delay_max' => 5,
            'timeout' => 45
        ]);

        $allDocuments = [];
        
        // Strategy 1: Target Kominfo JDIH (most relevant for TIK)
        $this->info("📡 Scraping from Kominfo JDIH...");
        $kominfoDocuments = $this->scrapeKominfo($scraper);
        $allDocuments = array_merge($allDocuments, $kominfoDocuments);
        
        $this->info("Found " . count($kominfoDocuments) . " documents from Kominfo");
        $this->newLine();

        // Strategy 2: Search peraturan.go.id with TIK terms
        if (count($allDocuments) < $limit) {
            $this->info("🏛️ Searching peraturan.go.id for TIK regulations...");
            $peraturanDocuments = $this->searchPeraturanGoId($scraper, $years);
            $allDocuments = array_merge($allDocuments, $peraturanDocuments);
            
            $this->info("Found " . count($peraturanDocuments) . " additional documents");
            $this->newLine();
        }

        // Strategy 3: Try specific known TIK regulation URLs
        if (count($allDocuments) < $limit) {
            $this->info("🎯 Checking specific known TIK regulation URLs...");
            $specificDocuments = $this->scrapeSpecificTikUrls($scraper);
            $allDocuments = array_merge($allDocuments, $specificDocuments);
            
            $this->info("Found " . count($specificDocuments) . " specific TIK documents");
            $this->newLine();
        }

        // Process and score documents
        $scoredDocuments = $this->scoreAndFilterTikDocuments($allDocuments);
        $finalDocuments = array_slice($scoredDocuments, 0, $limit);

        $this->displayTikResults($finalDocuments);

        if (!empty($finalDocuments) && $this->confirm('Save TIK-focused results to database?', true)) {
            $this->saveTikDocuments($finalDocuments);
        }

        return count($finalDocuments) > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function scrapeKominfo($scraper): array
    {
        $documents = [];
        $kominfoConfig = $this->tikSources['kominfo'];
        
        foreach ($kominfoConfig['search_paths'] as $path) {
            $url = $kominfoConfig['base_url'] . $path;
            $this->line("  Checking: {$path}");
            
            try {
                // First get the search/category page
                $html = $this->makeBasicRequest($url);
                if ($html) {
                    $docUrls = $this->extractDocumentUrls($html, $kominfoConfig['base_url']);
                    
                    foreach (array_slice($docUrls, 0, 5) as $docUrl) {
                        $docData = $scraper->scrapeWithStrategies($docUrl, ['basic', 'stealth']);
                        
                        if ($docData && $this->isTikRelevant($docData['title'] ?? '')) {
                            $docData['source'] = 'jdih.kominfo.go.id';
                            $docData['extraction_method'] = 'tik_focused';
                            $documents[] = $docData;
                        }
                        
                        sleep(2);
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
            }
        }
        
        return $documents;
    }

    private function searchPeraturanGoId($scraper, array $years): array
    {
        $documents = [];
        
        // Search with TIK-specific terms
        $searchTerms = [
            'teknologi informasi',
            'sistem elektronik', 
            'transaksi elektronik',
            'cyber security',
            'telekomunikasi'
        ];
        
        foreach ($searchTerms as $term) {
            $searchUrl = "https://peraturan.go.id/search?q=" . urlencode($term);
            $this->line("  Searching: {$term}");
            
            try {
                $html = $this->makeBasicRequest($searchUrl);
                if ($html) {
                    $docUrls = $this->extractDocumentUrls($html, 'https://peraturan.go.id');
                    
                    foreach (array_slice($docUrls, 0, 5) as $docUrl) {
                        $docData = $scraper->scrapeWithStrategies($docUrl, ['stealth', 'basic']);
                        
                        if ($docData) {
                            $docData['source'] = 'peraturan.go.id';
                            $docData['search_term'] = $term;
                            $documents[] = $docData;
                        }
                        
                        sleep(3);
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Search failed: {$e->getMessage()}");
            }
        }
        
        return $documents;
    }

    private function scrapeSpecificTikUrls($scraper): array
    {
        $documents = [];
        
        // Known TIK regulation patterns - trying both old and new URL formats
        $knownTikUrls = [
            // Try new format first
            'https://peraturan.go.id/id/uu-no-11-tahun-2008',
            'https://peraturan.go.id/id/uu-no-19-tahun-2016', 
            'https://peraturan.go.id/id/pp-no-71-tahun-2019',
            
            // Alternative sources for TIK regulations
            'https://jdih.kominfo.go.id/produk_hukum/view/id/759',
            'https://peraturan.bpk.go.id/Details/122894/uu-no-11-tahun-2008',
        ];
        
        foreach ($knownTikUrls as $url) {
            $this->line("  Testing: " . basename($url));
            
            try {
                $docData = $scraper->scrapeWithStrategies($url, ['stealth', 'basic', 'mobile']);
                
                if ($docData) {
                    $docData['source'] = 'specific_tik_url';
                    $docData['is_known_tik_regulation'] = true;
                    $documents[] = $docData;
                }
            } catch (\Exception $e) {
                // Silently continue - these URLs might not exist
            }
            
            sleep(2);
        }
        
        return $documents;
    }

    private function makeBasicRequest(string $url): ?string
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
            return null;
        }
    }

    private function extractDocumentUrls(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);
        
        $urls = [];
        
        $linkPatterns = [
            '//a[contains(@href, "detail")]/@href',
            '//a[contains(@href, "/id/")]/@href',
            '//a[contains(@href, "peraturan")]/@href',
            '//a[contains(@href, "produk_hukum")]/@href'
        ];
        
        foreach ($linkPatterns as $pattern) {
            $nodes = $xpath->query($pattern);
            foreach ($nodes as $node) {
                $href = $node->nodeValue;
                if ($this->isValidDocumentUrl($href)) {
                    $urls[] = $this->resolveUrl($href, $baseUrl);
                }
            }
        }
        
        return array_unique($urls);
    }

    private function isValidDocumentUrl(string $url): bool
    {
        $validPatterns = [
            '/detail/',
            '/id\/uu-/',
            '/id\/pp-/', 
            '/id\/perpres-/',
            '/produk_hukum/',
            '/peraturan-menteri/'
        ];
        
        foreach ($validPatterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
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

    private function isTikRelevant(string $title): bool
    {
        $titleLower = strtolower($title);
        
        foreach (array_keys($this->tikKeywords) as $keyword) {
            if (strpos($titleLower, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function scoreAndFilterTikDocuments(array $documents): array
    {
        $scoredDocs = [];
        
        foreach ($documents as $doc) {
            $score = $this->calculateTikScore($doc['title'] ?? '');
            
            if ($score > 0) {
                $doc['tik_relevance_score'] = $score;
                $doc['tik_keywords'] = $this->extractTikKeywords($doc['title'] ?? '');
                $doc['is_tik_related'] = true;
                $scoredDocs[] = $doc;
            }
        }
        
        // Sort by TIK relevance score
        usort($scoredDocs, function($a, $b) {
            return ($b['tik_relevance_score'] ?? 0) <=> ($a['tik_relevance_score'] ?? 0);
        });
        
        return $scoredDocs;
    }

    private function calculateTikScore(string $title): int
    {
        $score = 0;
        $titleLower = strtolower($title);
        
        foreach ($this->tikKeywords as $keyword => $points) {
            if (strpos($titleLower, $keyword) !== false) {
                $score += $points;
            }
        }
        
        return $score;
    }

    private function extractTikKeywords(string $title): array
    {
        $foundKeywords = [];
        $titleLower = strtolower($title);
        
        foreach ($this->tikKeywords as $keyword => $points) {
            if (strpos($titleLower, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }
        
        return $foundKeywords;
    }

    private function displayTikResults(array $documents): void
    {
        if (empty($documents)) {
            $this->error("❌ No TIK-relevant documents found");
            $this->line("Try expanding search terms or checking different sources");
            return;
        }

        $this->info("🎯 TIK-FOCUSED RESULTS");
        $this->table(
            ['Metric', 'Value'],
            [
                ['TIK Documents Found', count($documents)],
                ['Avg TIK Score', count($documents) > 0 ? round(collect($documents)->avg('tik_relevance_score'), 1) : 0],
                ['Sources Used', count(array_unique(array_column($documents, 'source')))],
                ['With PDF Links', count(array_filter($documents, fn($d) => !empty($d['pdf_url'])))]
            ]
        );

        $this->newLine();
        $this->info("📋 TOP TIK DOCUMENTS:");

        foreach (array_slice($documents, 0, 5) as $i => $doc) {
            $score = $doc['tik_relevance_score'] ?? 0;
            $keywords = implode(', ', $doc['tik_keywords'] ?? []);
            
            $this->line(($i + 1) . ". " . ($doc['title'] ?? 'No title'));
            $this->line("   🎯 TIK Score: {$score}");
            $this->line("   🏷️ Keywords: {$keywords}");
            $this->line("   📍 Source: " . ($doc['source'] ?? 'Unknown'));
            
            if (!empty($doc['pdf_url'])) {
                $this->line("   📎 PDF: " . $doc['pdf_url']);
            }
            
            $this->newLine();
        }
    }

    private function saveTikDocuments(array $documents): void
    {
        $this->info("💾 Saving TIK documents to database...");
        $saved = 0;

        foreach ($documents as $doc) {
            try {
                LegalDocument::updateOrCreate(
                    ['source_url' => $doc['source_url']],
                    [
                        'title' => substr($doc['title'] ?? 'Unknown Title', 0, 500), // Truncate if too long
                        'document_number' => substr($doc['document_number'] ?? '', 0, 500),
                        'issue_date' => $doc['issue_date'] ?? null,
                        'pdf_url' => $doc['pdf_url'] ?? null,
                        'tik_relevance_score' => $doc['tik_relevance_score'] ?? 0,
                        'tik_keywords' => $doc['tik_keywords'] ?? [],
                        'is_tik_related' => true,
                        'document_category' => 'tik_regulation',
                        'metadata' => $this->normalizeScrapedMetadata($doc),
                        'source_id' => 1
                    ]
                );
                $saved++;
            } catch (\Exception $e) {
                $this->error("Failed to save: " . substr($e->getMessage(), 0, 100));
            }
        }

        $this->info("✅ Saved {$saved} TIK documents to database");
    }

    private function normalizeScrapedMetadata(array $scrapedData): array
    {
        $normalized = [
            'agency' => null,
            'category' => null,
            'importance' => null,
            'summary' => null,
            'keywords' => [],
            'satker_kemlu_terkait' => null,
            'kl_external_terkait' => null,
            'tanggal_berakhir' => null,
            'extraction_method' => null,
            'entry_date' => null,
        ];

        // Map fields from scrapedData to normalized structure
        $normalized['agency'] = $scrapedData['source'] ?? null; // Use 'source' as agency
        $normalized['summary'] = $scrapedData['title'] ?? null; // Use title as summary
        $normalized['keywords'] = $scrapedData['tik_keywords'] ?? []; // Use tik_keywords
        $normalized['extraction_method'] = $scrapedData['extraction_method'] ?? 'tik_focused_scraper';
        $normalized['entry_date'] = now()->toISOString(); // Set entry date

        // Ensure keywords is always an array
        if (!is_array($normalized['keywords'])) {
            $normalized['keywords'] = [];
        }

        return $normalized;
    }
}