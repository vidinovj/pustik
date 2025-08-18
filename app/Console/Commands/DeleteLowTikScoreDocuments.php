<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;

class DeleteLowTikScoreDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:delete-low-tik-score {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete documents with a TIK relevance score of 0 or null';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN - No documents will be deleted.');
        }

        $this->info('🔥 Deleting documents with low TIK scores...');
        $this->newLine();

        $documentsToDelete = LegalDocument::where('tik_relevance_score', 0)
            ->orWhereNull('tik_relevance_score')
            ->get();

        $count = $documentsToDelete->count();

        if ($count === 0) {
            $this->info('✅ No documents with low TIK scores found.');
            return 0;
        }

        $this->info("Found {$count} documents to delete.");

        foreach ($documentsToDelete as $document) {
            $this->line("  - Deleting: {$document->title} (Score: {$document->tik_relevance_score})");
            if (!$isDryRun) {
                $document->delete();
            }
        }

        $this->newLine();
        if ($isDryRun) {
            $this->warn("⚠️  This was a dry run. Use without --dry-run to delete {$count} documents.");
        } else {
            $this->info("✅ Successfully deleted {$count} documents.");
        }

        return 0;
    }
}
