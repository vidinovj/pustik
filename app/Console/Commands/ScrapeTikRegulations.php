<?php
// app/Console/Commands/ScrapeTikRegulations.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\BrowserPeraturanScraper;
use App\Services\Scrapers\Enhanced\KemluTikScraper;
use App\Services\Scrapers\Enhanced\KomdigiScraper;
use App\Services\Scrapers\Enhanced\KemenkoScraper;
use App\Models\DocumentSource;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Log;

class ScrapeTikRegulations extends Command
{
    protected $signature = 'legal-docs:scrape-tik {--source=all} {--limit=50} {--test-mode}';
    protected $description = 'Scrape TIK/IT regulations from multiple government sources';

    protected array $tikKeywords = [
        'teknologi informasi', 'teknologi komunikasi', 'tik', 'ict',
        'informatika', 'telekomunikasi', 'digital', 'elektronik',
        'cyber', 'internet', 'data', 'sistem informasi',
        'komputer', 'software', 'hardware', 'jaringan',
        'keamanan siber', 'e-government', 'smart city',
        'fintech', 'startup', 'platform digital'
    ];

    public function handle(): int
    {
        $this->info('🌐 Multi-Source TIK Regulation Scraper');
        $this->info('🎯 Target: IT/Communications/Digital regulations');
        $this->newLine();

        $source = $this->option('source');
        $limit = (int) $this->option('limit');
        $testMode = $this->option('test-mode');

        if ($testMode) {
            $this->warn('🧪 RUNNING IN TEST MODE - Limited scraping');
            $limit = min($limit, 10);
        }

        $this->info("Configuration:");
        $this->line("  • Source: {$source}");
        $this->line("  • Limit per source: {$limit}");
        $this->line("  • Test mode: " . ($testMode ? 'Yes' : 'No'));
        $this->newLine();

        $totalDocuments = 0;
        $sourceResults = [];

        // Define scraping sources
        $sources = $this->getSources($source);

        foreach ($sources as $sourceName => $config) {
            $this->info("🔄 Scraping: {$config['name']}");
            $this->line("   URL: {$config['url']}");
            
            try {
                $scraped = $this->scrapeSource($sourceName, $config, $limit, $testMode);
                $sourceResults[$sourceName] = $scraped;
                $totalDocuments += count($scraped);
                
                $this->info("   ✅ Scraped: " . count($scraped) . " documents");
                
                if (count($scraped) > 0) {
                    $this->displaySampleDocs($scraped, 2);
                }
                
            } catch (\Exception $e) {
                $this->error("   ❌ Failed: " . $e->getMessage());
                $sourceResults[$sourceName] = [];
                Log::channel('legal-documents-errors')->error("TIK Scraper failed for {$sourceName}: " . $e->getMessage());
            }
            
            $this->newLine();
            sleep(2); // Be respectful between sources
        }

        // Summary
        $this->displaySummary($sourceResults, $totalDocuments);
        
        return Command::SUCCESS;
    }

    protected function getSources(string $source): array
    {
        $allSources = [
            'peraturan_go_id' => [
                'name' => 'Peraturan.go.id (National)',
                'url' => 'https://peraturan.go.id',
                'scraper' => 'browser',
                'priority' => 1
            ],
            'kemlu' => [
                'name' => 'JDIH Kemlu (MFA)',
                'url' => 'https://jdih.kemlu.go.id',
                'scraper' => 'enhanced_http',
                'priority' => 2
            ],
            'komdigi' => [
                'name' => 'JDIH Komdigi (ICT Ministry)',
                'url' => 'https://jdih.komdigi.go.id',
                'scraper' => 'enhanced_http',
                'priority' => 1
            ],
            'kemenko' => [
                'name' => 'JDIH Kemenko (Coordinating Ministry)',
                'url' => 'https://jdih.kemenko.go.id',
                'scraper' => 'enhanced_http',
                'priority' => 3
            ]
        ];

        if ($source === 'all') {
            // Sort by priority
            uasort($allSources, fn($a, $b) => $a['priority'] <=> $b['priority']);
            return $allSources;
        }

        return isset($allSources[$source]) ? [$source => $allSources[$source]] : [];
    }

