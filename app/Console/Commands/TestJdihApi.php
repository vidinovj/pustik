<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestJdihApi extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'legal-docs:test-api {--details : Show detailed response data}';

    /**
     * The console command description.
     */
    protected $description = 'Test JDIH Perpusnas API connectivity and available endpoints';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Testing JDIH Perpusnas API...');
        $this->newLine();

        // Test various endpoints from the documentation
        $endpoints = [
            'Articles List' => '/list-artikel?limit=3',
            'Survey Results' => '/survey/hasil',
            'Token Generation' => '/token',
        ];

        foreach ($endpoints as $name => $endpoint) {
            $this->info("Testing {$name}: {$endpoint}");
            
            try {
                $response = Http::jdihPerpusnas()->get($endpoint);
                
                $this->info("   Status: {$response->status()}");
                
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['data'])) {
                        $this->info("   ✅ Success - Found " . count($data['data']) . " items");
                    } elseif (isset($data['total'])) {
                        $this->info("   ✅ Success - Total: " . $data['total']);
                    } else {
                        $this->info("   ✅ Success - Response received");
                    }
                    
                    // Show sample data structure
                    if ($this->option('details')) {
                        $this->line("   Sample response:");
                        $this->line("   " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        $this->newLine();
                    }
                } else {
                    $this->warn("   ⚠️  Status {$response->status()}: " . $response->body());
                }
                
            } catch (\Exception $e) {
                $this->error("   ❌ Error: " . $e->getMessage());
            }
            
            $this->newLine();
        }

        // Test search functionality
        $this->info('Testing search functionality:');
        try {
            $response = Http::jdihPerpusnas()->get('/list-artikel', [
                'q' => 'teknologi informasi',
                'limit' => 3
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info("   ✅ Search works - Found " . ($data['total'] ?? 'unknown') . " results for 'teknologi informasi'");
            } else {
                $this->warn("   ⚠️  Search failed with status: " . $response->status());
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Search error: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('📋 Test Summary:');
        $this->info('If you see successful responses, the API is working with demo credentials.');
        $this->info('Contact Perpusnas for production API access.');
        
        return Command::SUCCESS;
    }
}