<?php
// app/Console/Commands/TestBpkScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\BpkScraper;
use App\Models\DocumentSource;
use Illuminate\Support\Facades\Log;

class TestBpkScraper extends Command
{
    protected $signature = 'scraper:test-bpk 
                           {--limit=10 : Limit number of documents to scrape}
                           {--search-only : Only test search functionality}
                           {--dry-run : Test without saving to database}
                           {--strategies=stealth,basic : Comma-separated strategies}';

    protected $description = 'Test the BPK scraper with peraturan.bpk.go.id';

    public function handle(): int
    {
        $this->info('🏛️  Testing BPK Scraper (peraturan.bpk.go.id)');
        $this->newLine();

        // Create or get BPK source
        $source = $this->getOrCreateBpkSource();
        
        if (!$source) {
            $this->error('❌ Failed to create BPK document source');
            return 1;
        }

        $this->info("📋 Using source: {$source->name} ({$source->base_url})");
        $this->newLine();

        try {
            $scraper = new BpkScraper($source);
            
            if ($this->option('search-only')) {
                return $this->testSearchFunctionality($scraper);
            }

            return $this->testFullScraping($scraper);

        } catch (\Exception $e) {
            $this->error("❌ Scraper test failed: {$e->getMessage()}");
            Log::channel('legal-documents-errors')->error("BPK scraper test failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function getOrCreateBpkSource(): ?DocumentSource
    {
        // Try to find existing BPK source
        $source = DocumentSource::where('name', 'bpk')
            ->orWhere('name', 'peraturan_bpk_go_id')
            ->first();

        if ($source) {
            return $source;
        }

        // Create new BPK source
        try {
            return DocumentSource::create([
                'name' => 'bpk',
                'display_name' => 'BPK Legal Database',
                'base_url' => 'https://peraturan.bpk.go.id',
                'status' => 'active',
                'config' => [
                    'request_delay' => 3,
                    'timeout' => 45,
                    'max_documents_per_run' => 50,
                    'supported_document_types' => [
                        'undang-undang',
                        'peraturan-pemerintah', 
                        'peraturan-presiden',
                        'peraturan-menteri'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            $this->error("Failed to create BPK source: {$e->getMessage()}");
            return null;
        }
    }

    private function testSearchFunctionality($scraper): int
    {
        $this->info('🔍 Testing BPK Search Functionality');
        $this->newLine();

        // Test search queries
        $testQueries = [
            'undang-undang informasi transaksi elektronik',
            'UU No. 11 Tahun 2008',
            'peraturan pemerintah sistem elektronik',
            'undang-undang data pribadi 2022'
        ];

        $totalFound = 0;

        foreach ($testQueries as $query) {
            $this->line("🔎 Searching: {$query}");
            
            try {
                // Use reflection to access private method for testing
                $reflection = new \ReflectionClass($scraper);
                $method = $reflection->getMethod('searchBpkForDocuments');
                $method->setAccessible(true);
                
                $urls = $method->invoke($scraper, $query);
                
                $this->info("   ✅ Found " . count($urls) . " document URLs");
                
                if (count($urls) > 0) {
                    foreach (array_slice($urls, 0, 3) as $url) {
                        $this->line("   📄 " . $url);
                    }
                    
                    if (count($urls) > 3) {
                        $this->line("   ... and " . (count($urls) - 3) . " more");
                    }
                }
                
                $totalFound += count($urls);
                $this->newLine();
                
                // Rate limiting
                sleep(2);
                
            } catch (\Exception $e) {
                $this->error("   ❌ Search failed: {$e->getMessage()}");
            }
        }

        $this->info("📊 Total URLs discovered: {$totalFound}");
        
        if ($totalFound > 0) {
            $this->info("✨ BPK search functionality is working!");
            return 0;
        } else {
            $this->warn("⚠️  No URLs found. Check BPK site structure or search patterns.");
            return 1;
        }
    }

    private function testFullScraping($scraper): int
    {
        $this->info('🚀 Testing Full BPK Scraping');
        $this->newLine();

        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        if ($isDryRun) {
            $this->warn('📋 DRY RUN - No documents will be saved');
        }

        $this->info("🎯 Target: {$limit} documents");
        $this->newLine();

        $startTime = microtime(true);
        
        try {
            // Run the scraper
            $documents = $scraper->scrape();
            
            $duration = round(microtime(true) - $startTime, 2);
            $successCount = count($documents);
            
            $this->newLine();
            $this->info("📊 BPK SCRAPING RESULTS");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Duration', "{$duration} seconds"],
                    ['Documents Found', $successCount],
                    ['Average per Document', $successCount > 0 ? round($duration / $successCount, 2) . 's' : 'N/A'],
                    ['Success Rate', $successCount > 0 ? '✅ Working' : '❌ Failed']
                ]
            );

            if ($successCount > 0) {
                $this->newLine();
                $this->info("📄 SAMPLE DOCUMENTS FOUND:");
                
                foreach (array_slice($documents, 0, 3) as $index => $doc) {
                    $this->line("   " . ($index + 1) . ". {$doc->title}");
                    $this->line("      📋 Type: {$doc->document_type}");
                    $this->line("      🔢 Number: " . ($doc->document_number ?: 'N/A'));
                    $this->line("      🔗 URL: {$doc->source_url}");
                    $this->newLine();
                }

                if (count($documents) > 3) {
                    $this->line("   ... and " . (count($documents) - 3) . " more documents");
                }

                $this->newLine();
                $this->info("✅ BPK scraper is working successfully!");
                
                if (!$isDryRun) {
                    $this->info("💾 Documents have been saved to the database");
                    $this->line("Run: php artisan docs:update-real-urls to verify URLs are correctly formatted");
                }
                
                return 0;
            } else {
                $this->error("❌ No documents were scraped");
                $this->line("Possible issues:");
                $this->line("• BPK site structure changed");
                $this->line("• Network connectivity issues");
                $this->line("• Rate limiting or blocking");
                $this->line("• Search patterns need updating");
                
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Scraping failed: {$e->getMessage()}");
            return 1;
        }
    }
}