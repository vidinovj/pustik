<?php
// app/Console/Commands/TestEnhancedScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\EnhancedDocumentScraper;
use Illuminate\Support\Facades\Log;

class TestEnhancedScraper extends Command
{
    protected $signature = 'scraper:test-enhanced 
                           {urls?* : URLs to test}
                           {--strategies=stealth,basic,mobile : Comma-separated strategies}
                           {--limit=10 : Limit number of URLs to test}
                           {--delay=5 : Delay between requests}';

    protected $description = 'Test the enhanced document scraper with multiple strategies';

    public function handle()
    {
        $urls = $this->argument('urls') ?: $this->getDefaultTestUrls();
        $strategies = explode(',', $this->option('strategies'));
        $limit = (int) $this->option('limit');
        $delay = (int) $this->option('delay');

        $scraper = new EnhancedDocumentScraper([
            'delay_min' => max(1, $delay - 2),
            'delay_max' => $delay + 3,
            'timeout' => 45,
            'retries' => 3
        ]);

        $this->info("🚀 Testing Enhanced Scraper");
        $this->info("Strategies: " . implode(', ', $strategies));
        $this->info("Testing " . min(count($urls), $limit) . " URLs");
        $this->newLine();

        $results = [];
        $successCount = 0;
        $testUrls = array_slice($urls, 0, $limit);

        foreach ($testUrls as $index => $url) {
            $this->line("📄 Testing URL " . ($index + 1) . "/" . count($testUrls));
            $this->line("   {$url}");

            $startTime = microtime(true);
            $result = $scraper->scrapeWithStrategies($url, $strategies);
            $duration = round((microtime(true) - $startTime), 2);

            if ($result) {
                $successCount++;
                $this->info("   ✅ Success ({$duration}s)");
                $this->line("   📋 Title: " . ($result['title'] ?? 'N/A'));
                $this->line("   🔢 Number: " . ($result['document_number'] ?? 'N/A'));
                $this->line("   📅 Date: " . ($result['issue_date'] ?? 'N/A'));
                
                if (!empty($result['pdf_url'])) {
                    $this->line("   📎 PDF: " . $result['pdf_url']);
                }
                
                $results[] = array_merge($result, ['test_duration' => $duration]);
            } else {
                $this->error("   ❌ Failed ({$duration}s)");
            }

            $this->newLine();

            // Delay between requests
            if ($index < count($testUrls) - 1) {
                $this->line("⏳ Waiting {$delay} seconds...");
                sleep($delay);
            }
        }

        // Summary
        $this->info("📊 RESULTS SUMMARY");
        $this->table(
            ['Metric', 'Value'],
            [
                ['URLs Tested', count($testUrls)],
                ['Successful', $successCount],
                ['Failed', count($testUrls) - $successCount],
                ['Success Rate', round(($successCount / count($testUrls)) * 100, 1) . '%'],
                ['Avg Duration', count($results) > 0 ? round(collect($results)->avg('test_duration'), 2) . 's' : 'N/A']
            ]
        );

        if ($successCount > 0) {
            $this->newLine();
            $this->info("🎯 SUCCESSFUL EXTRACTIONS:");
            
            foreach ($results as $i => $result) {
                $this->line(($i + 1) . ". " . ($result['title'] ?? 'No title'));
                if (!empty($result['document_number'])) {
                    $this->line("   Number: " . $result['document_number']);
                }
                if (!empty($result['pdf_url'])) {
                    $this->line("   PDF: " . $result['pdf_url']);
                }
                $this->line("   Duration: " . $result['test_duration'] . 's');
                $this->newLine();
            }
        }

        // Save results to file for analysis
        $filename = 'scraper_test_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = storage_path('app/scraper_tests/' . $filename);
        
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        file_put_contents($filepath, json_encode([
            'test_date' => now(),
            'strategies' => $strategies,
            'urls_tested' => $testUrls,
            'success_count' => $successCount,
            'success_rate' => round(($successCount / count($testUrls)) * 100, 1),
            'results' => $results
        ], JSON_PRETTY_PRINT));

        $this->info("📁 Results saved to: {$filepath}");

        return $successCount > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function getDefaultTestUrls(): array
    {
        return [
            // Peraturan.go.id URLs
            'https://peraturan.go.id/id/peraturan-pemerintah-no-71-tahun-2019',
            'https://peraturan.go.id/id/undang-undang-no-11-tahun-2008',
            'https://peraturan.go.id/id/undang-undang-no-19-tahun-2016',
            
            // Different document types for testing
            'https://peraturan.go.id/id/peraturan-presiden-no-95-tahun-2018',
            'https://peraturan.go.id/id/peraturan-menteri-komunikasi-dan-informatika-no-20-tahun-2016',
            
            // JDIH sites
            'https://jdih.kemlu.go.id/portal/detail-search/peraturan-menteri-luar-negeri-nomor-1-tahun-2020',
            
            // Alternative sources
            'https://jdih.setkab.go.id/PUUdoc/175852/PP0712019.pdf',
            'https://peraturan.bpk.go.id/Home/Details/123456',
        ];
    }
}