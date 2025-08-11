<?php
// app/Console/Commands/ImprovedTikScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use App\Services\Scrapers\BrowserPeraturanScraper;
use App\Models\DocumentSource;

class ImprovedTikScraper extends Command
{
    protected $signature = 'legal-docs:improved-tik-scraper {--limit=10}';
    protected $description = 'Improved TIK scraper with better keyword filtering';

    // More specific TIK keywords to avoid false positives
    protected array $strongTikKeywords = [
        // Core IT terms
        'teknologi informasi',
        'sistem informasi', 
        'informatika',
        'komputerisasi',
        'digitalisasi',
        'transformasi digital',
        
        // Communications
        'telekomunikasi',
        'komunikasi elektronik',
        'jaringan telekomunikasi',
        
        // Digital services
        'layanan elektronik',
        'sistem elektronik',
        'transaksi elektronik',
        'e-government',
        'e-commerce',
        'e-procurement',
        
        // Cyber & Security
        'keamanan siber',
        'keamanan informasi',
        'cyber security',
        'data pribadi',
        'perlindungan data',
        
        // Specific tech
        'aplikasi',
        'perangkat lunak',
        'software',
        'hardware',
        'database',
        'server',
        'website',
        'platform digital',
        
        // Government IT
        'smart city',
        'pemerintahan digital',
        'SPBE',
        'pemerintahan berbasis elektronik',
        'administrasi digital'
    ];

    // Keywords that might appear in non-TIK contexts
    protected array $excludeIfContains = [
        'perdagangan', 'protocol', 'kerjasama', 'bilateral',
        'investasi', 'ekonomi', 'keuangan', 'perbankan',
        'politik', 'diplomatik', 'internasional'
    ];

    public function handle(): int
    {
        $this->info('🎯 Improved TIK Scraper - Better Keyword Filtering');
        $this->newLine();

        $limit = (int) $this->option('limit');

        // Test current documents first
        $this->analyzeExistingDocuments();
        
        // Run improved scraper
        $this->runImprovedScraper($limit);

        return Command::SUCCESS;
    }

    protected function analyzeExistingDocuments(): void
    {
        $this->info('🔍 Analyzing existing documents...');
        
        $docs = LegalDocument::all();
        
        foreach ($docs as $doc) {
            $originalTik = $doc->metadata['tik_related'] ?? false;
            $improvedTik = $this->isReallyTikRelated($doc->title, $doc->full_text ?? '');
            
            $status = $originalTik === $improvedTik ? '✅' : '🔄';
            $tikStatus = $improvedTik ? 'TIK' : 'NOT TIK';
            
            $this->line("   {$status} ID {$doc->id}: {$tikStatus}");
            $this->line("      " . substr($doc->title, 0, 70) . '...');
            
            if ($originalTik !== $improvedTik) {
                // Update the document
                $metadata = $doc->metadata ?? [];
                $metadata['tik_related'] = $improvedTik;
                $metadata['filtering_method'] = 'improved_keywords';
                $doc->metadata = $metadata;
                $doc->save();
                
                $this->line("      🔄 Updated TIK status");
            }
        }
        
        $this->newLine();
    }

    protected function runImprovedScraper(int $limit): void
    {
        $this->info("🌐 Running improved scraper with limit {$limit}...");
        
        // Get Peraturan.go.id source
        $source = DocumentSource::where('name', 'peraturan_go_id')->first();
        
        if (!$source) {
            $this->error('Peraturan.go.id source not found. Run setup first.');
            return;
        }

        try {
            $scraper = new BrowserPeraturanScraper($source);
            $documents = $scraper->scrapeWithLimit($limit);
            
            $this->info("✅ Browser scraper found " . count($documents) . " documents");
            
            // Apply improved filtering
            $realTikDocs = [];
            
            foreach ($documents as $doc) {
                if ($this->isReallyTikRelated($doc->title, $doc->full_text ?? '')) {
                    // Update metadata
                    $metadata = $doc->metadata ?? [];
                    $metadata['tik_related'] = true;
                    $metadata['filtering_method'] = 'improved_keywords';
                    $metadata['keywords_found'] = $this->getMatchingStrongKeywords($doc->title, $doc->full_text ?? '');
                    $doc->metadata = $metadata;
                    $doc->save();
                    
                    $realTikDocs[] = $doc;
                }
            }
            
            $this->newLine();
            $this->info("🎯 IMPROVED FILTERING RESULTS:");
            $this->line("   📄 Total documents found: " . count($documents));
            $this->line("   🔍 Real TIK documents: " . count($realTikDocs));
            $this->line("   📊 TIK accuracy: " . (count($documents) > 0 ? round(count($realTikDocs) / count($documents) * 100, 1) : 0) . "%");
            
            if (count($realTikDocs) > 0) {
                $this->newLine();
                $this->info("📋 Real TIK Documents Found:");
                foreach ($realTikDocs as $i => $doc) {
                    $title = substr($doc->title, 0, 60) . '...';
                    $keywords = implode(', ', $doc->metadata['keywords_found'] ?? []);
                    $this->line("   " . ($i + 1) . ". {$title}");
                    $this->line("      Keywords: {$keywords}");
                }
            } else {
                $this->warn("⚠️  No real TIK documents found. Consider:");
                $this->line("     • Expanding search to more specific IT regulations");
                $this->line("     • Targeting specific ministries (Kominfo, etc.)");
                $this->line("     • Manual entry of key IT regulations");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Scraper failed: " . $e->getMessage());
        }
    }

    protected function isReallyTikRelated(string $title, string $content): bool
    {
        $text = strtolower($title . ' ' . $content);
        
        // First check for exclusion keywords
        foreach ($this->excludeIfContains as $exclude) {
            if (stripos($text, $exclude) !== false) {
                // If it contains trade/diplomatic terms, be more strict
                return $this->hasStrongTikKeywords($text, 2); // Need at least 2 strong keywords
            }
        }
        
        // Check for strong TIK keywords
        return $this->hasStrongTikKeywords($text, 1); // Need at least 1 strong keyword
    }

    protected function hasStrongTikKeywords(string $text, int $minRequired = 1): bool
    {
        $found = 0;
        
        foreach ($this->strongTikKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $found++;
                if ($found >= $minRequired) {
                    return true;
                }
            }
        }
        
        return false;
    }

