<?php

// app/Console/Commands/ClassifyDocuments.php - REWORKED VERSION

namespace App\Console\Commands;

use App\Models\LegalDocument;
use Illuminate\Console\Command;

class ClassifyDocuments extends Command
{
    protected $signature = 'documents:classify 
                           {--dry-run : Show classification results without saving}
                           {--force : Re-classify documents that already have type codes}
                           {--type= : Only classify specific document types}
                           {--tik-only : Only classify TIK-related documents}
                           {--limit= : Limit number of documents to process}
                           {--show-details : Show detailed classification reasoning}
                           {--show-types : Display available document types and exit}';

    protected $description = 'Classify and normalize document type codes from titles and URLs';

    // Document type patterns and their standardized codes
    private array $typePatterns = [
        'uu' => [
            'patterns' => [
                '/undang-undang/i',
                '/uu\s+no/i',
                '/uu-no-/i',
                'law',
                'act',
            ],
            'name' => 'Undang-undang',
            'hierarchy_level' => 1,
        ],
        'pp' => [
            'patterns' => [
                '/peraturan\s+pemerintah/i',
                '/pp\s+no/i',
                '/pp-no-/i',
                'government regulation',
            ],
            'name' => 'Peraturan Pemerintah',
            'hierarchy_level' => 2,
        ],
        'perpres' => [
            'patterns' => [
                '/peraturan\s+presiden/i',
                '/perpres\s+no/i',
                '/perpres-no-/i',
                'presidential regulation',
            ],
            'name' => 'Peraturan Presiden',
            'hierarchy_level' => 3,
        ],
        'permen' => [
            'patterns' => [
                '/peraturan\s+menteri/i',
                '/permen\w*\s+no/i',
                '/permen\w*-no-/i',
                'ministerial regulation',
            ],
            'name' => 'Peraturan Menteri',
            'hierarchy_level' => 4,
        ],
        'kepmen' => [
            'patterns' => [
                '/keputusan\s+menteri/i',
                '/kepmen\w*\s+no/i',
                '/kepmen\w*-no-/i',
                'ministerial decree',
            ],
            'name' => 'Keputusan Menteri',
            'hierarchy_level' => 4,
        ],
        'keppres' => [
            'patterns' => [
                '/keputusan\s+presiden/i',
                '/keppres\s+no/i',
                '/keppres-no-/i',
                'presidential decree',
            ],
            'name' => 'Keputusan Presiden',
            'hierarchy_level' => 3,
        ],
        'perda' => [
            'patterns' => [
                '/peraturan\s+daerah/i',
                '/perda\s+no/i',
                '/perda-no-/i',
                'regional regulation',
            ],
            'name' => 'Peraturan Daerah',
            'hierarchy_level' => 5,
        ],
        'inpres' => [
            'patterns' => [
                '/instruksi\s+presiden/i',
                '/inpres\s+no/i',
                '/inpres-no-/i',
                'presidential instruction',
            ],
            'name' => 'Instruksi Presiden',
            'hierarchy_level' => 3,
        ],
    ];

    public function handle()
    {
        // Handle show types option first
        if ($this->option('show-types')) {
            $this->showAvailableTypes();

            return;
        }

        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $showDetails = $this->option('show-details');
        $limit = $this->option('limit');
        $typeFilter = $this->option('type');
        $tikOnly = $this->option('tik-only');

        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes will be saved');
        }

        $this->info('DOCUMENT TYPE CODE CLASSIFIER');
        $this->info('Extracting and normalizing document type codes from titles and URLs');
        $this->newLine();

