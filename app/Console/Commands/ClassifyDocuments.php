<?php
// app/Console/Commands/ClassifyDocuments.php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Services\DocumentClassifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyDocuments extends Command
{
    protected $signature = 'documents:classify 
                           {--dry-run : Show classification results without saving}
                           {--force : Re-classify documents that already have categories}
                           {--type= : Only classify specific document types (uu,pp,perpres,permen)}
                           {--tik-only : Only classify TIK-related documents}
                           {--limit= : Limit number of documents to process}
                           {--show-details : Show detailed classification reasoning}
                           {--show-categories : Display available canonical categories and exit}';
    
    protected $description = 'Classify legal documents into canonical categories using rule-based classification';

    public function handle()
    {
        // Handle show categories option first
        if ($this->option('show-categories')) {
            $this->showAvailableCategories();
            return;
        }
        
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');
        $showDetails = $this->option('show-details');
        $limit = $this->option('limit');
        $typeFilter = $this->option('type');
        $tikOnly = $this->option('tik-only');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be saved');
        }

        $this->info('🏷️  DOCUMENT CLASSIFICATION SYSTEM');
        $this->info('Using canonical Indonesian legal document categories');
        $this->newLine();

        // Build query
        $query = LegalDocument::query();
        
        if (!$force) {
            $query->where(function($q) {
                $q->whereNull('document_category')
                  ->orWhere('document_category', '')
                  ->orWhere('document_category', 'unknown');
            });
        }
        
        if ($typeFilter) {
            $types = explode(',', $typeFilter);
            $query->where(function($q) use ($types) {
                foreach ($types as $type) {
                    $typeMap = [
                        'uu' => 'Undang-undang',
                        'pp' => 'Peraturan Pemerintah',
                        'perpres' => 'Peraturan Presiden',
                        'permen' => 'Peraturan Menteri'
                    ];
                    
                    if (isset($typeMap[$type])) {
                        $q->orWhere('document_type', 'like', '%' . $typeMap[$type] . '%');
                    }
                }
            });
        }
        
        if ($tikOnly) {
            $query->where('tik_relevance_score', '>', 10);
        }
        
        if ($limit) {
            $query->limit((int) $limit);
        }
        
        $documents = $query->get();
        
        if ($documents->isEmpty()) {
            $this->warn('No documents found to classify.');
            return;
        }
        
        $this->info("📋 Processing {$documents->count()} documents...");
        $this->newLine();

        $stats = [
            'processed' => 0,
            'classified' => 0,
            'hierarchy_classified' => 0,
            'subject_classified' => 0,
            'special_classified' => 0,
            'high_confidence' => 0,
            'category_changes' => 0
        ];

        $categoryStats = [];
        $progressBar = $this->output->createProgressBar($documents->count());
        
        foreach ($documents as $document) {
            $stats['processed']++;
            
            // Get current category
            $oldCategory = $document->document_category;
            
            // Classify the document
            $classification = DocumentClassifierService::classifyDocument($document);
            $newCategory = $classification['primary_category'];
            
            // Track category statistics
            if (!isset($categoryStats[$newCategory])) {
                $categoryStats[$newCategory] = 0;
            }
            $categoryStats[$newCategory]++;
            
            // Check classification types
            foreach ($classification['all_classifications'] as $cls) {
                if ($cls['type'] === 'hierarchy') $stats['hierarchy_classified']++;
                if ($cls['type'] === 'subject_matter') $stats['subject_classified']++;
                if ($cls['type'] === 'special') $stats['special_classified']++;
                if ($cls['confidence'] >= 0.8) $stats['high_confidence']++;
            }
            
            $hasChanges = ($newCategory !== $oldCategory && $newCategory !== 'unknown');
            
            if ($hasChanges) {
                $stats['classified']++;
                if ($oldCategory && $oldCategory !== 'unknown') {
                    $stats['category_changes']++;
                }
                
                if ($showDetails && $stats['classified'] <= 5) {
                    $this->showClassificationDetails($document, $classification, $oldCategory);
                }
                
                if (!$isDryRun) {
                    // Update document
                    $document->document_category = $newCategory;
                    
                    // Update metadata with classification info
                    $metadata = $document->metadata ?? [];
                    $metadata['classification'] = $classification;
                    $document->metadata = $metadata;
                    
                    $document->save();
                }
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);

        $this->displayResults($stats, $categoryStats, $isDryRun);
        $this->showCategoryBreakdown($categoryStats);
        
        if (!$isDryRun && $stats['classified'] > 0) {
            $this->info("✅ Classification completed! {$stats['classified']} documents categorized.");
        }
    }

    private function showClassificationDetails(LegalDocument $document, array $classification, ?string $oldCategory): void
    {
        $this->newLine();
        $this->line("📄 " . substr($document->title, 0, 70) . '...');
        $this->line("   Old Category: " . ($oldCategory ?? 'None'));
        $this->line("   New Category: {$classification['primary_category']}");
        
        $this->line("   Classifications:");
        foreach (array_slice($classification['all_classifications'], 0, 3) as $cls) {
            $confidence = round($cls['confidence'] * 100);
            $categoryName = DocumentClassifierService::getCategory($cls['category'])['name'] ?? $cls['category'];
            $this->line("     • {$categoryName} ({$confidence}% confidence) - {$cls['reasoning']}");
        }
        
        $this->line("   Tags: " . implode(', ', $classification['suggested_tags']));
        $this->newLine();
    }

    private function displayResults(array $stats, array $categoryStats, bool $isDryRun): void
    {
        $this->info('📊 CLASSIFICATION RESULTS:');
        
        $tableData = [
            ['Documents Processed', $stats['processed']],
            ['Successfully Classified', $stats['classified']],
            ['Hierarchy-based', $stats['hierarchy_classified']],
            ['Subject Matter-based', $stats['subject_classified']],
            ['Special Type-based', $stats['special_classified']],
            ['High Confidence (80%+)', $stats['high_confidence']],
            ['Category Changes', $stats['category_changes']]
        ];
        
        $this->table(['Metric', 'Count'], $tableData);

        if ($isDryRun) {
            $this->newLine();
            $this->warn('⚠️  This was a dry run. Use without --dry-run to apply classifications.');
            $this->info('💡 Use --show-details to see classification reasoning for first 5 documents.');
        }
    }

    private function showCategoryBreakdown(array $categoryStats): void
    {
        if (empty($categoryStats)) return;
        
        $this->newLine();
        $this->info('📁 CATEGORY BREAKDOWN:');
        
        arsort($categoryStats);
        
        $tableData = [];
        foreach (array_slice($categoryStats, 0, 10, true) as $category => $count) {
            $categoryInfo = DocumentClassifierService::getCategory($category);
            $displayName = $categoryInfo['name'] ?? ucfirst(str_replace('_', ' ', $category));
            $tableData[] = [$displayName, $count, $categoryInfo['description'] ?? ''];
        }
        
        $this->table(['Category', 'Count', 'Description'], $tableData);
    }

    private function showAvailableCategories(): void
    {
        $this->info('📚 AVAILABLE CANONICAL CATEGORIES:');
        $this->newLine();
        
        $categories = DocumentClassifierService::getCanonicalCategories();
        
        // Group by type
        $hierarchyCategories = DocumentClassifierService::getCategoriesByType('hierarchy');
        $subjectCategories = DocumentClassifierService::getCategoriesByType('subject');
        $specialCategories = DocumentClassifierService::getCategoriesByType('special');
        
        $this->info("🏛️  HIERARCHY CATEGORIES (Legal Structure):");
        foreach ($hierarchyCategories as $key => $data) {
            $this->line("  • {$data['name']} (Level {$data['hierarchy_level']}) - {$data['description']}");
        }
        
        $this->newLine();
        $this->info("🎯 SUBJECT MATTER CATEGORIES (TIK-focused):");
        foreach ($subjectCategories as $key => $data) {
            $this->line("  • {$data['name']} - {$data['description']}");
        }
        
        $this->newLine();
        $this->info("⚡ SPECIAL CATEGORIES (Document Characteristics):");
        foreach ($specialCategories as $key => $data) {
            $this->line("  • {$data['name']} - {$data['description']}");
        }
        
        $this->newLine();
        $this->info("💡 USAGE EXAMPLES:");
        $this->line("  php artisan documents:classify --dry-run");
        $this->line("  php artisan documents:classify --type=uu --tik-only");
        $this->line("  php artisan documents:classify --force --show-details");
    }
}