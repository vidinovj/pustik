<?php

// app/Console/Commands/UpdateTikScores.php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Services\TikTermsService;
use Illuminate\Console\Command;

class UpdateTikScores extends Command
{
    protected $signature = 'documents:update-tik-scores 
                           {--dry-run : Show what would be changed without saving}
                           {--force : Update even documents that already have scores}';

    protected $description = 'Update TIK relevance scores using enhanced Indonesian digital government terms';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }

        $this->info('🚀 UPDATING TIK SCORES WITH ENHANCED TERMS');
        $this->info('Enhanced terms include: SPBE, digitalisasi, transformasi digital, and more...');
        $this->newLine();

        // Get documents to update
        $query = LegalDocument::query();

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('tik_relevance_score')
                    ->orWhere('tik_relevance_score', 0)
                    ->orWhereRaw("JSON_EXTRACT(metadata, '$.tik_related') IS NULL");
            });
        }

        $documents = $query->get();

        $this->info("📋 Processing {$documents->count()} documents...");
        $this->newLine();

        $stats = [
            'processed' => 0,
            'updated' => 0,
            'highly_tik_related' => 0,
            'new_tik_documents' => 0,
            'score_improvements' => 0,
        ];

        $progressBar = $this->output->createProgressBar($documents->count());

        foreach ($documents as $document) {
            $stats['processed']++;

            // Get current scores
            $oldScore = $document->tik_relevance_score ?? 0;
            $oldTikRelated = $document->metadata['tik_related'] ?? false;

            // Calculate new TIK summary
            $tikSummary = TikTermsService::generateTikSummary(
                $document->title ?? '',
                $document->full_text ?? '',
                $document->metadata['agency'] ?? ''
            );

            $newScore = $tikSummary['tik_score'];
            $newTikRelated = $tikSummary['is_highly_tik_related'];

            // Check if this is a significant change
            $hasChanges = ($newScore !== $oldScore) || ($newTikRelated !== $oldTikRelated);

            if ($hasChanges) {
                $stats['updated']++;

                if ($newScore > $oldScore) {
                    $stats['score_improvements']++;
                }

                if (! $oldTikRelated && $newTikRelated) {
                    $stats['new_tik_documents']++;
                }

                if ($newScore >= 20) {
                    $stats['highly_tik_related']++;
                }

                if ($isDryRun) {
                    // Show detailed changes in dry run
                    if ($stats['updated'] <= 10) { // Show first 10 in detail
                        $this->newLine();
                        $this->line('📄 '.substr($document->title, 0, 60).'...');
                        $this->line("   Score: {$oldScore} → {$newScore} (+".($newScore - $oldScore).')');
                        $this->line('   TIK Related: '.($oldTikRelated ? 'Yes' : 'No').' → '.($newTikRelated ? 'Yes' : 'No'));
                        $this->line("   Level: {$tikSummary['relevance_level']}");
                        $this->line('   Top keywords: '.implode(', ', array_slice(array_column($tikSummary['found_keywords'], 'term'), 0, 3)));
                        $this->line("   Category: {$tikSummary['primary_category']}");
                    }
                } else {
                    // Update the document
                    $document->tik_relevance_score = $newScore;
                    $document->tik_keywords = array_column($tikSummary['found_keywords'], 'term'); // Update top-level tik_keywords

                    // Update metadata
                    $metadata = $document->metadata ?? [];
                    $metadata['tik_related'] = $newTikRelated ? 1 : 0;
                    $metadata['tik_summary'] = $tikSummary;
                    $metadata['tik_updated_at'] = now()->toISOString();

                    $document->metadata = $metadata;
                    $document->save();
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displayResults($stats, $isDryRun);
        $this->showTopTikDocuments($isDryRun);
    }

    private function displayResults(array $stats, bool $isDryRun): void
    {
        $this->info('📊 TIK SCORING UPDATE RESULTS:');

        $tableData = [
            ['Documents Processed', $stats['processed']],
            ['Documents Updated', $stats['updated']],
            ['Score Improvements', $stats['score_improvements']],
            ['New TIK Documents', $stats['new_tik_documents']],
            ['Highly TIK-Related (20+)', $stats['highly_tik_related']],
        ];

        $this->table(['Metric', 'Count'], $tableData);

        if ($isDryRun) {
            $this->newLine();
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply changes.');
            $this->info('💡 Use --force to update documents that already have scores.');
        } else {
            $this->newLine();
            $this->info('✅ TIK scores updated successfully!');
            $this->line('🎯 Enhanced detection includes: SPBE, digitalisasi, transformasi digital');
        }
    }

    private function showTopTikDocuments(bool $isDryRun): void
    {
        $this->newLine();
        $this->info('🏆 TOP TIK-RELATED DOCUMENTS:');

        $topDocs = LegalDocument::where('tik_relevance_score', '>', 15)
            ->orderByDesc('tik_relevance_score')
            ->limit(10)
            ->get(['title', 'tik_relevance_score', 'metadata']);

        if ($topDocs->isEmpty()) {
            $this->line('No highly TIK-related documents found.');

            return;
        }

        $tableData = $topDocs->map(function ($doc, $index) {
            $agency = $doc->metadata['agency'] ?? 'Unknown';
            $tikSummary = $doc->metadata['tik_summary'] ?? [];
            $category = $tikSummary['primary_category'] ?? 'unknown';

            return [
                $index + 1,
                substr($doc->title, 0, 50).'...',
                $doc->tik_relevance_score,
                substr($agency, 0, 20),
                ucfirst(str_replace('_', ' ', $category)),
            ];
        })->toArray();

        $this->table(
            ['#', 'Title', 'Score', 'Agency', 'Category'],
            $tableData
        );
    }
}
