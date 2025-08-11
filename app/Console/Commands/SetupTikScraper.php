<?php
// app/Console/Commands/SetupTikScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentSource;
use Illuminate\Support\Facades\DB;

class SetupTikScraper extends Command
{
    protected $signature = 'legal-docs:setup-tik {--reset}';
    protected $description = 'Setup TIK regulation scraper sources and test configuration';

    public function handle(): int
    {
        $this->info('🛠️  Setting up TIK Regulation Scraper');
        $this->newLine();

        if ($this->option('reset')) {
            $this->resetSources();
        }

        $this->setupDocumentSources();
        $this->testConfiguration();
        $this->showUsageInstructions();

        return Command::SUCCESS;
    }

    protected function resetSources(): void
    {
        $this->warn('🗑️  Resetting document sources...');
        
        $sources = ['peraturan_go_id', 'kemlu_tik', 'komdigi', 'kemenko'];
        
        foreach ($sources as $sourceName) {
            DocumentSource::where('name', $sourceName)->delete();
            $this->line("   ✅ Removed: {$sourceName}");
        }
        
        $this->newLine();
    }

    protected function setupDocumentSources(): void
    {
        $this->info('📋 Setting up document sources...');
        
        $sources = [
            [
                'name' => 'peraturan_go_id',
                'display_name' => 'Peraturan.go.id (National Repository)',
                'base_url' => 'https://peraturan.go.id',
                'config' => [
                    'scraper_type' => 'browser',
                    'tik_focused' => true,
                    'priority' => 1,
                    'request_delay' => 3,
                    'timeout' => 45,
                    'browser_automation' => true
                ]
            ],
            [
                'name' => 'kemlu_tik',
                'display_name' => 'JDIH Kemlu - TIK Regulations',
                'base_url' => 'https://jdih.kemlu.go.id',
                'config' => [
                    'scraper_type' => 'enhanced_http',
                    'tik_focused' => true,
                    'priority' => 2,
                    'request_delay' => 2,
                    'timeout' => 30,
                    'search_terms' => ['teknologi informasi', 'digital', 'cyber diplomacy']
                ]
            ],
            [
                'name' => 'komdigi',
                'display_name' => 'JDIH Komdigi - ICT Ministry',
                'base_url' => 'https://jdih.komdigi.go.id',
                'config' => [
                    'scraper_type' => 'enhanced_http',
                    'tik_focused' => true,
                    'priority' => 1,
                    'request_delay' => 2,
                    'timeout' => 30,
                    'all_regulations_relevant' => true
                ]
            ],
            [
                'name' => 'kemenko',
                'display_name' => 'JDIH Kemenko - Digital Economy',
                'base_url' => 'https://jdih.kemenko.go.id',
                'config' => [
                    'scraper_type' => 'enhanced_http',
                    'tik_focused' => true,
                    'priority' => 3,
                    'request_delay' => 2,
                    'timeout' => 30,
                    'focus_areas' => ['digital economy', 'fintech', 'startup ecosystem']
                ]
            ]
        ];

        foreach ($sources as $sourceData) {
            $source = DocumentSource::updateOrCreate(
                ['name' => $sourceData['name']],
                [
                    'display_name' => $sourceData['display_name'],
                    'base_url' => $sourceData['base_url'],
                    'status' => 'active',
                    'config' => $sourceData['config']
                ]
            );

            $this->line("   ✅ {$source->display_name}");
        }
        
        $this->newLine();
    }

    protected function testConfiguration(): void
    {
        $this->info('🧪 Testing configuration...');
        
        // Test Node.js availability
        $nodeCheck = shell_exec('node --version 2>/dev/null');
        if ($nodeCheck) {
            $this->line("   ✅ Node.js: " . trim($nodeCheck));
        } else {
            $this->error("   ❌ Node.js not found - required for browser automation");
            $this->line("      Install from: https://nodejs.org/");
        }
        
        // Test Puppeteer availability
        $puppeteerCheck = shell_exec('npm list puppeteer --depth=0 2>/dev/null');
        if (stripos($puppeteerCheck, 'puppeteer@') !== false) {
            $this->line("   ✅ Puppeteer: Available");
        } else {
            $this->warn("   ⚠️  Puppeteer not found - installing...");
            $this->line("      Run: npm install puppeteer");
        }
        
        // Test database connection
        try {
            DB::connection()->getPdo();
            $sourceCount = DocumentSource::count();
            $this->line("   ✅ Database: Connected ({$sourceCount} sources configured)");
        } catch (\Exception $e) {
            $this->error("   ❌ Database: " . $e->getMessage());
        }
        
        // Test HTTP client
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get('https://httpbin.org/get');
            if ($response->successful()) {
                $this->line("   ✅ HTTP Client: Working");
            } else {
                $this->warn("   ⚠️  HTTP Client: Non-200 response");
            }
        } catch (\Exception $e) {
            $this->error("   ❌ HTTP Client: " . $e->getMessage());
        }
        
