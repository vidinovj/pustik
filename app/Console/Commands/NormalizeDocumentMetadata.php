<?php

// app/Console/Commands/NormalizeDocumentMetadata.php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NormalizeDocumentMetadata extends Command
{
    protected $signature = 'documents:normalize-metadata {--dry-run : Show what would be changed without saving} {--force : Force normalization on all documents}';

    protected $description = 'Normalize all legal document metadata to canonical structure and flatten complex fields';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }

        if ($isForce) {
            $this->info('💪 FORCE MODE - Normalizing all documents');
        }

        $documents = LegalDocument::all();
        $this->info("📋 Processing {$documents->count()} documents...");

        $stats = [
            'seeded' => 0,
            'quick_populated' => 0,
            'tik_focused' => 0,
            'scraped' => 0,
            'uncategorized' => 0,
            'normalized' => 0,
            'flattened_tik_summary' => 0,
            'agency_inferred' => 0,
            'keywords_sourced' => 0,
        ];

        foreach ($documents as $document) {
            $originalMetadata = $document->metadata ?? [];
            $sourceType = $this->identifySourceType($originalMetadata);
            $stats[$sourceType]++;

            $normalizedMetadata = $this->normalizeMetadata($document, $originalMetadata, $sourceType);

            if ($isForce || $this->hasChanges($originalMetadata, $normalizedMetadata)) {
                $this->info("📝 [{$sourceType}] {$document->title}");

                // Track specific changes
                if (isset($originalMetadata['tik_summary']) && ! isset($normalizedMetadata['tik_summary'])) {
                    $stats['flattened_tik_summary']++;
                }
                if (($originalMetadata['agency'] ?? null) !== ($normalizedMetadata['agency'] ?? null)) {
                    $stats['agency_inferred']++;
                }
                if (count($normalizedMetadata['keywords']) > count($originalMetadata['keywords'] ?? [])) {
                    $stats['keywords_sourced']++;
                }

                if ($isDryRun) {
                    $this->displayChanges($originalMetadata, $normalizedMetadata);
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

        if ($extractionMethod === 'manual_core_entry' || $extractionMethod === 'normalized_quick_populated') {
            return 'quick_populated';
        }

        if ($extractionMethod === 'tik_focused_scraper' || $extractionMethod === 'normalized_tik_focused') {
            return 'tik_focused';
        }

        if ($extractionMethod === 'enhanced_multi_strategy' || $extractionMethod === 'normalized_uncategorized') {
            return 'uncategorized';
        }

        if ($extractionMethod === 'normalized_bpk_scraped' || $extractionMethod === 'normalized_scraped') {
            return 'scraped';
        }

        if (! $extractionMethod && isset($metadata['agency'])) {
            return 'seeded';
        }

        return 'uncategorized';
    }

    private function normalizeMetadata(LegalDocument $document, array $metadata, string $sourceType): array
    {
        $canonical = [
            'agency' => $this->preserveOrInferAgency($document, $metadata),
            'summary' => $metadata['summary'] ?? 'Document summary needs update',
            'category' => $this->preserveOrInferCategory($document, $metadata),
            'keywords' => $this->getKeywordsFromTikKeywordsColumn($document),
            'entry_date' => $metadata['entry_date'] ?? Carbon::now()->toISOString(),
            'importance' => $this->preserveOrInferImportance($document, $metadata),
            'tik_related' => $document->is_tik_related ?? ($document->tik_relevance_score > 10 ? 1 : 0),
            'extraction_method' => 'normalized_'.$sourceType,
        ];

        // Handle different source types
        switch ($sourceType) {
            case 'seeded':
                return $this->normalizeSeeded($metadata, $canonical);

            case 'quick_populated':
                return $this->normalizeQuickPopulated($metadata, $canonical);

            case 'tik_focused':
                return $this->normalizeTikFocused($metadata, $canonical);

            case 'scraped':
                return $this->normalizeScraped($document, $metadata, $canonical);

            case 'uncategorized':
                return $this->normalizeUncategorized($metadata, $canonical);
        }

        return $canonical;
    }

    private function normalizeScraped(LegalDocument $document, array $metadata, array $canonical): array
    {
        // Flatten tik_summary into canonical format
        $tikSummary = $metadata['tik_summary'] ?? [];

        // Extract keywords from tik_summary.found_keywords if exists
        $tikKeywords = [];
        if (isset($tikSummary['found_keywords']) && is_array($tikSummary['found_keywords'])) {
            foreach ($tikSummary['found_keywords'] as $keyword) {
                if (is_array($keyword) && isset($keyword['term'])) {
                    $tikKeywords[] = $keyword['term'];
                }
            }
        }

        // Merge with column-based keywords
        $columnKeywords = $this->getKeywordsFromTikKeywordsColumn($document);
        $allKeywords = array_unique(array_merge($columnKeywords, $tikKeywords));

        return array_merge($canonical, [
            'agency' => $canonical['agency'], // This already uses preserveOrInferAgency logic
            'summary' => $metadata['summary'] ?? 'Scraped document - summary needs update',
            'category' => $canonical['category'], // This already uses preserveOrInferCategory logic
            'keywords' => $allKeywords,
            'importance' => $canonical['importance'], // This already uses preserveOrInferImportance logic
            'tik_related' => $document->is_tik_related ?? ($tikSummary['is_highly_tik_related'] ?? 0),
            'tik_score' => $tikSummary['tik_score'] ?? $document->tik_relevance_score ?? 0,
            'tik_relevance_level' => $tikSummary['relevance_level'] ?? 'unknown',
            'tik_primary_category' => $tikSummary['primary_category'] ?? 'general',
            'extraction_method' => 'normalized_scraped',
            // Note: tik_summary is intentionally omitted to flatten the structure
        ]);
    }

    private function normalizeSeeded(array $metadata, array $canonical): array
    {
        return array_merge($canonical, [
            'agency' => $metadata['agency'] ?? $canonical['agency'],
            'summary' => 'Legacy seeded document - summary needs manual update',
            'category' => 'Legacy',
            'importance' => 'unknown',
            'extraction_method' => 'normalized_seeded',
        ]);
    }

    private function normalizeQuickPopulated(array $metadata, array $canonical): array
    {
        // Quick populated is already in good format, preserve existing data
        return array_merge($canonical, [
            'agency' => $metadata['agency'] ?? $canonical['agency'],
            'summary' => $metadata['summary'] ?? $canonical['summary'],
            'category' => $metadata['category'] ?? $canonical['category'],
            'keywords' => array_unique(array_merge($canonical['keywords'], $metadata['keywords'] ?? [])),
            'entry_date' => $metadata['entry_date'] ?? $canonical['entry_date'],
            'importance' => $metadata['importance'] ?? $canonical['importance'],
            'tik_related' => $metadata['tik_related'] ?? $canonical['tik_related'],
            'extraction_method' => 'normalized_quick_populated',
        ]);
    }

    private function normalizeTikFocused(array $metadata, array $canonical): array
    {
        // Special handling for specific_tik_url agency
        $agency = $metadata['agency'] === 'specific_tik_url' ? 'TIK Scraped' : $canonical['agency'];

        return array_merge($canonical, [
            'agency' => $agency,
            'summary' => $metadata['summary'] ?? $canonical['summary'],
            'category' => ! empty($metadata['category']) ? $metadata['category'] : 'TIK Related',
            'keywords' => array_unique(array_merge($canonical['keywords'], $metadata['keywords'] ?? [])),
            'importance' => ! empty($metadata['importance']) ? $metadata['importance'] : $canonical['importance'],
            'tik_related' => 1, // All TIK focused are TIK related
            'extraction_method' => 'normalized_tik_focused',
        ]);
    }

    private function normalizeUncategorized(array $metadata, array $canonical): array
    {
        // Preserve existing good agency data
        $existingAgency = $metadata['agency'] ?? '';
        $agency = $canonical['agency']; // This already uses preserveOrInferAgency logic

        return array_merge($canonical, [
            'agency' => $agency,
            'summary' => $metadata['title'] ?? $metadata['summary'] ?? 'Legacy document - summary needs update',
            'category' => $canonical['category'], // This already uses preserveOrInferCategory logic
            'keywords' => $canonical['keywords'], // Use column-based keywords
            'entry_date' => $metadata['extracted_at'] ?? $metadata['entry_date'] ?? $canonical['entry_date'],
            'importance' => $canonical['importance'], // This already uses preserveOrInferImportance logic
            'tik_related' => $metadata['tik_related'] ?? $this->detectTikRelated($metadata),
            'extraction_method' => 'normalized_uncategorized',
        ]);
    }

    /**
     * Preserve good existing agency or infer from document type if missing/bad
     */
    private function preserveOrInferAgency(LegalDocument $document, array $metadata): string
    {
        $existingAgency = $metadata['agency'] ?? '';

        // Preserve good existing agency data
        if (! empty($existingAgency) &&
            ! in_array($existingAgency, ['Unknown', 'Unknown Agency', 'TIK Agency', 'Kementerian (Unspecified)']) &&
            ! strpos($existingAgency, 'specific_tik_url') !== false) {
            return $existingAgency;
        }

        // Only infer if agency is missing or clearly wrong
        return $this->inferAgencyFromDocumentType($document);
    }

    /**
     * Preserve good existing category or infer from document type if missing/bad
     */
    private function preserveOrInferCategory(LegalDocument $document, array $metadata): string
    {
        $existingCategory = $metadata['category'] ?? '';

        // Preserve good existing category data
        if (! empty($existingCategory) &&
            ! in_array($existingCategory, ['Unknown', 'Legacy', 'Legal Document'])) {
            return $existingCategory;
        }

        // Only infer if category is missing or clearly wrong
        return $this->inferCategoryFromDocumentType($document);
    }

    /**
     * Preserve good existing importance or infer from document type if missing
     */
    private function preserveOrInferImportance(LegalDocument $document, array $metadata): string
    {
        $existingImportance = $metadata['importance'] ?? '';

        // Preserve existing importance unless it's clearly wrong
        if (! empty($existingImportance) &&
            in_array($existingImportance, ['critical', 'high', 'medium', 'low'])) {
            return $existingImportance;
        }

        // Only infer if importance is missing
        return $this->inferImportanceFromDocumentType($document);
    }

    private function inferAgencyFromDocumentType(LegalDocument $document): string
    {
        $documentType = $document->document_type;

        $agencyMapping = [
            'Undang-undang' => 'DPR RI',
            'Peraturan Pemerintah' => 'Pemerintah RI',
            'Peraturan Presiden' => 'Presiden RI',
            'Peraturan Menteri' => $this->inferMinistryFromUrl($document->source_url ?? ''),
        ];

        return $agencyMapping[$documentType] ?? $this->inferFromMetadata($document);
    }

    /**
     * Infer specific ministry from URL patterns
     */
    private function inferMinistryFromUrl(string $url): string
    {
        $ministryMapping = [
            'permendagri' => 'Kementerian Dalam Negeri',
            'permenkumham' => 'Kementerian Hukum dan HAM',
            'permendikbud' => 'Kementerian Pendidikan dan Kebudayaan',
            'permendikbudriset' => 'Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi',
            'permenkes' => 'Kementerian Kesehatan',
            'permenkeu' => 'Kementerian Keuangan',
            'permentan' => 'Kementerian Pertanian',
            'permenhub' => 'Kementerian Perhubungan',
            'permenpan' => 'Kementerian PAN-RB',
            'permenlu' => 'Kementerian Luar Negeri',
            'permendesa' => 'Kementerian Desa, PDT dan Transmigrasi',
            'permenpera' => 'Kementerian PUPR',
            'permensos' => 'Kementerian Sosial',
            'permenlhk' => 'Kementerian Lingkungan Hidup dan Kehutanan',
            'permenristekdikti' => 'Kementerian Riset, Teknologi, dan Pendidikan Tinggi',
            'permenedag' => 'Kementerian Perdagangan',
            'permenperin' => 'Kementerian Perindustrian',
            'permenkominfo' => 'Kementerian Komunikasi dan Informatika',
            'permenkomdigi' => 'Kementerian Komunikasi dan Informatika',
            'permenkopukm' => 'Kementerian Koperasi dan UKM',
            'permenpopar' => 'Kementerian Pariwisata',
            'permenjamsos' => 'Kementerian Ketenagakerjaan',
            'permenaker' => 'Kementerian Ketenagakerjaan',
            'permentrans' => 'Kementerian Perhubungan',
        ];

        $urlLower = strtolower($url);
        foreach ($ministryMapping as $pattern => $ministry) {
            if (strpos($urlLower, $pattern) !== false) {
                return $ministry;
            }
        }

        return 'Kementerian (Unspecified)';
    }

    private function inferCategoryFromDocumentType(LegalDocument $document): string
    {
        $categoryMapping = [
            'Undang-undang' => 'Primary Law',
            'Peraturan Pemerintah' => 'Government Regulation',
            'Peraturan Presiden' => 'Presidential Regulation',
            'Peraturan Menteri' => 'Ministerial Regulation',
        ];

        return $categoryMapping[$document->document_type] ?? 'Unknown Regulation';
    }

    private function inferImportanceFromDocumentType(LegalDocument $document): string
    {
        $importanceMapping = [
            'Undang-undang' => 'critical',
            'Peraturan Pemerintah' => 'high',
            'Peraturan Presiden' => 'high',
            'Peraturan Menteri' => 'medium',
        ];

        return $importanceMapping[$document->document_type] ?? 'medium';
    }

    /**
     * Get keywords from tik_keywords column instead of metadata
     */
    private function getKeywordsFromTikKeywordsColumn(LegalDocument $document): array
    {
        $tikKeywords = $document->tik_keywords ?? [];

        // Handle different possible formats
        if (is_string($tikKeywords)) {
            // If it's a JSON string, decode it
            $decoded = json_decode($tikKeywords, true);
            if (is_array($decoded)) {
                $tikKeywords = $decoded;
            } else {
                // If it's a comma-separated string
                $tikKeywords = array_map('trim', explode(',', $tikKeywords));
            }
        }

        // If tik_keywords contains objects with 'term' field, extract terms
        $keywords = [];
        foreach ($tikKeywords as $keyword) {
            if (is_array($keyword) && isset($keyword['term'])) {
                $keywords[] = $keyword['term'];
            } elseif (is_string($keyword)) {
                $keywords[] = $keyword;
            }
        }

        return array_filter(array_unique($keywords));
    }

    private function inferFromMetadata(LegalDocument $document): string
    {
        $metadata = $document->metadata ?? [];

        return $metadata['agency'] ?? 'Unknown Agency';
    }

    private function mapSourceToAgency(string $source): string
    {
        $mapping = [
            'peraturan.go.id' => 'Sekretariat Kabinet RI',
            'bpkp.go.id' => 'BPKP',
            'kemlu.go.id' => 'Kementerian Luar Negeri',
        ];

        return $mapping[$source] ?? ucfirst(str_replace('.go.id', '', $source));
    }

    private function mapLegacyCategory(string $category): string
    {
        $mapping = [
            'uu' => 'Primary Law',
            'pp' => 'Government Regulation',
            'perpres' => 'Presidential Regulation',
            'permen' => 'Ministerial Regulation',
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
        // Remove tik_summary from comparison since we're intentionally flattening it
        $originalFiltered = $original;
        unset($originalFiltered['tik_summary']);

        ksort($originalFiltered);
        ksort($normalized);

        return $originalFiltered !== $normalized;
    }

    private function displayChanges(array $original, array $normalized): void
    {
        $this->line('   CHANGES:');

        // Show flattened tik_summary
        if (isset($original['tik_summary']) && ! isset($normalized['tik_summary'])) {
            $this->line('   ✓ Flattened tik_summary into canonical fields');
        }

        // Show key field changes
        $keyFields = ['agency', 'keywords', 'category', 'importance'];
        foreach ($keyFields as $field) {
            $oldValue = $original[$field] ?? 'NULL';
            $newValue = $normalized[$field] ?? 'NULL';

            if ($oldValue !== $newValue) {
                $oldDisplay = is_array($oldValue) ? '['.count($oldValue).' items]' : $oldValue;
                $newDisplay = is_array($newValue) ? '['.count($newValue).' items]' : $newValue;
                $this->line("   {$field}: {$oldDisplay} → {$newDisplay}");
            }
        }
    }

    private function displayStats(array $stats, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 METADATA NORMALIZATION SUMMARY:');
        $this->table(
            ['Source Type', 'Count'],
            [
                ['Seeded', $stats['seeded']],
                ['Quick Populated', $stats['quick_populated']],
                ['TIK Focused', $stats['tik_focused']],
                ['Scraped (with tik_summary)', $stats['scraped']],
                ['Uncategorized', $stats['uncategorized']],
                ['🔄 Normalized', $isDryRun ? 'DRY RUN' : $stats['normalized']],
            ]
        );

        $this->newLine();
        $this->info('📈 SPECIFIC IMPROVEMENTS:');
        $this->table(
            ['Improvement', 'Count'],
            [
                ['TIK Summary Flattened', $stats['flattened_tik_summary']],
                ['Agency Inferred from Type', $stats['agency_inferred']],
                ['Keywords Sourced from Column', $stats['keywords_sourced']],
            ]
        );

        if ($isDryRun) {
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $this->info("✅ Normalized {$stats['normalized']} documents with flattened metadata structure.");
        }
    }
}
