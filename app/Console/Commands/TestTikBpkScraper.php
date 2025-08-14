<?php
// app/Console/Commands/TestTikBpkScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\TikEnhancedBpkScraper;
use App\Services\TikTermsService;
use App\Models\DocumentSource;
use Illuminate\Support\Facades\Log;

class TestTikBpkScraper extends Command
{
    protected $signature = 'scraper:test-tik-bpk 
                           {--limit=15 : Limit number of TIK documents to scrape}
                           {--min-score=5 : Minimum TIK relevance score}
                           {--search-only : Only test TIK search functionality}
                           {--dry-run : Test without saving to database}
                           {--show-keywords : Show TIK keywords found}';

    protected $description = 'Test the TIK-enhanced BPK scraper with peraturan.bpk.go.id';

    public function handle(): int
    {
        $this->info('🎯 Testing TIK-Enhanced BPK Scraper');
        $this->newLine();

        // Create or get TIK BPK source
        $source = $this->getOrCreateTikBpkSource();
        
        if (!$source) {
            $this->error('❌ Failed to create TIK BPK document source');
            return 1;
        }

        $this->info("📋 Using source: {$source->name} ({$source->base_url})");
        $this->info("🎯 TIK Score Threshold: {$this->option('min-score')}");
        $this->newLine();

        try {
            $scraper = new TikEnhancedBpkScraper($source);
            $scraper->setMinTikScore((int) $this->option('min-score'));
            
            if ($this->option('search-only')) {
                return $this->testTikSearchFunctionality($scraper);
            }

            return $this->testTikFocusedScraping($scraper);

        } catch (\Exception $e) {
            $this->error("❌ TIK scraper test failed: {$e->getMessage()}");
            Log::channel('legal-documents-errors')->error("TIK BPK scraper test failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function getOrCreateTikBpkSource(): ?DocumentSource
    {
        // Try to find existing TIK BPK source
        $source = DocumentSource::where('name', 'bpk_tik')
            ->orWhere('name', 'peraturan_bpk_go_id_tik')
            ->first();

        if ($source) {
            return $source;
        }

        // Create new TIK BPK source
        try {
            return DocumentSource::create([
                'name' => 'bpk_tik',
                'display_name' => 'BPK Legal Database (TIK-Enhanced)',
                'base_url' => 'https://peraturan.bpk.go.id',
                'status' => 'active',
                'config' => [
                    'request_delay' => 3,
                    'timeout' => 45,
                    'max_documents_per_run' => 50,
                    'min_tik_score' => (int) $this->option('min-score'),
                    'tik_focused' => true,
                    'supported_document_types' => [
                        'undang-undang',
                        'peraturan-pemerintah', 
                        'peraturan-presiden',
                        'peraturan-menteri'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            $this->error("Failed to create TIK BPK source: {$e->getMessage()}");
            return null;
        }
    }

    private function testTikSearchFunctionality($scraper): int
    {
        $this->info('🔍 Testing TIK-Enhanced BPK Search');
        $this->newLine();

        // Test TIK search queries
        $tikQueries = [
            'undang-undang informasi transaksi elektronik',
            'UU data pribadi 27 2022',
            'sistem pemerintahan berbasis elektronik',
            'keamanan siber',
            'teknologi informasi 2024'
        ];

        $totalFound = 0;
        $tikRelevantFound = 0;

        foreach ($tikQueries as $query) {
            $this->line("🔎 Searching: {$query}");
            
            try {
                // Use reflection to access private method for testing
                $reflection = new \ReflectionClass($scraper);
                $method = $reflection->getMethod('searchBpkForDocuments');
                $method->setAccessible(true);
                
                $urls = $method->invoke($scraper, $query);
                
                $this->info("   ✅ Found " . count($urls) . " document URLs");
                
                // Test TIK filtering on found URLs
                $tikUrls = 0;
                if (count($urls) > 0) {
                    foreach (array_slice($urls, 0, 3) as $url) {
                        $this->line("   📄 " . $url);
                        
                        // Quick TIK check based on URL
                        if ($this->urlLooksLikeTik($url)) {
                            $tikUrls++;
                        }
                    }
                    
                    if (count($urls) > 3) {
                        $this->line("   ... and " . (count($urls) - 3) . " more");
                    }
                    
                    $this->line("   🎯 Likely TIK-related: {$tikUrls}/" . min(3, count($urls)));
                }
                
                $totalFound += count($urls);
                $tikRelevantFound += $tikUrls;
                $this->newLine();
                
                // Rate limiting
                sleep(2);
                
            } catch (\Exception $e) {
                $this->error("   ❌ Search failed: {$e->getMessage()}");
            }
        }

        $this->info("📊 TIK Search Results:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total URLs Found', $totalFound],
                ['Likely TIK URLs', $tikRelevantFound],
                ['TIK Relevance Rate', $totalFound > 0 ? round(($tikRelevantFound / $totalFound) * 100, 1) . '%' : 'N/A']
            ]
        );

        if ($totalFound > 0) {
            $this->info("✨ TIK-enhanced BPK search is working!");
            
            if ($tikRelevantFound > 0) {
                $this->info("🎯 Found TIK-relevant documents - the filtering is working!");
            } else {
                $this->warn("⚠️  No obviously TIK-relevant URLs found. May need to adjust search terms.");
            }
            
            return 0;
        } else {
            $this->warn("⚠️  No URLs found. Check BPK site or search patterns.");
            return 1;
        }
    }

    private function testTikFocusedScraping($scraper): int
    {
        $this->info('🚀 Testing TIK-Focused BPK Scraping');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');
        $showKeywords = $this->option('show-keywords');

        if ($isDryRun) {
            $this->warn('📋 DRY RUN - No documents will be saved');
        }

        $this->info("🎯 Target: {$limit} TIK documents");
        $this->info("📊 Min TIK Score: {$this->option('min-score')}");
        $this->newLine();

        $startTime = microtime(true);
        
        try {
            // Run the TIK scraper
            $documents = $scraper->scrape();
            
            $duration = round(microtime(true) - $startTime, 2);
            $tikCount = count($documents);
            
            $this->newLine();
            $this->info("📊 TIK BPK SCRAPING RESULTS");
            
            if ($tikCount > 0) {
                // Calculate average TIK score
                $avgTikScore = collect($documents)->avg('tik_relevance_score') ?? 0;
                $maxTikScore = collect($documents)->max('tik_relevance_score') ?? 0;
                
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Duration', "{$duration} seconds"],
                        ['TIK Documents Found', $tikCount],
                        ['Average TIK Score', round($avgTikScore, 1)],
                        ['Highest TIK Score', $maxTikScore],
                        ['Avg Time per TIK Doc', round($duration / $tikCount, 2) . 's'],
                        ['Success Rate', '✅ TIK Filtering Active']
                    ]
                );

                $this->newLine();
                $this->info("📄 TIK DOCUMENTS FOUND:");
                
                foreach (array_slice($documents, 0, 5) as $index => $doc) {
                    $score = $doc->tik_relevance_score ?? 0;
                    $category = $doc->document_category ?? 'Unknown';
                    
                    $this->line("   " . ($index + 1) . ". {$doc->title}");
                    $this->line("      📋 Type: {$doc->document_type}");
                    $this->line("      🎯 TIK Score: {$score}");
                    $this->line("      📂 Category: {$category}");
                    $this->line("      🔗 URL: {$doc->source_url}");
                    
                    if ($showKeywords && !empty($doc->tik_keywords)) {
                        $keywordList = collect($doc->tik_keywords)->pluck('term')->take(5)->implode(', ');
                        $this->line("      🏷️  Keywords: {$keywordList}");
                    }
                    
                    $this->newLine();
                }

                if (count($documents) > 5) {
                    $this->line("   ... and " . (count($documents) - 5) . " more TIK documents");
                }

                $this->newLine();
                $this->info("✅ TIK-enhanced BPK scraper is working successfully!");
                
                // Show TIK keyword analysis
                if ($showKeywords) {
                    $this->showTikKeywordAnalysis($documents);
                }
                
                if (!$isDryRun) {
                    $this->info("💾 TIK documents have been saved to the database");
                    $this->line("Run: php artisan docs:update-real-urls to verify URLs");
                }
                
                return 0;
            } else {
                $this->error("❌ No TIK documents were found");
                $this->line("Possible issues:");
                $this->line("• TIK score threshold too high (current: {$this->option('min-score')})");
                $this->line("• TIK keywords need adjustment");
                $this->line("• BPK search patterns not finding TIK content");
                $this->line("• Network/rate limiting issues");
                
                $this->newLine();
                $this->line("💡 Try lowering the threshold: --min-score=3");
                
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ TIK scraping failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function urlLooksLikeTik(string $url): bool
    {
        $urlLower = strtolower($url);
        
        $tikPatterns = [
            'informasi', 'elektronik', 'data', 'digital', 'sistem', 
            'teknologi', 'komunikasi', 'siber', 'cyber'
        ];
        
        foreach ($tikPatterns as $pattern) {
            if (str_contains($urlLower, $pattern)) {
                return true;
            }
        }
        
        return false;
    }

    private function showTikKeywordAnalysis($documents): void
    {
        $this->newLine();
        $this->info("🏷️  TIK KEYWORD ANALYSIS:");
        
        $allKeywords = [];
        
        foreach ($documents as $doc) {
            if (!empty($doc->tik_keywords)) {
                foreach ($doc->tik_keywords as $keyword) {
                    $term = $keyword['term'] ?? $keyword;
                    if (!isset($allKeywords[$term])) {
                        $allKeywords[$term] = 0;
                    }
                    $allKeywords[$term]++;
                }
            }
        }
        
        // Sort by frequency
        arsort($allKeywords);
        
        $this->table(
            ['TIK Keyword', 'Frequency'],
            array_slice(array_map(function($term, $count) {
                return [$term, $count];
            }, array_keys($allKeywords), $allKeywords), 0, 10)
        );
    }
}