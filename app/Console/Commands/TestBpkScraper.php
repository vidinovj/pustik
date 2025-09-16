<?php

namespace App\Console\Commands;

use App\Models\DocumentSource;
use App\Services\Scrapers\BpkScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log; // Add this for logging

class TestBpkScraper extends Command
{
    protected $signature = 'scraper:test-bpk {--limit= : Limit the number of documents to scrape} {--dry-run : Test without saving to database}';

    protected $description = 'Test the BPK scraper';

    public function handle()
    {
        $this->info('Starting BPK Scraper Test...');

        try {
            $source = DocumentSource::where('name', 'BPK')->first();
            $scraper = new BpkScraper($source);

            Log::info('Scraper instance type: '.get_class($scraper)); // Debugging line
            if (method_exists($scraper, 'setLimit')) { // Debugging line
                Log::info('setLimit method exists on scraper instance.');
            } else {
                Log::info('setLimit method DOES NOT exist on scraper instance.');
            }

            $limit = $this->option('limit');
            $isDryRun = $this->option('dry-run');

            if ($limit) {
                $scraper->setLimit((int) $limit);
            }

            if ($isDryRun) {
                $scraper->setDryRun(true);
                $this->info('🔍 DRY RUN MODE - No changes will be saved');
            }

            $this->info('Running BPK Scraper Test...');

            $documents = $scraper->scrape();

            $this->info('Scraped Documents:');
            $this->line(json_encode($documents, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error during BPK Scraper Test: '.$e->getMessage());
            Log::error('BPK Scraper Test Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
