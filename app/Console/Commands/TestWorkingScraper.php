<?php

namespace App\Console\Commands;

use App\Models\DocumentSource;
use App\Services\Scrapers\PeraturanGoIdScraper;
use Illuminate\Console\Command;

class TestWorkingScraper extends Command
{
    protected $signature = 'legal-docs:test-working';
    protected $description = 'Test the working sites scraper (peraturan.go.id)';

    public function handle(): int
    {
        $this->info('🚀 Testing Working Sites Scraper');
        $this->newLine();

        // Update sources first
        $this->info('📥 Updating document sources...');
        $this->call('db:seed', ['--class' => 'DocumentSourcesSeeder']);
        $this->newLine();

        // Get the working source
        $source = DocumentSource::where('name', 'peraturan_go_id')->first();
        
        if (!$source) {
            $this->error('Peraturan.go.id source not found after seeding.');
            return Command::FAILURE;
        }

        if (!$source->is_active) {
            $this->error('Peraturan.go.id source is not active.');
            return Command::FAILURE;
        }

        $this->info("Testing source: {$source->name}");
        $this->info("Base URL: {$source->base_url}");
        $this->newLine();

        try {
            // Test connectivity first
            $this->testConnectivity($source->base_url);

            // Create and run the scraper
            $scraper = new PeraturanGoIdScraper($source);
            
            $this->info("🔄 Running scraper for peraturan.go.id...");
            $this->line("This may take a few minutes...");
            
            $documents = $scraper->scrape();
            
            $this->newLine();
            $this->info("✅ Scraping completed!");
            $this->info("Documents processed: " . count($documents));
            
            // Show results
            if (count($documents) > 0) {
                $this->table(
                    ['ID', 'Title', 'Type', 'Document Number', 'Date'],
                    collect($documents)->take(10)->map(function ($doc) {
                        return [
                            $doc->id,
                            substr($doc->title, 0, 40) . '...',
                            substr($doc->document_type, 0, 20),
                            $doc->document_number ?: 'N/A',
                            $doc->issue_date ?: 'N/A'
                        ];
                    })->toArray()
                );
                
                if (count($documents) > 10) {
                    $this->info("... and " . (count($documents) - 10) . " more documents");
                }
            } else {
                $this->warn("⚠️  No documents were processed. Check logs for details.");
            }

            // Database summary
            $totalInDb = $source->legalDocuments()->count();
            $this->newLine();
            $this->info("📊 Database Summary:");
            $this->line("  • Total documents from this source: {$totalInDb}");
            $this->line("  • Documents added this run: " . count($documents));
            
            // Log file locations
            $this->newLine();
            $this->info("📋 Check logs for detailed information:");
            $this->line("  • Success: storage/logs/legal-documents.log");
            $this->line("  • Errors: storage/logs/legal-documents-errors.log");

        } catch (\Exception $e) {
            $this->error("❌ Scraper failed: {$e->getMessage()}");
            $this->newLine();
            $this->line("Stack trace:");
            $this->line($e->getTraceAsString());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function testConnectivity(string $url): void
    {
        $this->info("🔌 Testing connectivity to {$url}...");
        
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get($url);
            
            if ($response->successful()) {
                $this->info("✅ Connection successful (HTTP {$response->status()})");
            } else {
                $this->warn("⚠️  Connection returned HTTP {$response->status()}");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Connection failed: {$e->getMessage()}");
            throw $e;
        }
        
        $this->newLine();
    }
}