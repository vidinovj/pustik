<?php
// app/Console/Commands/NormalizeDocumentColumns.php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class NormalizeDocumentColumns extends Command
{
    protected $signature = 'documents:normalize-columns {--dry-run : Show what would be changed without saving}';
    protected $description = 'Normalize legal document column data and populate missing fields';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }

        $documents = LegalDocument::all();
        $this->info("📋 Processing {$documents->count()} documents for column normalization...");
        
        $stats = [
            'document_type_fixed' => 0,
            'status_added' => 0,
            'checksum_generated' => 0,
            'source_id_generated' => 0,
            'full_text_attempted' => 0,
            'total_updated' => 0
        ];

        foreach ($documents as $document) {
            $changes = [];
            $hasChanges = false;

            // 1. Standardize document_type
            if ($this->needsDocumentTypeNormalization($document)) {
                $oldType = $document->document_type;
                $newType = $this->normalizeDocumentType($document);
                $changes['document_type'] = ['from' => $oldType, 'to' => $newType];
                $stats['document_type_fixed']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->document_type = $newType;
                }
            }

            // 2. Add missing status
            if (empty($document->status)) {
                $changes['status'] = ['from' => null, 'to' => 'active'];
                $stats['status_added']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->status = 'active';
                }
            }

            // 3. Generate missing checksum
            if (empty($document->checksum)) {
                $checksum = $this->generateChecksum($document);
                $changes['checksum'] = ['from' => null, 'to' => substr($checksum, 0, 8) . '...'];
                $stats['checksum_generated']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->checksum = $checksum;
                }
            }

            // 4. Generate missing document_source_id
            if (empty($document->document_source_id)) {
                $sourceId = $this->generateDocumentSourceId($document);
                $changes['document_source_id'] = ['from' => null, 'to' => $sourceId];
                $stats['source_id_generated']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->document_source_id = $sourceId;
                }
            }

            // 5. Attempt to populate full_text
            if (empty($document->full_text) && !empty($document->source_url)) {
                $changes['full_text'] = ['from' => null, 'to' => 'extraction_attempted'];
                $stats['full_text_attempted']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->full_text = $this->extractFullText($document);
                }
            }

            // Display and save changes
            if ($hasChanges) {
                $this->displayDocumentChanges($document, $changes, $isDryRun);
                
                if (!$isDryRun) {
                    $document->save();
                    $stats['total_updated']++;
                }
            }
        }

        $this->displayStats($stats, $isDryRun);
    }

    private function needsDocumentTypeNormalization(LegalDocument $document): bool
    {
        $type = $document->document_type;
        
        // Check for abbreviations that need expansion
        return in_array($type, ['UU', 'PP', 'Perpres', 'Permen']) || 
               (empty($type) && $this->canInferDocumentType($document));
    }

    private function normalizeDocumentType(LegalDocument $document): string
    {
        $currentType = $document->document_type;
        
        // Standardize abbreviations
        $typeMapping = [
            'UU' => 'Undang-undang',
            'PP' => 'Peraturan Pemerintah', 
            'Perpres' => 'Peraturan Presiden',
            'Permen' => 'Peraturan Menteri',
            'uu' => 'Undang-undang'
        ];

        if (isset($typeMapping[$currentType])) {
            return $typeMapping[$currentType];
        }

        // If empty, infer from title or document_number
        if (empty($currentType)) {
            return $this->inferDocumentType($document);
        }

        return $currentType;
    }

    private function canInferDocumentType(LegalDocument $document): bool
    {
        $text = strtolower($document->title . ' ' . $document->document_number);
        $indicators = ['undang-undang', 'peraturan pemerintah', 'peraturan presiden', 'peraturan menteri'];
        
        foreach ($indicators as $indicator) {
            if (strpos($text, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function inferDocumentType(LegalDocument $document): string
    {
        $text = strtolower($document->title . ' ' . $document->document_number);
        
        if (strpos($text, 'undang-undang') !== false || strpos($text, 'uu no') !== false) {
            return 'Undang-undang';
        }
        
        if (strpos($text, 'peraturan pemerintah') !== false || strpos($text, 'pp no') !== false) {
            return 'Peraturan Pemerintah';
        }
        
        if (strpos($text, 'peraturan presiden') !== false || strpos($text, 'perpres') !== false) {
            return 'Peraturan Presiden';
        }
        
        if (strpos($text, 'peraturan menteri') !== false || strpos($text, 'permen') !== false) {
            return 'Peraturan Menteri';
        }
        
        // Default based on agency if available
        $agency = $document->metadata['agency'] ?? '';
        if (strpos(strtolower($agency), 'presiden') !== false) {
            return 'Peraturan Presiden';
        }
        
        return 'Unknown Document Type';
    }

    private function generateChecksum(LegalDocument $document): string
    {
        // Generate consistent checksum based on document content
        $content = implode('|', [
            $document->title,
            $document->document_number,
            $document->source_url,
            json_encode($document->metadata)
        ]);
        
        return hash('sha256', $content);
    }

    private function generateDocumentSourceId(LegalDocument $document): string
    {
        $extractionMethod = $document->metadata['extraction_method'] ?? 'unknown';
        $sourceName = 'Unknown Source'; // Default source name

        switch ($extractionMethod) {
            case 'manual_core_entry':
                $sourceName = 'Core TIK Regulations (Manual)';
                break;
            case 'tik_focused_scraper':
                $sourceName = 'TIK Focused Scraper';
                break;
            case 'enhanced_multi_strategy':
                // For enhanced_multi_strategy, try to get source from metadata->source
                $sourceName = $document->metadata['source'] ?? 'Enhanced Scraper';
                break;
            case 'normalized_seeded':
                $sourceName = 'Sample Seeder';
                break;
            default:
                // For other or unknown types, try to infer from metadata->agency or title
                $sourceName = $document->metadata['agency'] ?? $document->title ?? 'Unknown Source';
                // Truncate to avoid very long source names
                $sourceName = Str::limit($sourceName, 100, '');
                break;
        }

        // Find or create the DocumentSource
        $documentSource = \App\Models\DocumentSource::firstOrCreate(
            ['name' => Str::slug($sourceName)], // Use a slugged name for consistency
            ['display_name' => $sourceName, 'base_url' => 'N/A', 'status' => 'active']
        );

        return $documentSource->id; // Return the actual UUID
    }

    private function extractFullText(LegalDocument $document): string
    {
        // Attempt basic full text extraction
        $fullText = '';
        
        // Combine available text sources
        $sources = [
            $document->title,
            $document->document_number,
            $document->metadata['summary'] ?? '',
            implode(' ', $document->metadata['keywords'] ?? [])
        ];
        
        $fullText = implode(' | ', array_filter($sources));
        
        // If we have a source URL, indicate extraction is needed
        if (!empty($document->source_url) && empty($fullText)) {
            $fullText = '[Full text extraction needed from: ' . $document->source_url . ']';
        }
        
        return $fullText ?: '[No extractable text available]';
    }

    private function displayDocumentChanges(LegalDocument $document, array $changes, bool $isDryRun): void
    {
        $prefix = $isDryRun ? '👁️ ' : '✏️ ';
        $this->info("{$prefix}Document: {$document->title}");
        
        foreach ($changes as $field => $change) {
            $from = $change['from'] ?? 'NULL';
            $to = $change['to'];
            $this->line("   {$field}: {$from} → {$to}");
        }
        
        $this->newLine();
    }

    private function displayStats(array $stats, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 COLUMN NORMALIZATION SUMMARY:');
        
        $tableData = [
            ['Document Type Fixed', $stats['document_type_fixed']],
            ['Status Added', $stats['status_added']],
            ['Checksums Generated', $stats['checksum_generated']],
            ['Source IDs Generated', $stats['source_id_generated']],
            ['Full Text Attempted', $stats['full_text_attempted']],
            ['📝 Total Documents Updated', $isDryRun ? 'DRY RUN' : $stats['total_updated']]
        ];
        
        $this->table(['Change Type', 'Count'], $tableData);

        if ($isDryRun) {
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $this->info("✅ Column normalization completed for {$stats['total_updated']} documents.");
        }
    }
}