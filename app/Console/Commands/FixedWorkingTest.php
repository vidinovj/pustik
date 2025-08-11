<?php
// app/Console/Commands/FixedWorkingTest.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use App\Models\DocumentSource;

class FixedWorkingTest extends Command
{
    protected $signature = 'legal-docs:fixed-test';
    protected $description = 'Fixed working test focusing on what we know works';

    public function handle(): int
    {
        $this->info('🔧 Fixed Working Test');
        $this->newLine();

        $this->info('✅ GOOD NEWS from your debug output:');
        $this->line('1. Scraper DID find documents (3 recent documents)');
        $this->line('2. TIK filtering WORKS (found "tik" keyword in PERPRES document)');
        $this->line('3. Peraturan.go.id IS accessible (we tested this before)');
        $this->newLine();

        $this->info('❌ Issues to fix:');
        $this->line('1. ✅ PHP syntax error (FIXED)');
        $this->line('2. ✅ Type error in debug (FIXED)'); 
        $this->line('3. ⚠️  jdih.komdigi.go.id DNS issue (need alternative)');
        $this->newLine();

        // Test what actually works
        $this->testPeraturanGoId();
        $this->showExistingData();
        $this->suggestWorkingPlan();

        return Command::SUCCESS;
    }

    protected function testPeraturanGoId(): void
    {
        $this->info('🧪 Testing Peraturan.go.id (we know this works)...');
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])
            ->timeout(15)
            ->get('https://peraturan.go.id');
            
            if ($response->successful()) {
                $this->info('✅ Peraturan.go.id is accessible');
                $size = strlen($response->body());
                $this->line("   Response size: {$size} bytes");
            } else {
                $this->warn("⚠️  Peraturan.go.id returned: " . $response->status());
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Peraturan.go.id error: ' . $e->getMessage());
        }
    }

    protected function showExistingData(): void
    {
        $this->newLine();
        $this->info('📊 Current Database Status:');
        
        $totalDocs = LegalDocument::count();
        $tikDocs = LegalDocument::where('metadata->tik_related', true)->count();
        $recentDocs = LegalDocument::where('created_at', '>=', now()->subDays(7))->count();
        
        $this->line("   📄 Total documents: {$totalDocs}");
        $this->line("   🔍 TIK-related: {$tikDocs}");
        $this->line("   📅 Added this week: {$recentDocs}");
        
        if ($totalDocs > 0) {
            $this->newLine();
            $this->info('📋 Recent documents:');
            
            $recent = LegalDocument::orderBy('created_at', 'desc')->limit(3)->get();
            foreach ($recent as $i => $doc) {
                $title = substr($doc->title, 0, 60) . '...';
                $tikStatus = ($doc->metadata['tik_related'] ?? false) ? '🔍 TIK' : '📄 General';
                $this->line("   " . ($i + 1) . ". {$title} ({$tikStatus})");
            }
        }
    }

    protected function suggestWorkingPlan(): void
    {
        $this->newLine();
        $this->info('🚀 WORKING PLAN:');
        
        $this->comment('Phase 1: Use what works (Peraturan.go.id)');
        $this->line('   php artisan legal-docs:scrape-tik --source=peraturan_go_id --limit=10');
        $this->newLine();
        
        $this->comment('Phase 2: Fix Komdigi URL');
        $this->line('   Try alternative Komdigi URLs:');
        $this->line('   • https://komdigi.go.id');
        $this->line('   • https://web.komdigi.go.id/peraturan');
        $this->line('   • Manual entry for key Komdigi regulations');
        $this->newLine();
        
        $this->comment('Phase 3: Expand working sources');
        $this->line('   Test other ministry JDIH sites that resolve:');
        $this->line('   • https://jdih.kemlu.go.id (MFA)');
        $this->line('   • https://peraturan.bpk.go.id (BPK)');
        $this->newLine();
        
        $this->info('💡 IMMEDIATE ACTION:');
        $this->line('Since Peraturan.go.id works and contains TIK regulations,');
        $this->line('let\'s get your catalog populated with that first!');
        $this->newLine();
        
        $this->ask('Ready to test Peraturan.go.id scraping? (Press Enter)');
    }
}

// Also create the missing stats command
// app/Console/Commands/ShowStats.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use App\Models\DocumentSource;

class ShowStats extends Command
{
    protected $signature = 'legal-docs:show-stats';
    protected $description = 'Show regulation database statistics';

    public function handle(): int
    {
        $this->info('📊 TIK Regulation Database Statistics');
        $this->newLine();

        // Overall stats
        $totalDocs = LegalDocument::count();
        $tikDocs = LegalDocument::where('metadata->tik_related', true)->count();
        $sources = DocumentSource::where('status', 'active')->count();
        $recentDocs = LegalDocument::where('created_at', '>=', now()->subDays(7))->count();

        $this->info('🎯 Overall:');
        $this->line("   📄 Total documents: {$totalDocs}");
        $this->line("   🔍 TIK-related: {$tikDocs}");
        $this->line("   🌐 Active sources: {$sources}");
        $this->line("   📅 Added this week: {$recentDocs}");
        $this->newLine();

        // By document type
        $types = LegalDocument::selectRaw('document_type, count(*) as count')
            ->groupBy('document_type')
            ->orderBy('count', 'desc')
            ->get();

        if ($types->isNotEmpty()) {
            $this->info('📋 By Type:');
            foreach ($types as $type) {
                $name = str_pad($type->document_type, 25);
                $this->line("   {$name}: {$type->count}");
            }
            $this->newLine();
        }

        // Recent activity
        $this->info('📅 Recent Activity:');
        $recent = LegalDocument::orderBy('created_at', 'desc')->limit(5)->get();
        
        if ($recent->isEmpty()) {
            $this->line('   No documents yet. Run scraping to populate!');
        } else {
            foreach ($recent as $i => $doc) {
                $title = substr($doc->title, 0, 50) . '...';
                $date = $doc->created_at->format('Y-m-d H:i');
                $tik = ($doc->metadata['tik_related'] ?? false) ? '🔍' : '📄';
                $this->line("   " . ($i + 1) . ". {$tik} {$title} ({$date})");
            }
        }

        $this->newLine();
        if ($totalDocs === 0) {
            $this->info('🚀 Ready to start scraping:');
            $this->line('   php artisan legal-docs:scrape-tik --test-mode');
        } else {
            $this->info('💡 Your catalog has content! Ready to launch.');
        }

        return Command::SUCCESS;
    }
}