        // Build query
        $query = LegalDocument::query();

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('document_type_code')
                    ->orWhere('document_type_code', '')
                    ->orWhere('document_type_code', 'unknown');
            });
        }

        if ($typeFilter) {
            $types = explode(',', $typeFilter);
            $query->where(function ($q) use ($types) {
                foreach ($types as $type) {
                    if (isset($this->typePatterns[$type])) {
                        $typeInfo = $this->typePatterns[$type];
                        $q->orWhere('document_type', 'like', '%'.$typeInfo['name'].'%');
                    }
                }
            });
        }

        if ($tikOnly) {
            $query->where('tik_relevance_score', '>', 5);
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $documents = $query->get();

        if ($documents->isEmpty()) {
            $this->warn('No documents found to classify.');

            return;
        }

        $this->info("Processing {$documents->count()} documents...");
        $this->newLine();

        $stats = [
            'processed' => 0,
            'classified' => 0,
            'from_title' => 0,
            'from_url' => 0,
            'from_existing_type' => 0,
            'high_confidence' => 0,
            'type_changes' => 0,
            'unknown' => 0,
        ];

        $typeStats = [];
        $progressBar = $this->output->createProgressBar($documents->count());

        foreach ($documents as $document) {
            $stats['processed']++;

            // Get current type code
            $oldTypeCode = $document->document_type_code;

            // Classify the document type
            $classification = $this->classifyDocumentType($document);
            $newTypeCode = $classification['type_code'];
            $confidence = $classification['confidence'];
            $source = $classification['source'];

            // Track statistics
            if (! isset($typeStats[$newTypeCode])) {
                $typeStats[$newTypeCode] = 0;
            }
            $typeStats[$newTypeCode]++;

            $stats[$source]++;
            if ($confidence >= 0.8) {
                $stats['high_confidence']++;
            }

            $hasChanges = ($newTypeCode !== $oldTypeCode && $newTypeCode !== 'unknown');

            if ($hasChanges) {
                $stats['classified']++;
                if ($oldTypeCode && $oldTypeCode !== 'unknown') {
                    $stats['type_changes']++;
                }

                if ($showDetails && $stats['classified'] <= 5) {
                    $this->showClassificationDetails($document, $classification, $oldTypeCode);
                }

                if (! $isDryRun) {
                    $document->document_type_code = $newTypeCode;

                    // Update metadata with classification info
                    $metadata = $document->metadata ?? [];
                    $metadata['type_classification'] = [
                        'classified_type' => $newTypeCode,
                        'confidence' => $confidence,
                        'source' => $source,
                        'reasoning' => $classification['reasoning'],
                        'classified_at' => now()->toISOString(),
                    ];
                    $document->metadata = $metadata;

                    $document->save();
                }
            } elseif ($newTypeCode === 'unknown') {
                $stats['unknown']++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayResults($stats, $typeStats, $isDryRun);

        if (! $isDryRun && $stats['classified'] > 0) {
            $this->info("Classification completed! {$stats['classified']} documents classified.");
        }
    }

    private function classifyDocumentType(LegalDocument $document): array
    {
        $searchText = strtolower(trim($document->title.' '.$document->source_url));
        $bestMatch = [
            'type_code' => 'unknown',
            'confidence' => 0.0,
            'source' => 'unknown',
            'reasoning' => 'No patterns matched',
        ];

        // First check existing document_type field
        if (! empty($document->document_type)) {
            foreach ($this->typePatterns as $code => $config) {
                if (stripos($document->document_type, $config['name']) !== false) {
                    return [
                        'type_code' => $code,
                        'confidence' => 0.95,
                        'source' => 'from_existing_type',
                        'reasoning' => "Mapped from existing document_type: {$document->document_type}",
                    ];
                }
            }
        }

        // Check URL patterns first (often more reliable)
        foreach ($this->typePatterns as $code => $config) {
            foreach ($config['patterns'] as $pattern) {
                // Check if the pattern is a regex (starts and ends with /)
                if (is_string($pattern) && str_starts_with($pattern, '/') && str_ends_with($pattern, '/')) {
                    // It's a regex pattern
                    if (preg_match($pattern, $searchText)) {
                        $confidence = 0.85; // Slightly lower for regex matches
                        if ($confidence > $bestMatch['confidence']) {
                            $bestMatch = [
                                'type_code' => $code,
                                'confidence' => $confidence,
                                'source' => stripos($document->source_url, $pattern) !== false ? 'from_url' : 'from_title',
                                'reasoning' => "Matched regex pattern: {$pattern}",
                            ];
                        }
                    }
                } elseif (is_string($pattern)) {
                    // It's a simple string pattern
                    if (stripos($searchText, $pattern) !== false) {
                        $confidence = 0.9; // High confidence from direct string match
                        if ($confidence > $bestMatch['confidence']) {
                            $bestMatch = [
                                'type_code' => $code,
                                'confidence' => $confidence,
                                'source' => stripos($document->source_url, $pattern) !== false ? 'from_url' : 'from_title',
                                'reasoning' => "Matched keyword: {$pattern}",
                            ];
                        }
                    }
                }
            }
        }

        return $bestMatch;
    }

    private function showClassificationDetails(LegalDocument $document, array $classification, ?string $oldTypeCode): void
    {
        $this->newLine();
        $this->line('Document: '.substr($document->title, 0, 70).'...');
        $this->line('   Old Type Code: '.($oldTypeCode ?? 'None'));
        $this->line("   New Type Code: {$classification['type_code']}");
        $this->line('   Confidence: '.round($classification['confidence'] * 100).'%');
        $this->line("   Source: {$classification['source']}");
        $this->line("   Reasoning: {$classification['reasoning']}");
        $this->newLine();
    }

    private function displayResults(array $stats, array $typeStats, bool $isDryRun): void
    {
        $this->info('CLASSIFICATION RESULTS:');

        $tableData = [
            ['Documents Processed', $stats['processed']],
            ['Successfully Classified', $stats['classified']],
            ['From Title Analysis', $stats['from_title']],
            ['From URL Analysis', $stats['from_url']],
            ['From Existing Type', $stats['from_existing_type']],
            ['High Confidence (80%+)', $stats['high_confidence']],
            ['Type Code Changes', $stats['type_changes']],
            ['Remained Unknown', $stats['unknown']],
        ];

        $this->table(['Metric', 'Count'], $tableData);

        // Show type breakdown
        if (! empty($typeStats)) {
            $this->newLine();
            $this->info('TYPE CODE BREAKDOWN:');

            arsort($typeStats);

            $typeTableData = [];
            foreach ($typeStats as $code => $count) {
                $name = $this->typePatterns[$code]['name'] ?? ucfirst($code);
                $level = $this->typePatterns[$code]['hierarchy_level'] ?? 'N/A';
                $typeTableData[] = [$code, $name, $count, "Level {$level}"];
            }

            $this->table(['Code', 'Full Name', 'Count', 'Hierarchy'], $typeTableData);
        }

        if ($isDryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Use without --dry-run to apply classifications.');
            $this->info('Use --show-details to see classification reasoning for first 5 documents.');
        }
    }

    private function showAvailableTypes(): void
    {
        $this->info('AVAILABLE DOCUMENT TYPE CODES:');
        $this->newLine();

        $tableData = [];
        foreach ($this->typePatterns as $code => $config) {
            $patterns = array_slice($config['patterns'], 0, 2); // Show first 2 patterns
            $patternStr = implode(', ', $patterns);
            if (count($config['patterns']) > 2) {
                $patternStr .= '...';
            }

            $tableData[] = [
                $code,
                $config['name'],
                "Level {$config['hierarchy_level']}",
                $patternStr,
            ];
        }

        $this->table(['Code', 'Full Name', 'Hierarchy', 'Sample Patterns'], $tableData);

        $this->newLine();
        $this->info('USAGE EXAMPLES:');
        $this->line('  php artisan documents:classify --dry-run');
        $this->line('  php artisan documents:classify --type=uu,pp --tik-only');
        $this->line('  php artisan documents:classify --force --show-details');
    }
}
