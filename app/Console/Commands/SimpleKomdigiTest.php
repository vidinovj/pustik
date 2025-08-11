<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SimpleKomdigiTest extends Command
{
    protected $signature = 'legal-docs:simple-komdigi-test';
    protected $description = 'Simple test to verify Komdigi accessibility';

    public function handle(): int
    {
        $this->info('🧪 Simple Komdigi Accessibility Test');
        $this->newLine();

        try {
            $this->info('🌐 Testing Komdigi website access...');
            
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->timeout(15)
            ->get('https://jdih.komdigi.go.id');
            
            if ($response->successful()) {
                $htmlSize = strlen($response->body());
                $this->info("✅ Komdigi accessible: {$htmlSize} bytes");
                
                $html = $response->body();
                
                // Quick content analysis
                $hasPeraturan = stripos($html, 'peraturan') !== false;
                $hasKeputusan = stripos($html, 'keputusan') !== false;
                $hasMenu = stripos($html, 'menu') !== false;
                $hasSearch = stripos($html, 'search') !== false || stripos($html, 'cari') !== false;
                
                $this->line("📋 Content Analysis:");
                $this->line("   Contains 'peraturan': " . ($hasPeraturan ? '✅ Yes' : '❌ No'));
                $this->line("   Contains 'keputusan': " . ($hasKeputusan ? '✅ Yes' : '❌ No'));
                $this->line("   Has navigation menu: " . ($hasMenu ? '✅ Yes' : '❌ No'));
                $this->line("   Has search function: " . ($hasSearch ? '✅ Yes' : '❌ No'));
                
                // Extract title
                if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $html, $matches)) {
                    $title = trim(strip_tags($matches[1]));
                    $this->line("   Page title: {$title}");
                }
                
                $this->newLine();
                if ($hasPeraturan || $hasKeputusan) {
                    $this->info('🎉 SUCCESS: Site contains regulation content!');
                    $this->line('The scraper should be able to find documents here.');
                } else {
                    $this->warn('⚠️  No obvious regulation indicators found.');
                    $this->line('Site may use different terminology or structure.');
                }
                
            } else {
                $this->error("❌ HTTP Error: " . $response->status());
                $this->line("Response: " . substr($response->body(), 0, 200));
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Connection Error: ' . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('💡 Next Steps:');
        $this->line('1. If site is accessible → The method error was the main issue');
        $this->line('2. Try scraping again: php artisan legal-docs:scrape-tik --source=komdigi --test-mode');
        $this->line('3. Check TIK filtering: php artisan legal-docs:debug-tik-filter');
        
        return Command::SUCCESS;
    }
}