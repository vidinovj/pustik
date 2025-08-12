<?php
// app/Console/Commands/FixExistingTikRecords.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\DB;

class FixExistingTikRecords extends Command
{
    protected $signature = 'scraper:fix-tik-records';
    protected $description = 'Update existing documents to mark TIK-related ones properly';

    private array $tikKeywords = [
        'teknologi informasi' => 10,
        'sistem elektronik' => 9,
        'transaksi elektronik' => 9,
        'informasi dan transaksi elektronik' => 10,
        'data pribadi' => 8,
        'cyber security' => 9,
        'keamanan siber' => 9,
        'telekomunikasi' => 8,
        'informatika' => 7,
        'digital' => 6,
        'internet' => 6,
        'komputer' => 5,
        'jaringan' => 5,
        'e-government' => 8,
        'e-commerce' => 7,
        'penyelenggaraan sistem' => 8
    ];

    public function handle()
    {
        $this->info("🔄 FIXING EXISTING TIK RECORDS");
        $this->info("=============================");
        $this->newLine();

        // First, check if columns exist
        $this->checkDatabaseSchema();

        // Get all documents
        $documents = LegalDocument::all();
        $this->info("📊 Found {$documents->count()} total documents");

        $tikCount = 0;
        $updated = 0;

        foreach ($documents as $doc) {
            $tikScore = $this->calculateTikScore($doc->title ?? '');
            $tikKeywords = $this->extractTikKeywords($doc->title ?? '');
            
            if ($tikScore > 0) {
                $tikCount++;
                
                try {
                    $doc->update([
                        'tik_relevance_score' => $tikScore,
                        'tik_keywords' => $tikKeywords,
                        'is_tik_related' => true,
                        'document_category' => 'tik_regulation'
                    ]);
                    
                    $updated++;
                    
                    $this->line("✅ Updated: " . substr($doc->title, 0, 80) . "...");
                    $this->line("   Score: {$tikScore}, Keywords: " . implode(', ', $tikKeywords));
                    $this->newLine();
                    
                } catch (\Exception $e) {
                    $this->error("❌ Failed to update document ID {$doc->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("📈 RESULTS:");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Documents', $documents->count()],
                ['TIK-Related Found', $tikCount],
                ['Successfully Updated', $updated],
                ['Success Rate', $tikCount > 0 ? round(($updated / $tikCount) * 100, 1) . '%' : '0%']
            ]
        );

        // Show TIK documents summary
        $this->newLine();
        $this->showTikSummary();

        return Command::SUCCESS;
    }

    private function checkDatabaseSchema(): void
    {
        $this->info("🔍 Checking database schema...");
        
        try {
            // Check if new columns exist
            $columns = DB::select("SHOW COLUMNS FROM legal_documents");
            $columnNames = collect($columns)->pluck('Field')->toArray();
            
            $requiredColumns = ['tik_relevance_score', 'tik_keywords', 'is_tik_related', 'document_category'];
            $missingColumns = array_diff($requiredColumns, $columnNames);
            
            if (!empty($missingColumns)) {
                $this->error("❌ Missing columns: " . implode(', ', $missingColumns));
                $this->line("Run this SQL first:");
                $this->line("ALTER TABLE legal_documents ADD COLUMN tik_relevance_score INT DEFAULT 0;");
                $this->line("ALTER TABLE legal_documents ADD COLUMN tik_keywords JSON;");
                $this->line("ALTER TABLE legal_documents ADD COLUMN is_tik_related BOOLEAN DEFAULT FALSE;");
                $this->line("ALTER TABLE legal_documents ADD COLUMN document_category VARCHAR(100);");
                exit(1);
            }
            
            $this->info("✅ All required columns exist");
            
        } catch (\Exception $e) {
            $this->error("❌ Database schema check failed: {$e->getMessage()}");
            exit(1);
        }
    }

    private function calculateTikScore(string $title): int
    {
        $score = 0;
        $titleLower = strtolower($title);
        
        foreach ($this->tikKeywords as $keyword => $points) {
            if (strpos($titleLower, $keyword) !== false) {
                $score += $points;
            }
        }
        
        return $score;
    }

    private function extractTikKeywords(string $title): array
    {
        $foundKeywords = [];
        $titleLower = strtolower($title);
        
        foreach ($this->tikKeywords as $keyword => $points) {
            if (strpos($titleLower, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }
        
        return $foundKeywords;
    }

    private function showTikSummary(): void
    {
        $this->info("🎯 TIK DOCUMENTS SUMMARY:");
        
        try {
            $tikDocs = LegalDocument::where('is_tik_related', true)
                ->orderByDesc('tik_relevance_score')
                ->get();
            
            if ($tikDocs->count() === 0) {
                $this->error("❌ No TIK documents found in database");
                return;
            }
            
            $this->table(
                ['Title', 'TIK Score', 'Keywords'],
                $tikDocs->take(5)->map(function ($doc) {
                    return [
                        substr($doc->title, 0, 50) . '...',
                        $doc->tik_relevance_score ?? 0,
                        implode(', ', $doc->tik_keywords ?? [])
                    ];
                })->toArray()
            );
            
            $this->newLine();
            $this->info("📊 TIK Statistics:");
            $this->line("• Total TIK documents: " . $tikDocs->count());
            $this->line("• Average TIK score: " . round($tikDocs->avg('tik_relevance_score'), 1));
            $this->line("• Highest TIK score: " . $tikDocs->max('tik_relevance_score'));
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to show TIK summary: {$e->getMessage()}");
        }
    }
}