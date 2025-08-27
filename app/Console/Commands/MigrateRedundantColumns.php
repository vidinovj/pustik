<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateRedundantColumns extends Command
{
    protected $signature = 'documents:migrate-redundant-columns 
                           {--dry-run : Show what would be changed without saving}
                           {--batch-size=100 : Process documents in batches}';

    protected $description = 'Migrate redundant columns (issue_date, document_category) to optimized schema';

    // Document type mapping
    private array $typeCodeMapping = [
        'Undang-undang' => 'uu',
        'Undang-Undang' => 'uu', 
        'UU' => 'uu',
        'uu' => 'uu',
        
        'Peraturan Pemerintah' => 'pp',
        'PP' => 'pp',
        'pp' => 'pp',
        
        'Peraturan Presiden' => 'perpres',
        'Perpres' => 'perpres',
        'perpres' => 'perpres',
        
        'Peraturan Menteri' => 'permen',
        'Permen' => 'permen',
        'permen' => 'permen',
        
        'Keputusan Presiden' => 'keppres',
        'Keppres' => 'keppres',
        'keppres' => 'keppres',
        
        'Peraturan Daerah' => 'perda',
        'Perda' => 'perda',
        'perda' => 'perda',
    ];

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }

        $this->info('📋 REDUNDANT COLUMN MIGRATION');
        $this->info('Cleaning up issue_date and document_category redundancy');
        $this->newLine();

        $totalDocuments = LegalDocument::count();
        $this->info("Processing {$totalDocuments} documents in batches of {$batchSize}");

        $stats = [
            'processed' => 0,
            'issue_year_extracted' => 0,
            'document_type_code_mapped' => 0,
            'document_number_cleaned' => 0,
            'skipped' => 0,
            'errors' => 0
        ];

        // Process in batches to avoid memory issues
        LegalDocument::chunk($batchSize, function ($documents) use (&$stats, $isDryRun) {
            foreach ($documents as $document) {
                try {
                    $this->processDocument($document, $stats, $isDryRun);
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $this->error("Error processing document {$document->id}: {$e->getMessage()}");
                }
            }
        });

        $this->displayMigrationStats($stats, $isDryRun);
        
        if (!$isDryRun && $stats['processed'] > 0) {
            $this->info('🎯 NEXT STEPS:');
            $this->line('1. Verify data integrity: php artisan documents:verify-migration');
            $this->line('2. Update code to use new columns');
            $this->line('3. Drop old columns: php artisan documents:drop-redundant-columns');
        }

        return Command::SUCCESS;
    }

    private function processDocument(LegalDocument $document, array &$stats, bool $isDryRun): void
    {
        $stats['processed']++;
        $changes = [];
        $hasChanges = false;

        // 1. Extract issue_year from issue_date or document_number
        if (empty($document->issue_year)) {
            $extractedYear = $this->extractIssueYear($document);
            if ($extractedYear) {
                $changes['issue_year'] = ['from' => null, 'to' => $extractedYear];
                $stats['issue_year_extracted']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->issue_year = $extractedYear;
                }
            }
        }

        // 2. Map document_type to standardized code
        if (empty($document->document_type_code) && !empty($document->document_type)) {
            $typeCode = $this->mapDocumentTypeToCode($document->document_type);
            if ($typeCode) {
                $changes['document_type_code'] = ['from' => null, 'to' => $typeCode];
                $stats['document_type_code_mapped']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->document_type_code = $typeCode;
                }
            }
        }

        // 3. Clean document_number (remove year if present)
        $cleanedNumber = $this->cleanDocumentNumber($document->document_number);
        if ($cleanedNumber !== $document->document_number) {
            $changes['document_number'] = ['from' => $document->document_number, 'to' => $cleanedNumber];
            $stats['document_number_cleaned']++;
            $hasChanges = true;
            
            if (!$isDryRun) {
                $document->document_number = $cleanedNumber;
            }
        }

        // Save changes
        if ($hasChanges) {
            $this->displayDocumentChanges($document, $changes, $isDryRun);
            
            if (!$isDryRun) {
                $document->save();
            }
        } else {
            $stats['skipped']++;
        }
    }

    private function extractIssueYear(LegalDocument $document): ?int
    {
        // First try to extract from issue_date
        if ($document->issue_date) {
            return $document->issue_date->year;
        }

        // Then try to extract from document_number
        if ($document->document_number) {
            // Pattern: "11/2008", "27/2022", etc.
            if (preg_match('/(\d{4})/', $document->document_number, $matches)) {
                $year = (int) $matches[1];
                // Sanity check - reasonable year range
                if ($year >= 1945 && $year <= date('Y') + 1) {
                    return $year;
                }
            }
        }

        // Try to extract from title
        if ($document->title) {
            if (preg_match('/tahun\s+(\d{4})/i', $document->title, $matches)) {
                $year = (int) $matches[1];
                if ($year >= 1945 && $year <= date('Y') + 1) {
                    return $year;
                }
            }
        }

        return null;
    }

    private function mapDocumentTypeToCode(string $documentType): ?string
    {
        return $this->typeCodeMapping[$documentType] ?? null;
    }

    private function cleanDocumentNumber(string $documentNumber): string
    {
        if (empty($documentNumber)) {
            return $documentNumber;
        }

        // Remove year patterns: "11/2008" -> "11"
        $cleaned = preg_replace('/\/\d{4}$/', '', $documentNumber);
        
        // Remove "tahun YYYY" patterns: "11 tahun 2008" -> "11"
        $cleaned = preg_replace('/\s+tahun\s+\d{4}$/i', '', $cleaned);
        
        return trim($cleaned);
    }

    private function displayDocumentChanges(LegalDocument $document, array $changes, bool $isDryRun): void
    {
        $prefix = $isDryRun ? '👁️ ' : '✏️ ';
        $this->info("{$prefix}Document: " . substr($document->title, 0, 60) . "...");
        
        foreach ($changes as $field => $change) {
            $from = $change['from'] ?? 'NULL';
            $to = $change['to'];
            $this->line("   {$field}: {$from} → {$to}");
        }
        
        $this->newLine();
    }

    private function displayMigrationStats(array $stats, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 MIGRATION SUMMARY:');
        
        $tableData = [
            ['Documents Processed', $stats['processed']],
            ['Issue Years Extracted', $stats['issue_year_extracted']],
            ['Document Type Codes Mapped', $stats['document_type_code_mapped']],
            ['Document Numbers Cleaned', $stats['document_number_cleaned']],
            ['Documents Skipped', $stats['skipped']],
            ['Errors Encountered', $stats['errors']],
            ['📝 Total Updated', $isDryRun ? 'DRY RUN' : ($stats['processed'] - $stats['skipped'] - $stats['errors'])]
        ];
        
        $this->table(['Migration Type', 'Count'], $tableData);

        if ($isDryRun) {
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $this->info("✅ Migration completed successfully!");
        }
    }
}

