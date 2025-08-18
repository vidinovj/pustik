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
            $normalizedNumber = $this->extractNormalizedDocumentNumber($document, $isForce);

            $shouldUpdate = $isForce || ($originalNumber !== $normalizedNumber);

            if ($shouldUpdate) {
                $this->info("📝 Document: {$document->title}");
                $this->line("   document_number: \"{$originalNumber}\" → \"{$normalizedNumber}\"");
                
                if (!$isDryRun) {
                    $document->document_number = $normalizedNumber;
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
     * Extract and normalize the document number from title or existing number.
     */
    private function extractNormalizedDocumentNumber(LegalDocument $document, bool $force = false): string
    {
        // When force is enabled, always use source_url to re-extract
        if ($force) {
            $textToParse = $document->source_url;
        } else {
            $textToParse = $document->document_number;
            if (empty($textToParse) || Str::length($textToParse) > 50) { // If number is empty or looks like a title
                $textToParse = $document->source_url;
            }
        }

        $textToParseLower = Str::lower($textToParse);

        // Pattern 1: For slugs like /...-no-5-tahun-2020
        if (preg_match('/-no-(\d+)-tahun-(\d{4})/i', $textToParseLower, $matches)) {
            return "{$matches[1]}/{$matches[2]}";
        }

        // Pattern 2: "Nomor X Tahun Y" or "No. X Tahun Y"
        if (preg_match('/(?:nomor|no[\.\s-]*)\s*(\d+)\s*(?:tahun|th\.)\s*(\d{4})/i', $textToParseLower, $matches)) {
            return "{$matches[1]}/{$matches[2]}";
        }

        // Pattern 3: "X/YYYY" (already in desired format) - skip in force mode to allow re-extraction
        if (!$force && preg_match('/^(\d+)\/(\d{4})$/', $textToParse, $matches)) {
            return $textToParse;
        }

        // Pattern 4: Just a number (e.g., "123") - append current year if no year found
        if (preg_match('/^(\d+)$/', $textToParse, $matches)) {
            return "{$matches[1]}/" . date('Y');
        }
        
        // Fallback: Try to extract any number/year combination
        if (preg_match('/(\d+)[^\d]*(\d{4})/', $textToParseLower, $matches)) {
            // Avoid matching parts of the ID in the URL
            if (str_contains($document->source_url, $matches[0])) {
                 if(!str_contains(strtolower($document->source_url), "tahun")){
                    return $document->document_number ?? 'UNKNOWN';
                 }
            }
            return "{$matches[1]}/{$matches[2]}";
        }

        // If all else fails, return original or a placeholder
        return $document->document_number ?? 'UNKNOWN';
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