    protected function scrapeSource(string $sourceName, array $config, int $limit, bool $testMode): array
    {
        $scraperType = $config['scraper'];
        $documents = [];

        switch ($scraperType) {
            case 'browser':
                $documents = $this->scrapeBrowserSource($sourceName, $config, $limit);
                break;
                
            case 'enhanced_http':
                $documents = $this->scrapeHttpSource($sourceName, $config, $limit);
                break;
        }

        // Filter for TIK-related content
        $tikDocuments = $this->filterTikDocuments($documents);
        
        $this->line("   🔍 Filtered: " . count($documents) . " → " . count($tikDocuments) . " TIK-related");
        
        return $tikDocuments;
    }

    protected function scrapeBrowserSource(string $sourceName, array $config, int $limit): array
    {
        // Get or create document source
        $source = DocumentSource::firstOrCreate([
            'name' => $sourceName
        ], [
            'display_name' => $config['name'],
            'base_url' => $config['url'],
            'status' => 'active',
            'config' => [
                'scraper_type' => 'browser',
                'tik_focused' => true
            ]
        ]);

        // Use browser scraper
        $scraper = new BrowserPeraturanScraper($source);
        return $scraper->scrapeWithLimit($limit);
    }

    protected function scrapeHttpSource(string $sourceName, array $config, int $limit): array
    {
        // Get or create document source
        $source = DocumentSource::firstOrCreate([
            'name' => $sourceName
        ], [
            'display_name' => $config['name'],
            'base_url' => $config['url'],
            'status' => 'active',
            'config' => [
                'scraper_type' => 'enhanced_http',
                'tik_focused' => true
            ]
        ]);

        // Use appropriate enhanced scraper
        switch ($sourceName) {
            case 'kemlu':
                $scraper = new KemluTikScraper($source);
                break;
            case 'komdigi':
                $scraper = new KomdigiScraper($source);
                break;
            case 'kemenko':
                $scraper = new KemenkoScraper($source);
                break;
            default:
                throw new \Exception("No scraper configured for source: {$sourceName}");
        }

        return $scraper->scrapeWithLimit($limit);
    }

    protected function filterTikDocuments(array $documents): array
    {
        $tikDocuments = [];

        foreach ($documents as $doc) {
            if ($this->isTikRelated($doc)) {
                $tikDocuments[] = $doc;
            }
        }

        return $tikDocuments;
    }

    protected function isTikRelated($document): bool
    {
        $title = strtolower($document->title ?? '');
        $content = strtolower($document->full_text ?? '');
        $subject = strtolower($document->metadata['subject'] ?? '');
        
        $searchText = $title . ' ' . $content . ' ' . $subject;

        foreach ($this->tikKeywords as $keyword) {
            if (stripos($searchText, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function displaySampleDocs(array $documents, int $count): void
    {
        $this->line("   📋 Sample documents:");
        
        foreach (array_slice($documents, 0, $count) as $i => $doc) {
            $title = substr($doc->title, 0, 60) . '...';
            $type = $doc->document_type ?? 'Unknown';
            $agency = $doc->metadata['agency'] ?? 'N/A';
            
            $this->line("     " . ($i + 1) . ". {$title}");
            $this->line("        Type: {$type} | Agency: {$agency}");
        }
    }

    protected function displaySummary(array $results, int $total): void
    {
        $this->newLine();
        $this->info("📊 SCRAPING SUMMARY");
        $this->line("┌─────────────────────────────────────────┐");
        
        foreach ($results as $source => $docs) {
            $count = count($docs);
            $status = $count > 0 ? '✅' : '❌';
            $sourceName = str_pad($source, 20);
            $countText = str_pad("{$count} docs", 10);
            
            $this->line("│ {$status} {$sourceName} │ {$countText} │");
        }
        
        $this->line("├─────────────────────────────────────────┤");
        $this->line("│ 🎯 TOTAL TIK REGULATIONS: " . str_pad("{$total}", 12) . " │");
        $this->line("└─────────────────────────────────────────┘");
        
        if ($total > 0) {
            $this->newLine();
            $this->info("🚀 NEXT STEPS:");
            $this->line("  1. Review scraped regulations in admin panel");
            $this->line("  2. Run full scrape: --limit=200 (remove --test-mode)");
            $this->line("  3. Set up automated daily scraping");
            $this->line("  4. Add manual MoU entries for important agreements");
            
            $this->newLine();
            $this->info("💡 TIP: Your catalog now has {$total} regulations to launch with!");
        } else {
            $this->warn("⚠️  No TIK regulations found. Consider:");
            $this->line("  • Expanding keyword list");
            $this->line("  • Checking source accessibility");
            $this->line("  • Running with --test-mode to debug");
        }
    }
}