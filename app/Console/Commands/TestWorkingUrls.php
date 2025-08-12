<?php
// app/Console/Commands/TestWorkingUrls.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\EnhancedDocumentScraper;

class TestWorkingUrls extends Command
{
    protected $signature = 'scraper:test-working-urls 
                           {--limit=10 : Limit number of URLs to test}
                           {--strategies=stealth,basic : Comma-separated strategies}';

    protected $description = 'Test scraping with confirmed working peraturan.go.id URLs';

    public function handle()
    {
        // Current working URLs based on search results
        $workingUrls = [
            // Recent Undang-Undang (Laws)
            'https://peraturan.go.id/id/uu-no-1-tahun-2023',  // KUHP
            'https://peraturan.go.id/id/uu-no-2-tahun-2024',  // Jakarta Province
            'https://peraturan.go.id/id/uu-no-19-tahun-2023', // Budget 2024
            'https://peraturan.go.id/id/uu-no-6-tahun-2023',  // Job Creation Law
            
            // Recent Peraturan Presiden (Presidential Regulations)  
            'https://peraturan.go.id/id/perpres-no-109-tahun-2024', // Govt Work Plan 2025
            'https://peraturan.go.id/id/perpres-no-201-tahun-2024', // Budget Details 2025
            'https://peraturan.go.id/id/perpres-no-19-tahun-2023',  // Human Trafficking Action Plan
            
            // Recent Ministerial Regulations
            'https://peraturan.go.id/id/permenaker-no-16-tahun-2024', // Minimum Wage 2025
            'https://peraturan.go.id/id/pp-no-28-tahun-2024',        // Health Law Implementation
            
            // Alternative JDIH sites for comparison
            'https://jdih.kemlu.go.id/',
            'https://peraturan.bpk.go.id/',
            'https://jdih.kemnaker.go.id/'
        ];

        $strategies = explode(',', $this->option('strategies'));
        $limit = (int) $this->option('limit');
        
        $scraper = new EnhancedDocumentScraper([
            'delay_min' => 3,
            'delay_max' => 8,
            'timeout' => 60,
            'retries' => 2
        ]);

        $this->info("🔍 Testing with WORKING peraturan.go.id URLs");
        $this->info("Strategies: " . implode(', ', $strategies));
        $this->newLine();

        $results = [];
        $successCount = 0;
        $testUrls = array_slice($workingUrls, 0, $limit);

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

            // Respectful delay between requests
            if ($index < count($testUrls) - 1) {
                sleep(5);
            }
        }

        // Summary
        $this->info("📊 RESULTS WITH WORKING URLs");
        $this->table(
            ['Metric', 'Value'],
            [
                ['URLs Tested', count($testUrls)],
                ['Successful', $successCount],
                ['Failed', count($testUrls) - $successCount],
                ['Success Rate', round(($successCount / count($testUrls)) * 100, 1) . '%']
            ]
        );

        if ($successCount > 0) {
            $this->info("✨ Great! The URLs work. Now let's identify patterns:");
            $this->newLine();
            
            $this->info("📝 URL PATTERNS DISCOVERED:");
            $this->line("• Laws (UU): peraturan.go.id/id/uu-no-{NUMBER}-tahun-{YEAR}");
            $this->line("• Presidential (Perpres): peraturan.go.id/id/perpres-no-{NUMBER}-tahun-{YEAR}");  
            $this->line("• Ministerial: peraturan.go.id/id/permen{MINISTRY}-no-{NUMBER}-tahun-{YEAR}");
            $this->line("• Government (PP): peraturan.go.id/id/pp-no-{NUMBER}-tahun-{YEAR}");
            
            $this->newLine();
            $this->info("🎯 NEXT STEPS:");
            $this->line("1. Update your scraper to use correct URL patterns");
            $this->line("2. Focus on recent documents (2023-2025) first");
            $this->line("3. Use category pages: peraturan.go.id/uu, peraturan.go.id/perpres");
            $this->line("4. Consider alternative sites: peraturan.bpk.go.id, jdih.kemlu.go.id");
        } else {
            $this->error("❌ Still failing. This suggests network/access issues.");
            $this->line("Try: curl -v https://peraturan.go.id/id/uu-no-1-tahun-2023");
        }

        return $successCount > 0 ? Command::SUCCESS : Command::FAILURE;
    }
}

// URL Pattern Generator Helper
class PeraturanUrlBuilder 
{
    public static function generateUrls(): array
    {
        $urls = [];
        
        // Recent IT/Tech related laws
        $itRegulations = [
            ['type' => 'uu', 'number' => 1, 'year' => 2023, 'title' => 'Criminal Code'],
            ['type' => 'uu', 'number' => 6, 'year' => 2023, 'title' => 'Job Creation Law'],
            ['type' => 'perpres', 'number' => 109, 'year' => 2024, 'title' => 'Govt Work Plan 2025'],
            ['type' => 'pp', 'number' => 28, 'year' => 2024, 'title' => 'Health Law Implementation'],
        ];
        
        foreach ($itRegulations as $reg) {
            $urls[] = "https://peraturan.go.id/id/{$reg['type']}-no-{$reg['number']}-tahun-{$reg['year']}";
        }
        
        return $urls;
    }
    
    public static function buildCategoryUrls(): array 
    {
        return [
            'https://peraturan.go.id/uu',      // All laws
            'https://peraturan.go.id/perpres', // Presidential regulations  
            'https://peraturan.go.id/pp',      // Government regulations
            'https://peraturan.go.id/uu?tahun=2023', // Laws from 2023
            'https://peraturan.go.id/uu?tahun=2024', // Laws from 2024
        ];
    }
}