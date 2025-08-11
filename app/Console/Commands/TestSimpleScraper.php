<?php

namespace App\Console\Commands;

use App\Models\DocumentSource;
use App\Services\Scrapers\SimplePeraturanScraper;
use Illuminate\Console\Command;

class TestSimpleScraper extends Command
{
    protected $signature = 'legal-docs:test-simple';
    protected $description = 'Test the simple browse-only scraper';

    public function handle(): int
    {
        $this->info('🧪 Testing Simple Browse-Only Scraper');
        $this->newLine();

        // Get the source
        $source = DocumentSource::where('name', 'peraturan_go_id')->first();
        
        if (!$source) {
            $this->error('Peraturan.go.id source not found. Run: php artisan db:seed --class=DocumentSourcesSeeder');
            return Command::FAILURE;
        }

        $this->info("Testing simple browse approach for: {$source->name}");
        $this->newLine();

        try {
            // Create the simple scraper
            $scraper = new SimplePeraturanScraper($source);
            
            $this->info("🔄 Running simple browse scraper...");
            $this->line("This will try to find document listings without using search...");
            
            $documents = $scraper->scrape();
            
            $this->newLine();
            $this->info("✅ Simple scraping completed!");
            $this->info("Documents processed: " . count($documents));
            
            // Show results
            if (count($documents) > 0) {
                $this->table(
                    ['ID', 'Title', 'Type', 'URL'],
                    collect($documents)->map(function ($doc) {
                        return [
                            $doc->id,
                            substr($doc->title, 0, 60) . '...',
                            $doc->document_type,
                            substr($doc->source_url, 0, 50) . '...'
                        ];
                    })->toArray()
                );
                
                $this->newLine();
                $this->info("🎉 Success! The simple approach worked!");
                $this->info("You can now run: php artisan legal-docs:scrape --source=peraturan_go_id");
                
            } else {
                $this->warn("⚠️  No documents found with simple browse approach");
                $this->line("Check the exploration results to find working URLs manually");
            }

            // Database summary
            $totalInDb = $source->legalDocuments()->count();
            $this->info("Total documents in database: {$totalInDb}");

        } catch (\Exception $e) {
            $this->error("❌ Simple scraper failed: {$e->getMessage()}");
            $this->line("Try running: php artisan legal-docs:explore-peraturan");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}