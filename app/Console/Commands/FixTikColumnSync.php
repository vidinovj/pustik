<?php
// app/Console/Commands/FixTikColumnSync.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;

class FixTikColumnSync extends Command
{
    protected $signature = 'documents:fix-tik-column-sync {--dry-run : Show what would be changed}';
    protected $description = 'Sync TIK data from metadata to top-level database columns';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN - No changes will be saved');
        }

        $this->info('🔄 Syncing TIK data from metadata to database columns...');
        $this->newLine();

        $documents = LegalDocument::whereNotNull('metadata')->get();
        
        $stats = [
            'processed' => 0,
            'tik_status_fixed' => 0,
            'category_fixed' => 0,
            'score_synced' => 0,
            'keywords_synced' => 0
        ];

        foreach ($documents as $document) {
            $stats['processed']++;
            $metadata = $document->metadata ?? [];
            $tikSummary = $metadata['tik_summary'] ?? [];
            
            $hasChanges = false;
            $changes = [];

            // 1. Fix is_tik_related based on metadata
            $metadataTikRelated = $metadata['tik_related'] ?? false;
            $isHighlyTikRelated = $tikSummary['is_highly_tik_related'] ?? false;
            $shouldBeTikRelated = $metadataTikRelated || $isHighlyTikRelated || ($document->tik_relevance_score >= 10);
            
            if ($document->is_tik_related != $shouldBeTikRelated) {
                $changes['is_tik_related'] = [
                    'from' => $document->is_tik_related ? 'true' : 'false',
                    'to' => $shouldBeTikRelated ? 'true' : 'false'
                ];
                $stats['tik_status_fixed']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->is_tik_related = $shouldBeTikRelated;
                }
            }

            // 2. Fix document_category from metadata
            $metadataCategory = $tikSummary['primary_category'] ?? null;
            if ($metadataCategory && $document->document_category !== $metadataCategory) {
                $changes['document_category'] = [
                    'from' => $document->document_category ?? 'null',
                    'to' => $metadataCategory
                ];
                $stats['category_fixed']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->document_category = $metadataCategory;
                }
            }

            // 3. Sync TIK score from metadata if different
            $metadataTikScore = $tikSummary['tik_score'] ?? 0;
            if ($metadataTikScore > 0 && $document->tik_relevance_score !== $metadataTikScore) {
                $changes['tik_relevance_score'] = [
                    'from' => $document->tik_relevance_score,
                    'to' => $metadataTikScore
                ];
                $stats['score_synced']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->tik_relevance_score = $metadataTikScore;
                }
            }

            // 4. Sync TIK keywords from metadata
            $metadataKeywords = array_column($tikSummary['found_keywords'] ?? [], 'term');
            $currentKeywords = is_string($document->tik_keywords) 
                ? json_decode($document->tik_keywords, true) ?? []
                : ($document->tik_keywords ?? []);
                
            if (!empty($metadataKeywords) && $metadataKeywords !== $currentKeywords) {
                $changes['tik_keywords'] = [
                    'from' => implode(', ', $currentKeywords),
                    'to' => implode(', ', $metadataKeywords)
                ];
                $stats['keywords_synced']++;
                $hasChanges = true;
                
                if (!$isDryRun) {
                    $document->tik_keywords = $metadataKeywords;
                }
            }

            // Display and save changes
            if ($hasChanges) {
                $this->displayDocumentChanges($document, $changes, $isDryRun);
                
                if (!$isDryRun) {
                    $document->save();
                }
            }
        }

        $this->displayStats($stats, $isDryRun);
        return 0;
    }

    private function displayDocumentChanges($document, array $changes, bool $isDryRun): void
    {
        $prefix = $isDryRun ? '👁️ ' : '✏️ ';
        $this->info("{$prefix}Document: {$document->title}");
        
        foreach ($changes as $field => $change) {
            $from = $change['from'];
            $to = $change['to'];
            $this->line("   {$field}: {$from} → {$to}");
        }
        
        $this->newLine();
    }

    private function displayStats(array $stats, bool $isDryRun): void
    {
        $this->newLine();
        $this->info('📊 TIK COLUMN SYNC RESULTS:');
        
        $tableData = [
            ['Documents Processed', $stats['processed']],
            ['TIK Status Fixed', $stats['tik_status_fixed']],
            ['Categories Fixed', $stats['category_fixed']],
            ['Scores Synced', $stats['score_synced']],
            ['Keywords Synced', $stats['keywords_synced']]
        ];
        
        $this->table(['Change Type', 'Count'], $tableData);

        if ($isDryRun) {
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply changes.');
        } else {
            $this->info('✅ TIK column sync completed!');
        }
    }
}