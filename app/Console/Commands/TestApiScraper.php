<?php

namespace App\Console\Commands;

use App\Models\DocumentSource;
use App\Services\Scrapers\JdihPerpusnasApiScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestApiScraper extends Command
{
    protected $signature = 'legal-docs:test-api';
    protected $description = 'Test the JDIH Perpusnas API scraper';

    public function handle(): int
    {
        $this->info('🧪 Testing JDIH Perpusnas API Scraper');
        $this->newLine();

        // First test API connectivity
        $this->testApiConnectivity();

        // Get the API source
        $source = DocumentSource::where('name', 'jdih_perpusnas_api')->first();
        
        if (!$source) {
            $this->error('JDIH Perpusnas API source not found. Run: php artisan db:seed --class=DocumentSourcesSeeder');
            return Command::FAILURE;
        }

        $this->info("Testing source: {$source->name}");
        $this->newLine();

        try {
            // Create and run the scraper
            $scraper = new JdihPerpusnasApiScraper($source);
            
            $this->info("🔄 Running API scraper...");
            $documents = $scraper->scrape();
            
            $this->newLine();
            $this->info("✅ API scraping completed!");
            $this->info("Documents processed: " . count($documents));
            
            // Show results table
            if (count($documents) > 0) {
                $this->table(
                    ['ID', 'Title', 'Type', 'Document Number', 'Date'],
                    collect($documents)->take(10)->map(function ($doc) {
                        return [
                            $doc->id,
                            substr($doc->title, 0, 50) . '...',
                            $doc->document_type,
                            $doc->document_number ?: 'N/A',
                            $doc->issue_date ?: 'N/A'
                        ];
                    })->toArray()
                );
                
                if (count($documents) > 10) {
                    $this->info("... and " . (count($documents) - 10) . " more documents");
                }
            }

            // Check database
            $totalInDb = $source->legalDocuments()->count();
            $this->info("Total documents in database for this source: {$totalInDb}");

        } catch (\Exception $e) {
            $this->error("❌ API scraper failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function testApiConnectivity(): void
    {
        $this->info("🔌 Testing API connectivity...");

        try {
            // Test basic endpoint
            $response = Http::jdihPerpusnas()->get('/list-artikel?limit=1');
            
            if ($response->successful()) {
                $this->info("✅ API connection successful");
                
                $data = $response->json();
                if (isset($data['data'])) {
                    $this->info("✅ API returns data structure");
                } else {
                    $this->warn("⚠️  API response format unexpected");
                    $this->line("Response: " . json_encode($data));
                }
            } else {
                $this->warn("⚠️  API returned HTTP {$response->status()}");
                $this->line("Response: " . $response->body());
            }

        } catch (\Exception $e) {
            $this->error("❌ API connection failed: {$e->getMessage()}");
        }

        $this->newLine();
    }
}