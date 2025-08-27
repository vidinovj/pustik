<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CheckDatabaseSchema extends Command
{
    protected $signature = 'db:check-schema {table=legal_documents}';
    protected $description = 'Check current database schema for a table';

    public function handle()
    {
        $table = $this->argument('table');
        
        $this->info("📋 Current schema for table: {$table}");
        $this->newLine();

        if (!Schema::hasTable($table)) {
            $this->error("❌ Table '{$table}' does not exist");
            return Command::FAILURE;
        }

        $columns = Schema::getColumnListing($table);
        
        $this->info("Columns found: " . count($columns));
        foreach ($columns as $column) {
            $this->line("   • {$column}");
        }

        // Check for specific columns we're interested in
        $this->newLine();
        $this->info("🔍 Checking for our target columns:");
        
        $targetColumns = [
            'tik_relevance_score',
            'tik_keywords', 
            'is_tik_related',
            'issue_year',
            'document_type_code'
        ];

        foreach ($targetColumns as $column) {
            $exists = Schema::hasColumn($table, $column);
            $status = $exists ? '✅ EXISTS' : '❌ MISSING';
            $this->line("   {$column}: {$status}");
        }

        return Command::SUCCESS;
    }
}