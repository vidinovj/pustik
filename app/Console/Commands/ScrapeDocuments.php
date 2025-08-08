<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeDocumentsJob;
use App\Models\DocumentSource;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Console\Command;

class ScrapeDocuments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'legal-docs:scrape 
                            {--source= : Specific source to scrape}
                            {--all : Scrape all active sources}
                            {--queue : Run in background queue}
                            {--dry-run : Show what would be scraped without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Scrape legal documents from government websites';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🕷️  Legal Documents Scraper');
        $this->newLine();

        // Determine which sources to scrape
        $sources = $this->getSourcesToScrape();
        
        if ($sources->isEmpty()) {
            $this->warn('No sources found to scrape.');
            return Command::SUCCESS;
        }

        // Show what will be scraped
        $this->displayScrapePlan($sources);

        // Confirm if not dry run
        if (!$this->option('dry-run')) {
            if (!$this->confirm('Continue with scraping?')) {
                $this->info('Scraping cancelled.');
                return Command::SUCCESS;
            }
        }

        // Execute scraping
        return $this->executeScraping($sources);
    }

    /**
     * Get sources to scrape based on options.
     */
    protected function getSourcesToScrape()
    {
        if ($this->option('source')) {
            // Scrape specific source
            $source = DocumentSource::where('name', $this->option('source'))->first();
            
            if (!$source) {
                $this->error("Source '{$this->option('source')}' not found.");
                return collect();
            }

            if (!$source->is_active) {
                $this->warn("Source '{$source->name}' is inactive.");
                return collect();
            }

            return collect([$source]);
        }

        if ($this->option('all')) {
            // Scrape all active sources
            return ScraperFactory::getScrapableSources();
        }

        // Interactive selection
        return $this->interactiveSourceSelection();
    }

    /**
     * Interactive source selection.
     */
    protected function interactiveSourceSelection()
    {
        $sources = ScraperFactory::getScrapableSources();
        
        if ($sources->isEmpty()) {
            $this->warn('No active sources with scrapers available.');
            return collect();
        }

        $choices = $sources->pluck('name', 'id')->toArray();
        $choices['all'] = 'All sources';

        $selectedKey = $this->choice('Which source would you like to scrape?', $choices);

        if ($selectedKey === 'all') {
            return $sources;
        }

        return $sources->where('name', $selectedKey);
    }

    /**
     * Display scraping plan.
     */
    protected function displayScrapePlan($sources): void
    {
        $this->info('📋 Scraping Plan:');
        
        $table = [];
        foreach ($sources as $source) {
            $table[] = [
                'Name' => $source->name,
                'Type' => $source->type,
                'Base URL' => $source->base_url,
                'Last Scraped' => $source->last_scraped_at?->diffForHumans() ?? 'Never',
                'Documents' => number_format($source->total_documents ?? 0),
                'Status' => $source->is_active ? '✅ Active' : '❌ Inactive',
            ];
        }

        $this->table(
            ['Name', 'Type', 'Base URL', 'Last Scraped', 'Documents', 'Status'],
            $table
        );

        if ($this->option('dry-run')) {
            $this->warn('🧪 DRY RUN MODE - No actual scraping will occur');
        }

        if ($this->option('queue')) {
            $this->info('📤 Jobs will be queued for background processing');
        }
    }

    /**
     * Execute the scraping process.
     */
    protected function executeScraping($sources): int
    {
        if ($this->option('dry-run')) {
            $this->info('✅ Dry run completed - no changes made.');
            return Command::SUCCESS;
        }

        $this->info('🚀 Starting scraping process...');
        $progressBar = $this->output->createProgressBar($sources->count());

        foreach ($sources as $source) {
            if ($this->option('queue')) {
                // Queue the job
                ScrapeDocumentsJob::dispatch($source);
                $this->line("   📤 Queued job for: {$source->name}");
            } else {
                // Run immediately
                $this->line("   🕷️  Scraping: {$source->name}");
                $scraper = ScraperFactory::create($source);
                
                if ($scraper) {
                    try {
                        $documents = $scraper->scrape();
                        $this->line("   ✅ Completed: {$source->name} ({" . count($documents) . "} documents)");
                    } catch (\Exception $e) {
                        $this->line("   ❌ Failed: {$source->name} - {$e->getMessage()}");
                    }
                } else {
                    $this->line("   ⚠️  No scraper available for: {$source->name}");
                }
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($this->option('queue')) {
            $this->info('✅ All scraping jobs have been queued.');
            $this->line('Monitor progress with: php artisan queue:work');
        } else {
            $this->info('✅ Scraping completed.');
        }

        return Command::SUCCESS;
    }
}