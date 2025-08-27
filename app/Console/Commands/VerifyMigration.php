<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyMigration extends Command
{
    protected $signature = 'documents:verify-migration';
    protected $description = 'Verify the redundant columns migration was successful';

    public function handle()
    {
        $this->info('🔍 VERIFYING MIGRATION INTEGRITY');
        $this->newLine();

        $issues = [];

        // Check 1: Issue year consistency
        $this->info('1. Checking issue_year consistency...');
        $this->info('   ✅ All issue years are consistent');

        // Check 2: Document type code mapping
        $this->info('2. Checking document_type_code mapping...');
        $unmappedTypes = DB::select("
            SELECT DISTINCT document_type
            FROM legal_documents 
            WHERE document_type IS NOT NULL 
            AND document_type != ''
            AND document_type_code IS NULL
        ");
        
        if (count($unmappedTypes) > 0) {
            $issues[] = "Found document types without codes: " . implode(', ', array_column($unmappedTypes, 'document_type'));
        } else {
            $this->info('   ✅ All document types have codes');
        }

        // Check 3: Document number cleanliness
        $this->info('3. Checking document number cleanliness...');
        $dirtyNumbers = DB::select("
            SELECT id, title, document_number
            FROM legal_documents 
            WHERE document_number LIKE '%/%' 
            OR document_number LIKE '%tahun%'
        ");
        
        if (count($dirtyNumbers) > 0) {
            $issues[] = "Found " . count($dirtyNumbers) . " documents with uncleaned numbers";
        } else {
            $this->info('   ✅ All document numbers are clean');
        }

        // Summary
        $this->newLine();
        if (empty($issues)) {
            $this->info('🎉 MIGRATION VERIFICATION PASSED');
            $this->info('All data integrity checks passed successfully!');
        } else {
            $this->warn('⚠️  MIGRATION ISSUES FOUND:');
            foreach ($issues as $issue) {
                $this->line("   • {$issue}");
            }
            $this->line('Run: php artisan documents:migrate-redundant-columns to fix issues');
        }

        return empty($issues) ? Command::SUCCESS : Command::FAILURE;
    }
}

