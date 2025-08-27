<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class DropRedundantColumns extends Command
{
    protected $signature = 'documents:drop-redundant-columns {--force : Skip confirmation}';
    protected $description = 'Drop the old redundant columns after migration is verified';

    public function handle()
    {
        if (!$this->option('force')) {
            $this->warn('⚠️  This will permanently delete the following columns:');
            $this->line('   • issue_date (replaced by issue_year)');
            $this->line('   • document_category (redundant with document_type_code)');
            $this->newLine();
            
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        $this->info('🗑️  Dropping redundant columns...');

        // First run verification
        $this->call('documents:verify-migration');
        
        if ($this->confirm('Migration verification passed. Continue with column removal?', true)) {
            Schema::table('legal_documents', function ($table) {
                if (Schema::hasColumn('legal_documents', 'issue_date')) {
                    if (Schema::hasIndex('legal_documents', ['issue_date'])) {
                        $table->dropIndex(['issue_date']);
                    }
                    $table->dropColumn(['issue_date']);
                }
                
                // Only drop document_category if it exists
                if (Schema::hasColumn('legal_documents', 'document_category')) {
                    if (Schema::hasIndex('legal_documents', ['document_category'])) {
                        $table->dropIndex(['document_category']);
                    }
                    $table->dropColumn(['document_category']);
                }
            });

            $this->info('✅ Redundant columns dropped successfully!');
            $this->info('🎯 Schema cleanup completed.');
        }

        return Command::SUCCESS;
    }
}