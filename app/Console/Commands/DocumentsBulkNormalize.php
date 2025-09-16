<?php

// app/Console/Commands/DocumentsBulkNormalize.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class DocumentsBulkNormalize extends Command
{
    protected $signature = 'documents:bulk-normalize {--dry-run : Show what would be changed without saving} {--force : Force update for TIK scores}';

    protected $description = 'Run all document normalization commands in sequence.';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🚀 Starting Bulk Document Normalization...');
        $this->newLine();

        $options = [];
        if ($isDryRun) {
            $options['--dry-run'] = true;
        }

        // 1. Normalize from URL (NEW)
        $this->info('🔄 Running documents:normalize-from-url...');
        Artisan::call('documents:normalize-from-url', $options, $this->output);
        $this->newLine();

        // 2. Normalize Metadata
        $this->info('🔄 Running documents:normalize-metadata...');
        Artisan::call('documents:normalize-metadata', $options, $this->output);
        $this->newLine();

        // 3. Normalize Columns
        $this->info('🔄 Running documents:normalize-columns...');
        Artisan::call('documents:normalize-columns', $options, $this->output);
        $this->newLine();

        // 4. Normalize Document Numbers
        $this->info('🔄 Running documents:normalize-document-numbers...');
        Artisan::call('documents:normalize-document-numbers', $options, $this->output);
        $this->newLine();

        // 5. Update TIK Scores
        $this->info('🔄 Running documents:update-tik-scores...');
        $tikScoreOptions = $options;
        if ($force) {
            $tikScoreOptions['--force'] = true;
        }
        Artisan::call('documents:update-tik-scores', $tikScoreOptions, $this->output);
        $this->newLine();

        // 6. Fix TIK Column Sync (NEW)
        $this->info('🔄 Running documents:fix-tik-column-sync...');
        Artisan::call('documents:fix-tik-column-sync', $options, $this->output);
        $this->newLine();

        // 7. Classify Documents
        $this->info('🔄 Running documents:classify...');
        $classifyOptions = $options;
        if ($force) {
            $classifyOptions['--force'] = true;
        }
        Artisan::call('documents:classify', $classifyOptions, $this->output);
        $this->newLine();

        $this->info('✅ Bulk Document Normalization Completed!');

        return Command::SUCCESS;
    }
}
