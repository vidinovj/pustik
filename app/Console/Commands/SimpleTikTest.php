<?php
// app/Console/Commands/SimpleTikTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SimpleTikTest extends Command
{
    protected $signature = 'scraper:simple-tik-test';
    protected $description = 'Quick test for TIK regulations with known URLs';

    public function handle()
    {
        $this->info("🔬 SIMPLE TIK REGULATION TEST");
        $this->info("============================");
        $this->newLine();

        // Test known TIK regulation sources
        $tikSources = [
            'Kominfo JDIH' => 'https://jdih.kominfo.go.id',
            'BSSN JDIH' => 'https://jdih.bssn.go.id', 
            'Peraturan.go.id Search' => 'https://peraturan.go.id/search?q=teknologi+informasi',
            'BPK Database' => 'https://peraturan.bpk.go.id'
        ];

        foreach ($tikSources as $name => $url) {
            $this->line("Testing: {$name}");
            $this->line("URL: {$url}");
            
            try {
                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ])
                    ->get($url);
                
                if ($response->successful()) {
                    $html = $response->body();
                    $tikIndicators = $this->findTikIndicators($html);
                    
                    $this->info("  ✅ Accessible");
                    $this->line("  📊 Content length: " . strlen($html) . " chars");
                    
                    if (!empty($tikIndicators)) {
                        $this->info("  🎯 TIK content found:");
                        foreach ($tikIndicators as $indicator) {
                            $this->line("    - {$indicator}");
                        }
                    } else {
                        $this->line("  ⚠️ No obvious TIK content detected");
                    }
                } else {
                    $this->error("  ❌ HTTP {$response->status()}");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Failed: " . $e->getMessage());
            }
            
            $this->newLine();
            sleep(2);
        }

        // Test specific known TIK regulation patterns
        $this->info("🎯 Testing specific TIK regulation URLs:");
        
        $specificUrls = [
            'UU ITE (old format)' => 'https://peraturan.go.id/id/undang-undang-no-11-tahun-2008',
            'UU ITE (new format)' => 'https://peraturan.go.id/id/uu-no-11-tahun-2008',
            'PP E-Gov' => 'https://peraturan.go.id/id/pp-no-71-tahun-2019',
            'BPK UU ITE' => 'https://peraturan.bpk.go.id/Details/122894/uu-no-11-tahun-2008'
        ];

        foreach ($specificUrls as $name => $url) {
            $this->line("Testing: {$name}");
            
            try {
                $response = Http::timeout(10)->get($url);
                
                if ($response->successful()) {
                    $this->info("  ✅ Found!");
                    $this->line("  📄 Title found: " . $this->extractTitle($response->body()));
                } else {
                    $this->error("  ❌ Not found (HTTP {$response->status()})");
                }
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("💡 RECOMMENDATIONS:");
        $this->line("1. Focus on sources that responded positively");
        $this->line("2. Use working URL patterns for specific documents");  
        $this->line("3. Target jdih.kominfo.go.id for IT regulations");
        $this->line("4. Search peraturan.go.id with TIK-specific terms");
        
        return Command::SUCCESS;
    }

    private function findTikIndicators(string $html): array
    {
        $indicators = [];
        $tikTerms = [
            'teknologi informasi',
            'sistem elektronik',
            'cyber security',
            'data pribadi',
            'telekomunikasi',
            'informatika',
            'e-government'
        ];
        
        $htmlLower = strtolower($html);
        
        foreach ($tikTerms as $term) {
            if (strpos($htmlLower, $term) !== false) {
                $indicators[] = $term;
            }
        }
        
        return array_unique($indicators);
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.+?)<\/title>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        
        return 'Title not found';
    }
}