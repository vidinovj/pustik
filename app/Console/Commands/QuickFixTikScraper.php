<?php
// app/Console/Commands/QuickFixTikScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class QuickFixTikScraper extends Command
{
    protected $signature = 'legal-docs:quick-fix-tik';
    protected $description = 'Quick fix for TIK scraper issues';

    public function handle(): int
    {
        $this->info('🔧 Quick Fix for TIK Scraper');
        $this->newLine();

        $this->info('Issues found:');
        $this->line('1. ✅ Fixed missing browseRecent() method');
        $this->line('2. ⚠️  TIK filtering too strict (found 2 docs, filtered 0)');
        $this->newLine();

        $this->info('Solutions:');
        $this->line('1. Run debug command to see what\'s being filtered:');
        $this->line('   php artisan legal-docs:debug-tik-filter');
        $this->newLine();

        $this->line('2. Try single source test with relaxed filtering:');
        $this->line('   php artisan legal-docs:scrape-tik --source=komdigi --limit=3');
        $this->newLine();

        $this->line('3. For Komdigi, ALL regulations are TIK-related, so try:');
        $this->line('   php artisan legal-docs:test-komdigi-direct');
        $this->newLine();

        // Let\'s create a direct Komdigi test
        $this->testKomdigiDirect();

        return Command::SUCCESS;
    }

    protected function testKomdigiDirect(): void
    {
        $this->info('🧪 Testing Komdigi Direct Access...');
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            ])
            ->timeout(15)
            ->get('https://jdih.komdigi.go.id');
            
            if ($response->successful()) {
                $this->info('✅ Komdigi site accessible');
                $htmlSize = strlen($response->body());
                $this->line("   Response size: {$htmlSize} bytes");
                
                // Quick check for document indicators
                $html = $response->body();
                $hasPeraturan = stripos($html, 'peraturan') !== false;
                $hasKeputusan = stripos($html, 'keputusan') !== false;
                $hasDocuments = stripos($html, 'dokumen') !== false;
                
                $this->line("   Contains 'peraturan': " . ($hasPeraturan ? 'Yes' : 'No'));
                $this->line("   Contains 'keputusan': " . ($hasKeputusan ? 'Yes' : 'No'));
                $this->line("   Contains 'dokumen': " . ($hasDocuments ? 'Yes' : 'No'));
                
                if ($hasPeraturan || $hasKeputusan || $hasDocuments) {
                    $this->info('🎉 Site has regulation content - scraper should work!');
                } else {
                    $this->warn('⚠️  No obvious regulation indicators found');
                }
                
            } else {
                $this->error('❌ Komdigi site returned: ' . $response->status());
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error accessing Komdigi: ' . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('💡 Next Steps:');
        $this->line('1. The method error is fixed');
        $this->line('2. Run the debug command to see filtering issues');
        $this->line('3. Try test again: php artisan legal-docs:scrape-tik --test-mode');
    }
}
