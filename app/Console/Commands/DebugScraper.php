<?php

namespace App\Console\Commands;

use App\Models\DocumentSource;
use App\Services\Scrapers\JdihKemluScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DebugScraper extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'legal-docs:debug-scraper {--source=jdih_kemlu}';

    /**
     * The console command description.
     */
    protected $description = 'Debug the web scraper to identify issues';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🐛 Starting Scraper Debug Session');
        $this->newLine();

        $sourceName = $this->option('source');
        $source = DocumentSource::where('name', $sourceName)->first();

        if (!$source) {
            $this->error("Source '{$sourceName}' not found.");
            return Command::FAILURE;
        }

        $this->info("Debugging source: {$source->name}");
        $this->info("Base URL: {$source->base_url}");
        $this->newLine();

        try {
            // Test basic connectivity first
            $this->testConnectivity($source);
            
            // Run the debug scraper
            $scraper = new JdihKemluScraper($source);
            
            $this->info("Running debug scraper...");
            $documents = $scraper->scrape();
            
            $this->newLine();
            $this->info("✅ Debug scraping completed!");
            $this->info("Documents processed: " . count($documents));
            
            // Show what was saved
            if (count($documents) > 0) {
                $this->table(
                    ['ID', 'Title', 'Type', 'URL'],
                    collect($documents)->map(function ($doc) {
                        return [
                            $doc->id,
                            substr($doc->title, 0, 50) . '...',
                            $doc->document_type,
                            substr($doc->source_url, 0, 50) . '...'
                        ];
                    })->toArray()
                );
            }
            
            $this->newLine();
            $this->info("📋 Check the following logs for detailed debug info:");
            $this->line("  • storage/logs/legal-documents.log");
            $this->line("  • storage/logs/legal-documents-errors.log");
            $this->line("  • storage/logs/jdih_kemlu_sample.html (sample page HTML)");

        } catch (\Exception $e) {
            $this->error("Debug failed: {$e->getMessage()}");
            Log::channel('legal-documents-errors')->error("Debug command failed: {$e->getMessage()}", [
                'exception' => $e
            ]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Test basic connectivity to the source.
     */
    protected function testConnectivity(DocumentSource $source): void
    {
        $this->info("Testing connectivity...");
        
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($source->base_url);
            
            if ($response->successful()) {
                $this->info("✅ Connection successful (HTTP {$response->status()})");
            } else {
                $this->warn("⚠️  Connection returned HTTP {$response->status()}");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Connection failed: {$e->getMessage()}");
        }
    }
}