    protected function getMatchingStrongKeywords(string $title, string $content): array
    {
        $text = strtolower($title . ' ' . $content);
        $matches = [];
        
        foreach ($this->strongTikKeywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $matches[] = $keyword;
            }
        }
        
        return $matches;
    }
}

// Also create a manual TIK entry command for key regulations

// app/Console/Commands/AddManualTikRegulations.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use App\Models\DocumentSource;

class AddManualTikRegulations extends Command
{
    protected $signature = 'legal-docs:add-manual-tik';
    protected $description = 'Manually add key TIK regulations that we know exist';

    public function handle(): int
    {
        $this->info('📝 Adding Manual TIK Regulations');
        $this->newLine();

        $keyTikRegulations = [
            [
                'title' => 'UU No. 11 Tahun 2008 Tentang Informasi dan Transaksi Elektronik',
                'document_type' => 'Undang-Undang',
                'document_number' => '11/2008',
                'agency' => 'DPR RI',
                'subject' => 'Informasi dan Transaksi Elektronik (ITE)',
                'year' => 2008
            ],
            [
                'title' => 'UU No. 19 Tahun 2016 Tentang Perubahan UU ITE',
                'document_type' => 'Undang-Undang', 
                'document_number' => '19/2016',
                'agency' => 'DPR RI',
                'subject' => 'Perubahan UU Informasi dan Transaksi Elektronik',
                'year' => 2016
            ],
            [
                'title' => 'UU No. 27 Tahun 2022 Tentang Perlindungan Data Pribadi',
                'document_type' => 'Undang-Undang',
                'document_number' => '27/2022', 
                'agency' => 'DPR RI',
                'subject' => 'Perlindungan Data Pribadi',
                'year' => 2022
            ],
            [
                'title' => 'PP No. 71 Tahun 2019 Tentang Penyelenggaraan Sistem dan Transaksi Elektronik',
                'document_type' => 'Peraturan Pemerintah',
                'document_number' => '71/2019',
                'agency' => 'Pemerintah RI',
                'subject' => 'Sistem dan Transaksi Elektronik',
                'year' => 2019
            ],
            [
                'title' => 'Perpres No. 39 Tahun 2019 Tentang Satu Data Indonesia',
                'document_type' => 'Peraturan Presiden',
                'document_number' => '39/2019',
                'agency' => 'Presiden RI',
                'subject' => 'Satu Data Indonesia',
                'year' => 2019
            ]
        ];

        // Get or create manual source
        $source = DocumentSource::firstOrCreate([
            'name' => 'manual_tik_entry'
        ], [
            'display_name' => 'Manual TIK Entry',
            'base_url' => 'https://peraturan.go.id',
            'status' => 'active'
        ]);

        $added = 0;

        foreach ($keyTikRegulations as $regData) {
            try {
                // Check if already exists
                $existing = LegalDocument::where('document_number', $regData['document_number'])->first();
                
                if ($existing) {
                    $this->line("   ⚠️  Already exists: " . $regData['title']);
                    continue;
                }

                $document = LegalDocument::create([
                    'title' => $regData['title'],
                    'document_type' => $regData['document_type'],
                    'document_number' => $regData['document_number'],
                    'issue_date' => $regData['year'] . '-01-01', // Approximate date
                    'source_url' => 'https://peraturan.go.id/id/' . strtolower(str_replace(' ', '-', $regData['document_type'])) . '-no-' . str_replace('/', '-tahun-', $regData['document_number']),
                    'document_source_id' => $source->id,
                    'status' => 'active',
                    'metadata' => [
                        'agency' => $regData['agency'],
                        'subject' => $regData['subject'],
                        'source_site' => 'Manual Entry',
                        'tik_related' => true,
                        'extraction_method' => 'manual_entry',
                        'importance' => 'high',
                        'category' => 'core_it_legislation'
                    ],
                    'full_text' => $regData['title'] . ' - ' . $regData['subject'],
                    'checksum' => md5($regData['title'] . $regData['document_number'])
                ]);

                $this->line("   ✅ Added: " . $regData['title']);
                $added++;

            } catch (\Exception $e) {
                $this->error("   ❌ Failed to add: " . $regData['title']);
                $this->line("      Error: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("📊 Summary: Added {$added} key TIK regulations");
        $this->info("🎯 These are the most important IT laws in Indonesia!");

        return Command::SUCCESS;
    }
}