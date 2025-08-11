<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DocumentSource;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class TestKomdigiDirect extends Command
{
    protected $signature = 'legal-docs:test-komdigi-direct';
    protected $description = 'Direct test of Komdigi scraping with relaxed TIK filtering';

    public function handle(): int
    {
        $this->info('🧪 Direct Komdigi Test');
        $this->newLine();

        // Get or create Komdigi source
        $source = DocumentSource::firstOrCreate([
            'name' => 'komdigi_test'
        ], [
            'display_name' => 'Komdigi Test',
            'base_url' => 'https://jdih.komdigi.go.id',
            'status' => 'active'
        ]);

        // Try to access homepage and find any regulation links
        try {
            $this->info('🌐 Accessing Komdigi homepage...');
            
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            ])
            ->timeout(30)
            ->get($source->base_url);
            
            if (!$response->successful()) {
                $this->error("❌ Failed to access Komdigi: " . $response->status());
                return Command::FAILURE;
            }
            
            $this->info('✅ Homepage accessed successfully');
            
            $html = $response->body();
            $documents = $this->extractAnyDocumentLinks($html, $source);
            
            $this->info("🔍 Found " . count($documents) . " potential document links");
            
            if (count($documents) > 0) {
                $this->displayFoundDocuments($documents);
                
                // For Komdigi, we assume ALL their regulations are TIK-related
                // since it's the ICT Ministry
                $this->saveDocumentsAsKomdigi($documents, $source);
            } else {
                $this->warn('⚠️  No document links found. Site structure may be different.');
                $this->line('Saving homepage title as sample...');
                $this->saveHomepageAsSample($html, $source);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function extractAnyDocumentLinks(string $html, DocumentSource $source): array
    {
        $documents = [];
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Look for any links that might be documents
        $linkPatterns = [
            '//a[contains(@href, "peraturan")]',
            '//a[contains(@href, "keputusan")]',
            '//a[contains(@href, "dokumen")]',
            '//a[contains(text(), "Peraturan")]',
            '//a[contains(text(), "Keputusan")]',
            '//a[contains(text(), "No.")]'
        ];
        
        foreach ($linkPatterns as $pattern) {
            $links = $xpath->query($pattern);
            
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                
                if ($href && $text && strlen($text) > 10) {
                    // Make absolute URL
                    if (!filter_var($href, FILTER_VALIDATE_URL)) {
                        $href = rtrim($source->base_url, '/') . '/' . ltrim($href, '/');
                    }
                    
                    $documents[] = [
                        'url' => $href,
                        'link_text' => $text,
                        'type' => $this->guessDocumentType($text)
                    ];
                }
            }
        }
        
        // Remove duplicates
        $unique = [];
        foreach ($documents as $doc) {
            $unique[$doc['url']] = $doc;
        }
        
        return array_values($unique);
    }

    protected function guessDocumentType(string $text): string
    {
        $text = strtolower($text);
        
        if (stripos($text, 'peraturan menteri') !== false) return 'Peraturan Menteri';
        if (stripos($text, 'keputusan menteri') !== false) return 'Keputusan Menteri';
        if (stripos($text, 'peraturan') !== false) return 'Peraturan';
        if (stripos($text, 'keputusan') !== false) return 'Keputusan';
        
        return 'Dokumen';
    }

    protected function displayFoundDocuments(array $documents): void
    {
        $this->line('📋 Found Documents:');
        
        foreach (array_slice($documents, 0, 5) as $i => $doc) {
            $this->line("   " . ($i + 1) . ". " . substr($doc['link_text'], 0, 60) . '...');
            $this->line("      Type: {$doc['type']}");
            $this->line("      URL: " . substr($doc['url'], 0, 60) . '...');
        }
        
        if (count($documents) > 5) {
            $this->line("   ... and " . (count($documents) - 5) . " more");
        }
        
        $this->newLine();
    }

    protected function saveDocumentsAsKomdigi(array $documents, DocumentSource $source): void
    {
        $saved = 0;
        
        foreach (array_slice($documents, 0, 3) as $doc) { // Save first 3 as test
            try {
                $document = LegalDocument::create([
                    'title' => $doc['link_text'],
                    'document_type' => $doc['type'],
                    'document_number' => $this->extractNumber($doc['link_text']),
                    'source_url' => $doc['url'],
                    'document_source_id' => $source->id,
                    'status' => 'active',
                    'metadata' => [
                        'agency' => 'Kementerian Komunikasi dan Digital',
                        'source_site' => 'JDIH Komdigi',
                        'tik_related' => true, // All Komdigi docs are TIK-related
                        'extraction_method' => 'direct_test',
                        'scraped_at' => now()->toISOString(),
                    ],
                    'full_text' => $doc['link_text'],
                    'checksum' => md5($doc['link_text'] . $doc['url'])
                ]);
                
                $saved++;
                
            } catch (\Exception $e) {
                $this->line("   ❌ Failed to save: " . $e->getMessage());
            }
        }
        
        $this->info("✅ Saved {$saved} documents to database");
        $this->line("All Komdigi regulations are marked as TIK-related");
    }

    protected function saveHomepageAsSample(string $html, DocumentSource $source): void
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        $titleElement = $xpath->query('//title')->item(0);
        $title = $titleElement ? trim($titleElement->textContent) : 'JDIH Komdigi Homepage';
        
        try {
            LegalDocument::create([
                'title' => $title,
                'document_type' => 'Homepage Sample',
                'source_url' => $source->base_url,
                'document_source_id' => $source->id,
                'status' => 'active',
                'metadata' => [
                    'agency' => 'Kementerian Komunikasi dan Digital',
                    'source_site' => 'JDIH Komdigi',
                    'tik_related' => true,
                    'extraction_method' => 'homepage_sample',
                    'note' => 'Sample entry - site accessible but document structure unknown'
                ],
                'full_text' => substr($html, 0, 1000),
                'checksum' => md5($title . $source->base_url)
            ]);
            
            $this->info('✅ Saved homepage as sample document');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to save sample: ' . $e->getMessage());
        }
    }

    protected function extractNumber(string $text): string
    {
        if (preg_match('/no\.?\s*(\d+[\/\w\-\d]*)/i', $text, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
