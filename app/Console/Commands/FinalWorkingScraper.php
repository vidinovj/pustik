<?php
// app/Console/Commands/FinalWorkingScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\MultiSourceLegalScraper;

class FinalWorkingScraper extends Command
{
    protected $signature = 'scraper:final-working 
                           {--limit=20 : Number of documents to retrieve}
                           {--tik-focus : Focus on TIK/IT related regulations}
                           {--test-first : Test a few URLs first}';

    protected $description = 'Complete working scraper using correct URLs and multiple sources';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $tikFocus = $this->option('tik-focus');
        $testFirst = $this->option('test-first');

        $this->info("🚀 FINAL WORKING LEGAL DOCUMENT SCRAPER");
        $this->info("=========================================");
        $this->newLine();

        // Step 1: Test with working URLs first
        if ($testFirst || $this->confirm('Test a few URLs first to verify connectivity?', true)) {
            $this->testWorkingUrls();
            $this->newLine();
            
            if (!$this->confirm('URLs working? Continue with full scrape?', true)) {
                return Command::FAILURE;
            }
        }

        // Step 2: Run multi-source scraper
        $this->info("📡 Starting multi-source document retrieval...");
        
        $searchTerms = $tikFocus ? [
            'teknologi informasi',
            'sistem elektronik', 
            'data pribadi',
            'cyber security',
            'telekomunikasi'
        ] : [];

        $scraper = new MultiSourceLegalScraper();
        
        $progressBar = $this->output->createProgressBar($limit);
        $progressBar->start();
        
        try {
            $documents = $scraper->scrapeMultipleSources($searchTerms, $limit);
            $progressBar->finish();
            
            $this->newLine(2);
            $this->info("✅ Scraping completed!");
            $this->newLine();
            
            // Display results
            $this->displayResults($documents);
            
            // Save to database (optional)
            if ($this->confirm('Save results to database?', true)) {
                $this->saveToDatabase($documents);
            }
            
            // Export results
            if ($this->confirm('Export results to JSON?', true)) {
                $this->exportResults($documents);
            }
            
        } catch (\Exception $e) {
            $progressBar->finish();
            $this->newLine();
            $this->error("❌ Scraping failed: " . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function testWorkingUrls(): void
    {
        $this->info("🧪 Testing known working URLs...");
        
        $testUrls = [
            'https://peraturan.go.id/id/uu-no-1-tahun-2023',      // Working
            'https://peraturan.go.id/id/perpres-no-109-tahun-2024', // Working  
            'https://peraturan.bpk.go.id/',                      // Alternative source
        ];

        foreach ($testUrls as $url) {
            $this->line("Testing: {$url}");
            
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);
                
                if ($response->successful()) {
                    $this->info("  ✅ Accessible");
                } else {
                    $this->error("  ❌ HTTP {$response->status()}");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Failed: {$e->getMessage()}");
            }
        }
    }

    private function displayResults(array $documents): void
    {
        if (empty($documents)) {
            $this->error("❌ No documents retrieved");
            return;
        }

        $this->info("📊 SCRAPING RESULTS");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Documents', count($documents)],
                ['Sources Used', count(array_unique(array_column($documents, 'source')))],
                ['With PDF Links', count(array_filter($documents, fn($d) => !empty($d['pdf_url'])))],
                ['TIK Relevant', count(array_filter($documents, fn($d) => ($d['relevance_score'] ?? 0) > 0))]
            ]
        );

        $this->newLine();
        $this->info("📋 SAMPLE DOCUMENTS:");

        foreach (array_slice($documents, 0, 5) as $i => $doc) {
            $this->line(($i + 1) . ". " . ($doc['title'] ?? 'No title'));
            $this->line("   Source: " . ($doc['source'] ?? 'Unknown'));
            $this->line("   URL: " . ($doc['source_url'] ?? 'N/A'));
            
            if (!empty($doc['pdf_url'])) {
                $this->line("   PDF: " . $doc['pdf_url']);
            }
            
            if (!empty($doc['document_number'])) {
                $this->line("   Number: " . $doc['document_number']);
            }
            
            $this->newLine();
        }

