<?php
// app/Console/Commands/VerifyDatabaseStatus.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyDatabaseStatus extends Command
{
    protected $signature = 'scraper:verify-database';
    protected $description = 'Check current database status and TIK documents';

    public function handle()
    {
        $this->info("🔍 DATABASE STATUS VERIFICATION");
        $this->info("==============================");
        $this->newLine();

        // Check table schema
        $this->checkTableSchema();
        $this->newLine();

        // Check document counts
        $this->checkDocumentCounts();
        $this->newLine();

        // Show recent documents
        $this->showRecentDocuments();
        $this->newLine();

        // Check for TIK documents
        $this->checkTikDocuments();

        return Command::SUCCESS;
    }

    private function checkTableSchema(): void
    {
        $this->info("📊 TABLE SCHEMA:");
        
        try {
            $columns = DB::select("SHOW COLUMNS FROM legal_documents");
            
            $requiredColumns = [
                'title' => 'Should be TEXT',
                'document_number' => 'Should be TEXT', 
                'tik_relevance_score' => 'Should be INT',
                'tik_keywords' => 'Should be JSON',
                'is_tik_related' => 'Should be BOOLEAN',
            ];

            foreach ($columns as $column) {
                $name = $column->Field;
                $type = $column->Type;
                
                if (array_key_exists($name, $requiredColumns)) {
                    $expected = $requiredColumns[$name];
                    $this->line("✅ {$name}: {$type} ({$expected})");
                    unset($requiredColumns[$name]);
                }
            }

            // Show missing columns
            if (!empty($requiredColumns)) {
                $this->error("❌ Missing columns:");
                foreach ($requiredColumns as $col => $desc) {
                    $this->line("   - {$col}: {$desc}");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ Schema check failed: {$e->getMessage()}");
        }
    }

    private function checkDocumentCounts(): void
    {
        $this->info("📈 DOCUMENT COUNTS:");
        
        try {
            $total = LegalDocument::count();
            $this->line("• Total documents: {$total}");

            if (Schema::hasColumn('legal_documents', 'is_tik_related')) {
                $tikTrue = LegalDocument::where('is_tik_related', true)->count();
                $tikFalse = LegalDocument::where('is_tik_related', false)->count();
                $tikNull = LegalDocument::whereNull('is_tik_related')->count();
                
                $this->line("• TIK-related (true): {$tikTrue}");
                $this->line("• TIK-related (false): {$tikFalse}");
                $this->line("• TIK-related (null): {$tikNull}");
            } else {
                $this->error("• is_tik_related column missing");
            }

            if (Schema::hasColumn('legal_documents', 'tik_relevance_score')) {
                $withScore = LegalDocument::where('tik_relevance_score', '>', 0)->count();
                $this->line("• With TIK score > 0: {$withScore}");
            } else {
                $this->error("• tik_relevance_score column missing");
            }

        } catch (\Exception $e) {
            $this->error("❌ Count check failed: {$e->getMessage()}");
        }
    }

    private function showRecentDocuments(): void
    {
        $this->info("📋 RECENT DOCUMENTS:");
        
        try {
            $recent = LegalDocument::orderBy('created_at', 'desc')->take(5)->get();
            
            if ($recent->count() === 0) {
                $this->line("No documents found");
                return;
            }

            foreach ($recent as $doc) {
                $title = substr($doc->title ?? 'No title', 0, 80);
                $tikRelated = $this->getColumnValue($doc, 'is_tik_related', 'N/A');
                $tikScore = $this->getColumnValue($doc, 'tik_relevance_score', 'N/A');
                
                $this->line("• {$title}...");
                $this->line("  TIK: {$tikRelated}, Score: {$tikScore}");
                $this->line("  Created: {$doc->created_at}");
                $this->newLine();
            }

        } catch (\Exception $e) {
            $this->error("❌ Recent documents check failed: {$e->getMessage()}");
        }
    }

    private function checkTikDocuments(): void
    {
        $this->info("🎯 TIK DOCUMENT ANALYSIS:");
        
        try {
            // Check for TIK keywords in titles
            $allDocs = LegalDocument::all();
            $tikKeywords = [
                'teknologi informasi',
                'transaksi elektronik', 
                'sistem elektronik',
                'informasi dan transaksi',
                'cyber',
                'digital',
                'telekomunikasi'
            ];

            $potentialTik = 0;
            $markedAsTik = 0;

            foreach ($allDocs as $doc) {
                $title = strtolower($doc->title ?? '');
                $hasTikKeyword = false;
                
                foreach ($tikKeywords as $keyword) {
                    if (strpos($title, $keyword) !== false) {
                        $hasTikKeyword = true;
                        break;
                    }
                }
                
                if ($hasTikKeyword) {
                    $potentialTik++;
                    
                    if ($this->getColumnValue($doc, 'is_tik_related', false)) {
                        $markedAsTik++;
                    }
                }
            }

            $this->line("• Documents with TIK keywords: {$potentialTik}");
            $this->line("• Marked as TIK-related: {$markedAsTik}");
            
            if ($potentialTik > 0 && $markedAsTik === 0) {
                $this->error("⚠️ Found TIK documents but none marked as TIK-related!");
                $this->line("Run: php artisan scraper:fix-tik-records");
            }

            // Show examples of potential TIK documents
            if ($potentialTik > 0) {
                $this->newLine();
                $this->info("📌 POTENTIAL TIK DOCUMENTS:");
                
                $examples = $allDocs->filter(function ($doc) use ($tikKeywords) {
                    $title = strtolower($doc->title ?? '');
                    foreach ($tikKeywords as $keyword) {
                        if (strpos($title, $keyword) !== false) {
                            return true;
                        }
                    }
                    return false;
                })->take(3);

                foreach ($examples as $doc) {
                    $this->line("• " . substr($doc->title, 0, 100) . "...");
                }
            }

        } catch (\Exception $e) {
            $this->error("❌ TIK analysis failed: {$e->getMessage()}");
        }
    }

    private function getColumnValue($model, $column, $default = null)
    {
        try {
            if (Schema::hasColumn('legal_documents', $column)) {
                return $model->$column ?? $default;
            }
            return $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}