        $this->newLine();
    }

    protected function showUsageInstructions(): void
    {
        $this->info('🚀 READY TO SCRAPE!');
        $this->newLine();
        
        $this->info('Quick Start Commands:');
        $this->line('');
        
        $this->comment('1. Test run (5 docs per source):');
        $this->line('   php artisan legal-docs:scrape-tik --test-mode --limit=5');
        $this->line('');
        
        $this->comment('2. Single source test:');
        $this->line('   php artisan legal-docs:scrape-tik --source=peraturan_go_id --limit=10');
        $this->line('   php artisan legal-docs:scrape-tik --source=kemlu_tik --limit=10');
        $this->line('');
        
        $this->comment('3. Full production run:');
        $this->line('   php artisan legal-docs:scrape-tik --limit=100');
        $this->line('');
        
        $this->comment('4. Check existing data:');
        $this->line('   php artisan legal-docs:stats');
        $this->line('');
        
        $this->info('💡 Tips:');
        $this->line('  • Start with --test-mode to verify everything works');
        $this->line('  • Browser automation (peraturan_go_id) is slowest but most reliable');
        $this->line('  • HTTP scrapers (kemlu, komdigi) are faster');
        $this->line('  • Use --limit to control how many docs per source');
        $this->line('  • Check storage/logs/legal-documents.log for detailed logs');
        $this->newLine();
        
        $this->info('🎯 Expected Results:');
        $this->line('  • Test run: 5-20 TIK regulations');
        $this->line('  • Full run: 100-300 TIK regulations');
        $this->line('  • Ready to launch your catalog with real content!');
        $this->newLine();
        
        $this->ask('Press Enter to continue, or Ctrl+C to exit');
    }
}

// app/Console/Commands/ShowTikStats.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use App\Models\DocumentSource;
use Illuminate\Support\Facades\DB;

class ShowTikStats extends Command
{
    protected $signature = 'legal-docs:stats';
    protected $description = 'Show TIK regulation scraping statistics';

    public function handle(): int
    {
        $this->info('📊 TIK Regulation Database Statistics');
        $this->newLine();

        $this->showOverallStats();
        $this->showSourceBreakdown();
        $this->showRecentActivity();
        $this->showSampleDocuments();

        return Command::SUCCESS;
    }

    protected function showOverallStats(): void
    {
        $totalDocs = LegalDocument::count();
        $activeSources = DocumentSource::where('status', 'active')->count();
        $tikDocs = LegalDocument::where('metadata->tik_related', true)->count();
        $recentDocs = LegalDocument::where('created_at', '>=', now()->subDays(7))->count();

        $this->info('🎯 Overall Statistics:');
        $this->line("   📄 Total Regulations: {$totalDocs}");
        $this->line("   🔍 TIK-Related: {$tikDocs}");
        $this->line("   🌐 Active Sources: {$activeSources}");
        $this->line("   📅 Added This Week: {$recentDocs}");
        $this->newLine();
    }

    protected function showSourceBreakdown(): void
    {
        $this->info('📋 By Source:');
        
        $sourceStats = DB::table('legal_documents')
            ->join('document_sources', 'legal_documents.document_source_id', '=', 'document_sources.id')
            ->select('document_sources.display_name', DB::raw('count(*) as count'))
            ->groupBy('document_sources.display_name')
            ->orderBy('count', 'desc')
            ->get();

        if ($sourceStats->isEmpty()) {
            $this->line('   No documents found. Run scraping first!');
        } else {
            foreach ($sourceStats as $stat) {
                $name = str_pad($stat->display_name, 30);
                $this->line("   {$name}: {$stat->count} docs");
            }
        }
        
        $this->newLine();
    }

    protected function showRecentActivity(): void
    {
        $this->info('📅 Recent Activity (Last 7 Days):');
        
        $recentActivity = DB::table('legal_documents')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'desc')
            ->get();

        if ($recentActivity->isEmpty()) {
            $this->line('   No recent activity.');
        } else {
            foreach ($recentActivity as $activity) {
                $date = $activity->date;
                $count = $activity->count;
                $this->line("   {$date}: {$count} documents");
            }
        }
        
        $this->newLine();
    }

    protected function showSampleDocuments(): void
    {
        $this->info('📄 Recent TIK Regulations:');
        
        $sampleDocs = LegalDocument::where('metadata->tik_related', true)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['title', 'document_type', 'created_at']);

        if ($sampleDocs->isEmpty()) {
            $this->line('   No TIK regulations found yet.');
            $this->line('   Run: php artisan legal-docs:scrape-tik --test-mode');
        } else {
            foreach ($sampleDocs as $i => $doc) {
                $title = substr($doc->title, 0, 60) . '...';
                $type = $doc->document_type;
                $date = $doc->created_at->format('Y-m-d');
                
                $this->line("   " . ($i + 1) . ". {$title}");
                $this->line("      Type: {$type} | Added: {$date}");
            }
        }
        
        $this->newLine();
        $this->info('💡 Ready to launch your TIK regulation catalog!');
    }
}