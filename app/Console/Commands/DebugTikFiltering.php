<?php
// app/Console/Commands/DebugTikFiltering.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LegalDocument;

class DebugTikFiltering extends Command
{
    protected $signature = 'legal-docs:debug-tik-filter';
    protected $description = 'Debug TIK keyword filtering to see why documents are being filtered out';

    protected array $tikKeywords = [
        'teknologi informasi', 'teknologi komunikasi', 'tik', 'ict',
        'informatika', 'telekomunikasi', 'digital', 'elektronik',
        'cyber', 'internet', 'data', 'sistem informasi',
        'komputer', 'software', 'hardware', 'jaringan',
        'keamanan siber', 'e-government', 'smart city',
        'fintech', 'startup', 'platform digital'
    ];

    public function handle(): int
    {
        $this->info('🔍 Debugging TIK Keyword Filtering');
        $this->newLine();

        // Get recent documents that were scraped
        $recentDocs = LegalDocument::orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'metadata', 'full_text']);

        if ($recentDocs->isEmpty()) {
            $this->warn('No documents found. Run scraping first.');
            return Command::SUCCESS;
        }

        $this->info("Found {$recentDocs->count()} recent documents. Analyzing TIK relevance:");
        $this->newLine();

        foreach ($recentDocs as $i => $doc) {
            $this->line("📄 Document " . ($i + 1) . ":");
            $this->line("   Title: " . substr($doc->title, 0, 80) . '...');
            
            // Check if currently marked as TIK-related
            $currentlyTik = $doc->metadata['tik_related'] ?? false;
            $this->line("   Currently TIK-related: " . ($currentlyTik ? 'Yes' : 'No'));
            
            // Run our filtering logic
            $isTikByFilter = $this->isTikRelated($doc);
            $this->line("   TIK by filter logic: " . ($isTikByFilter ? 'Yes' : 'No'));
            
            if ($currentlyTik !== $isTikByFilter) {
                $this->warn("   ⚠️  MISMATCH! Current: {$currentlyTik}, Filter: {$isTikByFilter}");
            }
            
            // Show which keywords match
            $matchingKeywords = $this->getMatchingKeywords($doc);
            if (!empty($matchingKeywords)) {
                $this->line("   Matching keywords: " . implode(', ', $matchingKeywords));
            } else {
                $this->line("   No keywords matched");
            }
            
            $this->newLine();
        }

        // Suggest keyword improvements
        $this->suggestKeywordImprovements($recentDocs);

        return Command::SUCCESS;
    }

    protected function isTikRelated($document): bool
    {
        $title = strtolower($document->title ?? '');
        $content = strtolower($document->full_text ?? '');
        $subject = strtolower($document->metadata['subject'] ?? '');
        
        $searchText = $title . ' ' . $content . ' ' . $subject;

        foreach ($this->tikKeywords as $keyword) {
            if (stripos($searchText, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function getMatchingKeywords($document): array
    {
        $title = strtolower($document->title ?? '');
        $content = strtolower($document->full_text ?? '');
        $subject = strtolower($document->metadata['subject'] ?? '');
        
        $searchText = $title . ' ' . $content . ' ' . $subject;
        $matches = [];

        foreach ($this->tikKeywords as $keyword) {
            if (stripos($searchText, $keyword) !== false) {
                $matches[] = $keyword;
            }
        }

        return $matches;
    }

    protected function suggestKeywordImprovements(array $documents): void
    {
        $this->info('💡 Keyword Analysis & Suggestions:');
        
        // Analyze titles for common IT-related words not in our keywords
        $allTitles = $documents->pluck('title')->implode(' ');
        $words = str_word_count(strtolower($allTitles), 1);
        $wordFreq = array_count_values($words);
        
        // Look for potential IT-related words
        $potentialKeywords = [];
        $itRelatedWords = [
            'aplikasi', 'sistem', 'layanan', 'platform', 'teknologi',
            'komunikasi', 'informasi', 'elektronik', 'digital',
            'keamanan', 'jaringan', 'database', 'server',
            'mobile', 'web', 'online', 'virtual', 'cloud'
        ];
        
        foreach ($itRelatedWords as $word) {
            if (isset($wordFreq[$word]) && $wordFreq[$word] > 0) {
                $potentialKeywords[$word] = $wordFreq[$word];
            }
        }
        
        if (!empty($potentialKeywords)) {
            $this->line('Found these IT-related words in document titles:');
            arsort($potentialKeywords);
            foreach (array_slice($potentialKeywords, 0, 10, true) as $word => $count) {
                $this->line("   • {$word} (appears {$count} times)");
            }
        }
        
        $this->newLine();
        $this->info('🚀 Recommended actions:');
        $this->line('1. Add broader keywords like "aplikasi", "sistem", "layanan"');
        $this->line('2. Consider agency-based filtering (all Komdigi docs are TIK-related)');
        $this->line('3. Lower the TIK filtering threshold for specific sources');
        
        $this->newLine();
        $this->ask('Press Enter to continue...');
    }
}