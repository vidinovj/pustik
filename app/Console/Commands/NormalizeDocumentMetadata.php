<?php
// app/Console/Commands/NormalizeDocumentMetadata.php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use Illuminate\Console\Command;
use Carbon\Carbon;

class NormalizeDocumentMetadata extends Command
{
    protected $signature = 'documents:normalize-metadata {--dry-run : Show what would be changed without saving}';
    protected $description = 'Normalize all legal document metadata to canonical structure';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }

        $documents = LegalDocument::all();
        $this->info("📋 Processing {$documents->count()} documents...");
        
        $stats = [
            'seeded' => 0,
            'quick_populated' => 0, 
            'tik_focused' => 0,
            'uncategorized' => 0,
            'normalized' => 0
        ];

        foreach ($documents as $document) {
            $originalMetadata = $document->metadata ?? [];
            $sourceType = $this->identifySourceType($originalMetadata);
            $stats[$sourceType]++;
            
            $normalizedMetadata = $this->normalizeMetadata($originalMetadata, $sourceType);
            
            if ($this->hasChanges($originalMetadata, $normalizedMetadata)) {
                $this->info("📝 [{$sourceType}] {$document->title}");
                
                if ($isDryRun) {
                    $this->line("   BEFORE: " . json_encode($originalMetadata, JSON_UNESCAPED_UNICODE));
                    $this->line("   AFTER:  " . json_encode($normalizedMetadata, JSON_UNESCAPED_UNICODE));
                } else {
                    $document->metadata = $normalizedMetadata;
                    $document->save();
                    $stats['normalized']++;
                }
                $this->newLine();
            }
        }

        $this->displayStats($stats, $isDryRun);
    }

    private function identifySourceType(array $metadata): string
    {
        $extractionMethod = $metadata['extraction_method'] ?? null;
        
        if ($extractionMethod === 'manual_core_entry') {
            return 'quick_populated';
        }
        
        if ($extractionMethod === 'tik_focused_scraper') {
            return 'tik_focused';
        }
        
        if ($extractionMethod === 'enhanced_multi_strategy') {
            return 'uncategorized';
        }
        
        if (!$extractionMethod && isset($metadata['agency'])) {
            return 'seeded';
        }
        
        return 'uncategorized';
    }

    private function normalizeMetadata(array $metadata, string $sourceType): array
    {
        $canonical = [
            'agency' => null,
            'summary' => null,
            'category' => null,
            'keywords' => [],
            'entry_date' => Carbon::now()->toISOString(),
            'importance' => null,
            'tik_related' => 0,
            'extraction_method' => 'normalized_' . $sourceType
        ];

        switch ($sourceType) {
            case 'seeded':
                return $this->normalizeSeeded($metadata, $canonical);
                
            case 'quick_populated':
                return $this->normalizeQuickPopulated($metadata, $canonical);
                
            case 'tik_focused':
                return $this->normalizeTikFocused($metadata, $canonical);
                
            case 'uncategorized':
                return $this->normalizeUncategorized($metadata, $canonical);
        }

        return $canonical;
    }

    private function normalizeSeeded(array $metadata, array $canonical): array
    {
        return array_merge($canonical, [
            'agency' => $metadata['agency'] ?? null,
            'summary' => 'Legacy seeded document - summary needs manual update',
            'category' => 'Legacy',
            'importance' => 'unknown',
            'extraction_method' => 'normalized_seeded'
        ]);
    }

    private function normalizeQuickPopulated(array $metadata, array $canonical): array
    {
        // Already canonical, just ensure all fields exist
        return array_merge($canonical, [
            'agency' => $metadata['agency'] ?? null,
            'summary' => $metadata['summary'] ?? null,
            'category' => $metadata['category'] ?? null,
            'keywords' => $metadata['keywords'] ?? [],
            'entry_date' => $metadata['entry_date'] ?? $canonical['entry_date'],
            'importance' => $metadata['importance'] ?? null,
            'tik_related' => $metadata['tik_related'] ?? 0,
            'extraction_method' => 'manual_core_entry'
        ]);
    }

    private function normalizeTikFocused(array $metadata, array $canonical): array
    {
        return array_merge($canonical, [
            'agency' => $metadata['agency'] === 'specific_tik_url' ? 'TIK Scraped' : $metadata['agency'],
            'summary' => $metadata['summary'] ?? null,
            'category' => !empty($metadata['category']) ? $metadata['category'] : 'TIK Related',
            'keywords' => $metadata['keywords'] ?? [],
            'entry_date' => $metadata['entry_date'] ?? $canonical['entry_date'],
            'importance' => !empty($metadata['importance']) ? $metadata['importance'] : 'medium',
            'tik_related' => 1, // All TIK focused are TIK related
            'extraction_method' => 'tik_focused_scraper'
        ]);
    }

    private function normalizeUncategorized(array $metadata, array $canonical): array
    {
        // Map legacy scraper format to canonical
        $keywords = [];
        
        // Extract keywords from title if available
        if (isset($metadata['title'])) {
            $title = strtolower($metadata['title']);
            if (strpos($title, 'informasi') !== false || strpos($title, 'elektronik') !== false) {
                $keywords[] = 'informasi elektronik';
            }
            if (strpos($title, 'transaksi') !== false) {
                $keywords[] = 'transaksi elektronik';
            }
        }

        return array_merge($canonical, [
            'agency' => $this->mapSourceToAgency($metadata['source'] ?? 'Unknown'),
            'summary' => $metadata['title'] ?? 'Legacy document - summary needs update',
            'category' => $this->mapLegacyCategory($metadata['category'] ?? 'unknown'),
            'keywords' => $keywords,
            'entry_date' => $metadata['extracted_at'] ?? $canonical['entry_date'],
            'importance' => 'medium',
            'tik_related' => $this->detectTikRelated($metadata),
            'extraction_method' => 'enhanced_multi_strategy'
        ]);
    }

    private function mapSourceToAgency(string $source): string
    {
        $mapping = [
            'peraturan.go.id' => 'Sekretariat Kabinet RI',
            'bpkp.go.id' => 'BPKP',
            'kemlu.go.id' => 'Kementerian Luar Negeri'
        ];

        return $mapping[$source] ?? ucfirst(str_replace('.go.id', '', $source));
    }

    private function mapLegacyCategory(string $category): string
    {
        $mapping = [
            'uu' => 'Primary Law',
            'pp' => 'Government Regulation',
            'perpres' => 'Presidential Regulation',
            'permen' => 'Ministerial Regulation'
        ];

        return $mapping[strtolower($category)] ?? ucfirst($category);
    }

    private function detectTikRelated(array $metadata): int
    {
        $tikKeywords = ['informasi', 'elektronik', 'transaksi', 'digital', 'teknologi'];
        $text = strtolower(json_encode($metadata));
        
        foreach ($tikKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 1;
            }
        }
        
        return 0;
    }

    private function hasChanges(array $original, array $normalized): bool
    {
        // Compare arrays after sorting keys to ignore order differences
        ksort($original);
        ksort($normalized);
        
        return $original !== $normalized;
    }

    private function displayStats(array $stats, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 PROCESSING SUMMARY:');
        $this->table(
            ['Source Type', 'Count'],
            [
                ['Seeded', $stats['seeded']],
                ['Quick Populated', $stats['quick_populated']], 
                ['TIK Focused', $stats['tik_focused']],
                ['Uncategorized', $stats['uncategorized']],
                ['🔄 Normalized', $isDryRun ? 'DRY RUN' : $stats['normalized']]
            ]
        );

        if ($isDryRun) {
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $this->info("✅ Normalized {$stats['normalized']} documents to canonical metadata structure.");
        }
    }
}