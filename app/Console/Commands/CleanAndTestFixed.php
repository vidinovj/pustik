<?php

namespace App\Console\Commands;

use App\Models\DocumentSource;
use App\Models\LegalDocument;
use App\Services\Scrapers\FixedPeraturanScraper;
use Illuminate\Console\Command;

class CleanAndTestFixed extends Command
{
    protected $signature = 'legal-docs:clean-and-test';
    protected $description = 'Clean bad data and test fixed scraper';

    public function handle(): int
    {
        $this->info('🧹 Cleaning Bad Data and Testing Fixed Scraper');
        $this->newLine();

        // Step 1: Clean bad data
        $this->cleanBadData();

        // Step 2: Test fixed scraper
        $this->testFixedScraper();

        return Command::SUCCESS;
    }

    protected function cleanBadData(): void
    {
        $this->info('🧹 Cleaning bad data from legal_documents table...');
        
        // Find documents with login-related titles
        $badTitles = [
            'E-Pengundangan | Login',
            'Login | E-penerjemah',
            'Login',
            'E-Pengundangan',
            'E-penerjemah'
        ];

        $deletedCount = 0;
        
        foreach ($badTitles as $badTitle) {
            $count = LegalDocument::where('title', 'LIKE', "%{$badTitle}%")->count();
            if ($count > 0) {
                LegalDocument::where('title', 'LIKE', "%{$badTitle}%")->delete();
                $deletedCount += $count;
                $this->line("  • Deleted {$count} documents with title containing '{$badTitle}'");
            }
        }

        // Clean documents with very short titles (likely garbage)
        $shortTitleCount = LegalDocument::whereRaw('CHAR_LENGTH(title) < 15')->count();
        if ($shortTitleCount > 0) {
            LegalDocument::whereRaw('CHAR_LENGTH(title) < 15')->delete();
            $deletedCount += $shortTitleCount;
            $this->line("  • Deleted {$shortTitleCount} documents with titles shorter than 15 characters");
        }

        $this->info("✅ Cleaned {$deletedCount} bad documents from database");
        $this->newLine();
    }

    protected function testFixedScraper(): void
    {
        $this->info('🧪 Testing Fixed Scraper');
        
        // Get the source
        $source = DocumentSource::where('name', 'peraturan_go_id')->first();
        
        if (!$source) {
            $this->error('Peraturan.go.id source not found.');
            return;
        }

        $beforeCount = $source->legalDocuments()->count();
        $this->info("Documents before test: {$beforeCount}");

        try {
            // Create and run the fixed scraper
            $scraper = new FixedPeraturanScraper($source);
            
            $this->info("🔄 Running fixed scraper...");
            $this->line("Using discovered URL structure and login page detection...");
            
            $documents = $scraper->scrape();
            
            $afterCount = $source->legalDocuments()->count();
            
            $this->newLine();
            $this->info("✅ Fixed scraper completed!");
            $this->info("Documents processed this run: " . count($documents));
            $this->info("Total documents in database: {$afterCount}");
            $this->info("Net change: " . ($afterCount - $beforeCount));
            
            // Show sample of what was scraped
            if (count($documents) > 0) {
                $this->newLine();
                $this->info("📋 Sample of scraped documents:");
                $this->table(
                    ['ID', 'Title', 'Type', 'Document Number'],
                    collect($documents)->take(5)->map(function ($doc) {
                        return [
                            $doc->id,
                            substr($doc->title, 0, 60) . '...',
                            $doc->document_type,
                            $doc->document_number ?: 'N/A'
                        ];
                    })->toArray()
                );
                
                // Validate quality
                $this->validateDataQuality($documents);
                
            } else {
                $this->warn("⚠️  No documents were processed. Check logs for details.");
            }

        } catch (\Exception $e) {
            $this->error("❌ Fixed scraper failed: {$e->getMessage()}");
        }
    }

    protected function validateDataQuality(array $documents): void
    {
        $this->newLine();
        $this->info("🔍 Data Quality Check:");
        
        $loginTitleCount = 0;
        $shortTitleCount = 0;
        $goodTitleCount = 0;
        
        foreach ($documents as $doc) {
            if (stripos($doc->title, 'login') !== false || stripos($doc->title, 'E-Pengundangan') !== false) {
                $loginTitleCount++;
            } elseif (strlen($doc->title) < 15) {
                $shortTitleCount++;
            } else {
                $goodTitleCount++;
            }
        }
        
        $this->line("  ✅ Good titles: {$goodTitleCount}");
        if ($loginTitleCount > 0) {
            $this->line("  ❌ Login-related titles: {$loginTitleCount}");
        }
        if ($shortTitleCount > 0) {
            $this->line("  ⚠️  Short titles: {$shortTitleCount}");
        }
        
        $qualityPercent = count($documents) > 0 ? round(($goodTitleCount / count($documents)) * 100, 1) : 0;
        $this->info("📊 Data Quality: {$qualityPercent}%");
        
        if ($qualityPercent >= 80) {
            $this->info("🎉 Good data quality! The fixed scraper is working properly.");
        } elseif ($qualityPercent >= 50) {
            $this->warn("⚠️  Moderate data quality. Some improvements needed.");
        } else {
            $this->error("❌ Poor data quality. Scraper needs more fixes.");
        }
    }
}