        // Source breakdown
        $sourceStats = [];
        foreach ($documents as $doc) {
            $source = $doc['source'] ?? 'unknown';
            $sourceStats[$source] = ($sourceStats[$source] ?? 0) + 1;
        }

        $this->info("📈 BY SOURCE:");
        foreach ($sourceStats as $source => $count) {
            $this->line("• {$source}: {$count} documents");
        }
    }

    private function saveToDatabase(array $documents): void
    {
        $this->info("💾 Saving to database...");
        $saved = 0;

        foreach ($documents as $doc) {
            try {
                // Assuming you have a LegalDocument model
                \App\Models\LegalDocument::updateOrCreate(
                    ['source_url' => $doc['source_url']],
                    [
                        'title' => $doc['title'] ?? 'Unknown Title',
                        'document_number' => $doc['document_number'] ?? null,
                        'issue_date' => $doc['issue_date'] ?? null,
                        'pdf_url' => $doc['pdf_url'] ?? null,
                        'metadata' => $this->normalizeScrapedMetadata($doc),
                        'source_id' => 1 // Adjust as needed
                    ]
                );
                $saved++;
            } catch (\Exception $e) {
                $this->error("Failed to save: " . $e->getMessage());
            }
        }

        $this->info("✅ Saved {$saved} documents to database");
    }

    private function exportResults(array $documents): void
    {
        $filename = 'legal_documents_' . date('Y-m-d_H-i-s') . '.json';
        $path = storage_path('app/exports/' . $filename);
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode([
            'exported_at' => now(),
            'total_documents' => count($documents),
            'documents' => $documents
        ], JSON_PRETTY_PRINT));

        $this->info("📁 Results exported to: {$path}");
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
        $normalized['category'] = $scrapedData['category'] ?? null; // Use 'category' if available
        $normalized['summary'] = $scrapedData['title'] ?? null; // Use title as summary
        $normalized['extraction_method'] = $scrapedData['extraction_method'] ?? 'final_working_scraper';
        $normalized['entry_date'] = $scrapedData['extracted_at'] ?? now()->toISOString(); // Map extracted_at to entry_date

        // Keywords: MultiSourceLegalScraper doesn't explicitly extract keywords,
        // so we might need to infer them or leave empty for now.
        // For now, we'll leave it empty or try to extract from title.
        $normalized['keywords'] = [];
        if (isset($scrapedData['title'])) {
            // Simple keyword extraction from title (can be improved)
            $titleLower = strtolower($scrapedData['title']);
            $commonKeywords = ['teknologi informasi', 'elektronik', 'digital', 'cyber', 'data', 'sistem informasi', 'telekomunikasi', 'internet'];
            foreach ($commonKeywords as $keyword) {
                if (strpos($titleLower, $keyword) !== false) {
                    $normalized['keywords'][] = $keyword;
                }
            }
        }
        
        // Ensure keywords is always an array
        if (!is_array($normalized['keywords'])) {
            $normalized['keywords'] = [];
        }

        return $normalized;
    }
}

// Quick setup verification
class ScraperSetupChecker 
{
    public static function verify(): array 
    {
        $issues = [];
        
        // Check Node.js
        if (!shell_exec('which node')) {
            $issues[] = 'Node.js not found - install from nodejs.org';
        }
        
        // Check npm packages
        if (!file_exists('node_modules/puppeteer')) {
            $issues[] = 'Puppeteer not installed - run: npm install';
        }
        
        // Check Laravel setup
        if (!class_exists('\App\Models\LegalDocument')) {
            $issues[] = 'LegalDocument model not found - check your Laravel setup';
        }
        
        // Check connectivity
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get('https://peraturan.go.id');
            if (!$response->successful()) {
                $issues[] = 'Cannot access peraturan.go.id - check internet connection';
            }
        } catch (\Exception $e) {
            $issues[] = 'Network connectivity issue: ' . $e->getMessage();
        }
        
        return $issues;
    }
}