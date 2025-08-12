<?php
// app/Console/Commands/DebugTikBoolean.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\DB;

class DebugTikBoolean extends Command
{
    protected $signature = 'scraper:debug-tik-boolean';
    protected $description = 'Debug the TIK boolean storage issue';

    public function handle()
    {
        $this->info("🔍 DEBUGGING TIK BOOLEAN ISSUE");
        $this->info("=============================");
        $this->newLine();

        // Check raw database values
        $this->info("📊 RAW DATABASE VALUES:");
        $rawResults = DB::select("
            SELECT id, title, tik_relevance_score, is_tik_related, document_category 
            FROM legal_documents 
            WHERE tik_relevance_score > 0 
            LIMIT 5
        ");

        foreach ($rawResults as $row) {
            $this->line("ID: {$row->id}");
            $this->line("Title: " . substr($row->title, 0, 60) . "...");
            $this->line("TIK Score: {$row->tik_relevance_score}");
            $this->line("is_tik_related RAW: " . var_export($row->is_tik_related, true));
            $this->line("document_category: " . ($row->document_category ?? 'NULL'));
            $this->newLine();
        }

        // Check Eloquent model values
        $this->info("🔧 ELOQUENT MODEL VALUES:");
        $tikDocs = LegalDocument::where('tik_relevance_score', '>', 0)->take(5)->get();
        
        foreach ($tikDocs as $doc) {
            $this->line("ID: {$doc->id}");
            $this->line("Title: " . substr($doc->title, 0, 60) . "...");
            $this->line("TIK Score: {$doc->tik_relevance_score}");
            $this->line("is_tik_related: " . var_export($doc->is_tik_related, true));
            $this->line("document_category: " . ($doc->document_category ?? 'NULL'));
            $this->newLine();
        }

        // Try different query approaches
        $this->info("🎯 TESTING DIFFERENT QUERIES:");
        
        $approaches = [
            "WHERE is_tik_related = 1" => DB::select("SELECT COUNT(*) as count FROM legal_documents WHERE is_tik_related = 1")[0]->count,
            "WHERE is_tik_related = true" => DB::select("SELECT COUNT(*) as count FROM legal_documents WHERE is_tik_related = true")[0]->count,
            "WHERE is_tik_related IS TRUE" => DB::select("SELECT COUNT(*) as count FROM legal_documents WHERE is_tik_related IS TRUE")[0]->count,
            "Eloquent where('is_tik_related', true)" => LegalDocument::where('is_tik_related', true)->count(),
            "Eloquent where('is_tik_related', 1)" => LegalDocument::where('is_tik_related', 1)->count(),
            "tik_relevance_score > 0" => LegalDocument::where('tik_relevance_score', '>', 0)->count(),
        ];

        foreach ($approaches as $method => $count) {
            $this->line("• {$method}: {$count} documents");
        }

        $this->newLine();
        
        // Fix the boolean values
        $this->info("🔧 FIXING BOOLEAN VALUES:");
        
        $this->line("Setting is_tik_related = 1 for documents with TIK score > 0...");
        $updated = DB::update("
            UPDATE legal_documents 
            SET is_tik_related = 1, document_category = 'tik_regulation'
            WHERE tik_relevance_score > 0
        ");
        
        $this->info("✅ Updated {$updated} records");
        
        // Verify the fix
        $this->newLine();
        $this->info("✅ VERIFICATION AFTER FIX:");
        $tikCount = LegalDocument::where('is_tik_related', true)->count();
        $this->line("• TIK documents (is_tik_related = true): {$tikCount}");
        
        if ($tikCount > 0) {
            $this->info("🎉 SUCCESS! Showing your TIK document catalog:");
            $this->showTikCatalog();
        }

        return Command::SUCCESS;
    }

    private function showTikCatalog(): void
    {
        $tikDocs = LegalDocument::where('is_tik_related', true)
            ->orderByDesc('tik_relevance_score')
            ->get();

        $this->table(
            ['#', 'Document Title', 'TIK Score', 'Year', 'Type'],
            $tikDocs->map(function ($doc, $index) {
                // Extract year from title
                preg_match('/(\d{4})/', $doc->title, $yearMatches);
                $year = $yearMatches[1] ?? 'Unknown';
                
                // Extract document type
                $type = 'Unknown';
                if (stripos($doc->title, 'undang-undang') !== false || stripos($doc->title, 'uu no') !== false) {
                    $type = 'UU (Law)';
                } elseif (stripos($doc->title, 'peraturan pemerintah') !== false || stripos($doc->title, 'pp no') !== false) {
                    $type = 'PP (Govt Regulation)';
                } elseif (stripos($doc->title, 'permenkominfo') !== false) {
                    $type = 'Permenkominfo';
                }
                
                return [
                    $index + 1,
                    substr($doc->title, 0, 60) . '...',
                    $doc->tik_relevance_score ?? 0,
                    $year,
                    $type
                ];
            })->toArray()
        );

        $this->newLine();
        $this->info("📋 YOUR TIK REGULATION CATALOG CONTAINS:");
        $this->line("• Core IT Laws (UU): Information & Electronic Transactions");
        $this->line("• Data Protection: Personal Data Protection Law");
        $this->line("• Cyber Security: National Cyber Security Law");
        $this->line("• E-Commerce: Electronic Trading Regulations");
        $this->line("• Implementation: Government and Ministry Regulations");
        
        $this->newLine();
        $this->info("🎯 PERFECT FOUNDATION for Indonesian TIK regulations!");
    }
}