<?php
// app/Console/Commands/DocumentsNormalizeDocumentNumbers.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use Illuminate\Support\Str;

class DocumentsNormalizeDocumentNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:normalize-document-numbers {--dry-run : Show what would be changed without saving} {--force : Re-normalize all documents regardless of current format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Normalize legal document numbers to a consistent format (e.g., "X/YYYY")';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }
        
        if ($isForce) {
            $this->info('💪 FORCE MODE - Re-normalizing all documents');
        }

        $documents = LegalDocument::all();
        $this->info("📋 Processing {$documents->count()} documents for number normalization...");
        
        $stats = [
            'processed' => 0,
            'normalized' => 0,
            'skipped' => 0
        ];

        foreach ($documents as $document) {
            $stats['processed']++;
            $originalNumber = $document->document_number;
            $normalizedData = $this->extractNormalizedDocumentData($document, $isForce);
            $normalizedNumber = $normalizedData['number'];
            $normalizedYear = $normalizedData['year'];

            $shouldUpdate = $isForce || ($originalNumber !== $normalizedNumber) || ($document->issue_year !== $normalizedYear);

            if ($shouldUpdate) {
                $this->info("📝 Document: {$document->title}");
                $this->line("   document_number: \"{$originalNumber}\" → \"{$normalizedNumber}\"");
                $this->line("   issue_year: \"{$document->issue_year}\" → \"{$normalizedYear}\"");
                
                if (!$isDryRun) {
                    $document->document_number = $normalizedNumber;
                    $document->issue_year = $normalizedYear;
                    $document->save();
                }
                $stats['normalized']++;
            } else {
                $stats['skipped']++;
            }
        }

        $this->displayStats($stats, $isDryRun, $isForce);
    }

    /**
     * Extract and normalize the document number and year from title or existing number.
     */
    private function extractNormalizedDocumentData(LegalDocument $document, bool $force = false): array
    {
        // Combine title and source_url for a more robust search context.
        $textToParse = $document->title . ' ' . $document->source_url;

        // Pattern 1: Slug format (e.g., /...-no-5-tahun-2020)
        if (preg_match('/-no-(\\d+)-tahun-(\\d{4})/i', $textToParse, $matches)) {
            return ['number' => $matches[1], 'year' => $matches[2]];
        }

        // Pattern 2: Explicit "Nomor X Tahun Y" (very specific)
        if (preg_match('/nomor\s+(\\d+)\s+tahun\s+(\\d{4})/i', $textToParse, $matches)) {
            return ['number' => $matches[1], 'year' => $matches[2]];
        }

        // Pattern 3: Flexible "No. X Thn Y", "No X Tahun Y", etc.
        if (preg_match('/(?:no|nomor)[\.\s-]*(\\d+)[\s-]*(?:tahun|thn|th)[\.\s-]*(\\d{4})/i', $textToParse, $matches)) {
            return ['number' => $matches[1], 'year' => $matches[2]];
        }

        // Pattern 4: "PERATURAN ... NOMOR X TAHUN Y"
        if (preg_match('/peraturan(?:.*)nomor\s+(\\d+)\s+tahun\s+(\\d{4})/i', $textToParse, $matches)) {
            return ['number' => $matches[1], 'year' => $matches[2]];
        }

        // Fallback: Find a 4-digit year and the number preceding it
        if (preg_match('/(\\d+)[\/\.\s-]*tahun\s*(\\d{4})/i', $textToParse, $matches)) {
            return ['number' => $matches[1], 'year' => $matches[2]];
        }

        // Last resort: if a year is already present, try to find any number
        if ($document->issue_year) {
            if (preg_match('/(\\d+)/', $textToParse, $matches)) {
                return ['number' => $matches[1], 'year' => $document->issue_year];
            }
        }

        // If all else fails, return original data
        return ['number' => $document->document_number, 'year' => $document->issue_year];
    }

    /**
     * Display statistics.
     */
    private function displayStats(array $stats, bool $isDryRun, bool $isForce = false): void
    {
        $this->newLine();
        $this->info('📊 DOCUMENT NUMBER NORMALIZATION SUMMARY:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Documents Processed', $stats['processed']],
                ['Documents Normalized', $stats['normalized']],
                ['Documents Skipped (no change)', $stats['skipped']],
            ]
        );

        if ($isDryRun) {
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $message = "✅ Normalized {$stats['normalized']} document numbers.";
            if ($isForce) {
                $message .= ' (Force mode: all documents processed)';
            }
            $this->info($message);
        }
    }
}
