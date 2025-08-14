<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentSource;
use App\Models\LegalDocument;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Support\Facades\Log;

class UnifiedScraperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'legal-docs:scrape
                            {--source=all : Specify a source to scrape (e.g., kemlu, komdigi, peraturan_go_id)}
                            {--limit=50 : Limit the number of documents to scrape per source}
                            {--test-mode : Run the scraper in test mode (limited documents)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unified command to scrape legal documents from various sources.';

    protected array $tikKeywords = [
        'teknologi informasi', 'teknologi komunikasi', 'tik', 'ict',
        'informatika', 'telekomunikasi', 'digital', 'elektronik',
        'cyber', 'internet', 'data', 'sistem informasi',
        'komputer', 'software', 'hardware', 'jaringan',
        'keamanan siber', 'e-government', 'smart city',
        'fintech', 'startup', 'platform digital'
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🌐 Unified Legal Document Scraper');
        $this->newLine();

        $sourceOption = $this->option('source');
        $limit = (int) $this->option('limit');
        $testMode = $this->option('test-mode');

        if ($testMode) {
            $this->warn('🧪 RUNNING IN TEST MODE - Limited scraping');
            $limit = min($limit, 10); // Further limit in test mode
        }

        $this->info("Configuration:");
        $this->line("  • Source: {$sourceOption}");
        $this->line("  • Limit per source: {$limit}");
        $this->line("  • Test mode: " . ($testMode ? 'Yes' : 'No'));
        $this->newLine();

        $totalDocuments = 0;
        $sourceResults = [];

        $sourcesToScrape = $this->getSourcesToScrape($sourceOption);

        if (empty($sourcesToScrape)) {
            $this->error("No valid sources found to scrape.");
            return Command::FAILURE;
        }

        foreach ($sourcesToScrape as $sourceName => $config) {
            $this->info("🔄 Scraping: {$config['name']}");
            $this->line("   URL: {$config['url']}");
            
            try {
                $scrapedDocuments = $this->scrapeFromSource($sourceName, $config, $limit);
                $sourceResults[$sourceName] = $scrapedDocuments;
                $totalDocuments += count($scrapedDocuments);
                
                $this->info("   ✅ Scraped: " . count($scrapedDocuments) . " documents");
                
                if (count($scrapedDocuments) > 0) {
                    $this->displaySampleDocs($scrapedDocuments, 2);
                }
                
            } catch (\Exception $e) {
                $this->error("   ❌ Failed: " . $e->getMessage());
                $sourceResults[$sourceName] = [];
                Log::channel('legal-documents-errors')->error("Unified Scraper failed for {$sourceName}: " . $e->getMessage());
            }
            
            $this->newLine();
            sleep(2); // Be respectful between sources
        }

        // Summary
        $this->displaySummary($sourceResults, $totalDocuments);
        
        return Command::SUCCESS;
    }

    protected function getSourcesToScrape(string $sourceOption): array
    {
        $allSources = [
            'peraturan_go_id' => [
                'name' => 'Peraturan.go.id (National)',
                'url' => 'https://peraturan.go.id',
                'scraper_type' => 'browser',
                'priority' => 1
            ],
            'kemlu' => [
                'name' => 'JDIH Kemlu (MFA)',
                'url' => 'https://jdih.kemlu.go.id',
                'scraper_type' => 'enhanced_http',
                'priority' => 2
            ],
            'komdigi' => [
                'name' => 'JDIH Komdigi (ICT Ministry)',
                'url' => 'https://jdih.komdigi.go.id',
                'scraper_type' => 'enhanced_http',
                'priority' => 1
            ],
            'kemenko' => [
                'name' => 'JDIH Kemenko (Coordinating Ministry)',
                'url' => 'https://jdih.kemenko.go.id',
                'scraper_type' => 'enhanced_http',
                'priority' => 3
            ]
        ];

        if ($sourceOption === 'all') {
            uasort($allSources, fn($a, $b) => $a['priority'] <=> $b['priority']);
            return $allSources;
        }

        return isset($allSources[$sourceOption]) ? [$sourceOption => $allSources[$sourceOption]] : [];
    }

    protected function scrapeFromSource(string $sourceName, array $config, int $limit): array
    {
        $source = DocumentSource::firstOrCreate([
            'name' => $sourceName
        ], [
            'display_name' => $config['name'],
            'base_url' => $config['url'],
            'status' => 'active',
            'config' => [
                'scraper_type' => $config['scraper_type'],
                'tik_focused' => true // Assuming all these sources are TIK focused
            ]
        ]);

        $scraper = ScraperFactory::create($source);

        if (!$scraper) {
            $this->error("Could not create scraper for {$sourceName}.");
            return [];
        }

        $documents = $scraper->scrape();

        if ($limit > 0) {
            $documents = array_slice($documents, 0, $limit);
        }

        // Filter for TIK-related content (if not already handled by scraper)
        // $tikDocuments = $this->filterTikDocuments($documents);
        
        // $this->line("   🔍 Filtered: " . count($documents) . " → " . count($tikDocuments) . " TIK-related");
        
        // return $tikDocuments;
        return $documents;
    }

    protected function filterTikDocuments(array $documents): array
    {
        $tikDocuments = [];

        foreach ($documents as $doc) {
            // Ensure $doc is an object with properties, not an array
            if (is_array($doc)) {
                $doc = (object) $doc;
            }

            if ($this->isTikRelated($doc)) {
                $tikDocuments[] = $doc;
            }
        }

        return $tikDocuments;
    }

    protected function isTikRelated($document): bool
    {
        // Check if the document object has the necessary properties
        if (!isset($document->title) && !isset($document->full_text) && !isset($document->metadata['subject'])) {
            return false; // Not enough data to determine TIK relevance
        }

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
            // Ensure $doc is an object with properties
            if (is_array($doc)) {
                $doc = (object) $doc;
            }

            $title = substr($doc->title ?? '', 0, 60) . '...';
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
