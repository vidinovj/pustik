<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAlternativeSites extends Command
{
    protected $signature = 'legal-docs:test-alternatives';
    protected $description = 'Test alternative government legal document sites';

    public function handle(): int
    {
        $this->info('🌐 Testing Alternative Government Legal Document Sites');
        $this->newLine();

        $sites = [
            'JDIHN (National)' => 'https://jdihn.go.id',
            'BPK JDIH' => 'https://peraturan.bpk.go.id', 
            'Komdigi JDIH' => 'https://jdih.komdigi.go.id',
            'JDIH Nasional' => 'https://peraturan.go.id',
            'Kemenkomdigi' => 'https://jdih.komdigi.go.id',
            'BSSN' => 'https://www.bssn.go.id',
            'Menpan RB' => 'https://www.menpan.go.id',
        ];

        $workingSites = [];

        foreach ($sites as $name => $url) {
            $this->testSite($name, $url, $workingSites);
        }

        $this->newLine();
        $this->info('📋 Summary of Working Sites:');
        
        if (count($workingSites) > 0) {
            foreach ($workingSites as $site) {
                $this->line("✅ {$site['name']}: {$site['url']}");
            }
            
            $this->newLine();
            $this->info('💡 Recommendation: Focus on these working sites for scraping');
        } else {
            $this->warn('⚠️  No working sites found. Check your internet connection.');
        }

        return Command::SUCCESS;
    }

    protected function testSite(string $name, string $url, array &$workingSites): void
    {
        $this->line("Testing {$name}...");

        try {
            $response = Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $this->info("  ✅ {$name} - HTTP {$response->status()}");
                
                // Quick content check
                $content = $response->body();
                $hasDocuments = (
                    stripos($content, 'dokumen') !== false ||
                    stripos($content, 'peraturan') !== false ||
                    stripos($content, 'regulasi') !== false ||
                    stripos($content, 'hukum') !== false
                );
                
                if ($hasDocuments) {
                    $this->line("  📄 Contains legal document content");
                    $workingSites[] = ['name' => $name, 'url' => $url];
                }
                
            } else {
                $this->line("  ❌ {$name} - HTTP {$response->status()}");
            }
            
        } catch (\Exception $e) {
            $this->line("  ❌ {$name} - Error: " . substr($e->getMessage(), 0, 50) . '...');
        }

        // Small delay to be respectful
        usleep(500000); // 0.5 seconds
    }
}