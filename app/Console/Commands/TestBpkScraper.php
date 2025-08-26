<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Scrapers\BpkScraper;
use App\Models\DocumentSource;

class TestBpkScraper extends Command
{
    protected $signature = 'scraper:test-bpk';
    protected $description = 'Test the BPK scraper';

    public function handle()
    {
        $source = DocumentSource::where('name', 'BPK')->first();
        $scraper = new BpkScraper($source);

        $this->info('Running BPK Scraper Test...');

        $analytics = $scraper->getEntitySearchAnalytics();
        $this->info('Analytics:');
        $this->line(json_encode($analytics, JSON_PRETTY_PRINT));

        $this->info('Scraping...');
        $documents = $scraper->scrape();

        $this->info('Scraped Documents:');
        $this->line(json_encode($documents, JSON_PRETTY_PRINT));
    }
}
