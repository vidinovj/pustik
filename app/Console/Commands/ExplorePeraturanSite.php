<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class ExplorePeraturanSite extends Command
{
    protected $signature = 'legal-docs:explore-peraturan';
    protected $description = 'Explore peraturan.go.id to find correct URL structure';

    public function handle(): int
    {
        $this->info('🔍 Exploring peraturan.go.id Structure');
        $this->newLine();

        try {
            // Step 1: Get homepage
            $this->info("📥 Fetching homepage...");
            $response = Http::timeout(30)->get('https://peraturan.go.id');
            
            if (!$response->successful()) {
                $this->error("❌ Failed to fetch homepage: HTTP {$response->status()}");
                return Command::FAILURE;
            }

            $html = $response->body();
            $this->analyzeHomepage($html);

            // Step 2: Look for document listings without search
            $this->exploreDocumentListings();

            // Step 3: Test potential category/listing URLs
            $this->testCategoryUrls();

        } catch (\Exception $e) {
            $this->error("❌ Exploration failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function analyzeHomepage(string $html): void
    {
        $this->info("🔍 Analyzing homepage structure...");
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);

        // Save homepage for manual inspection
        $homepagePath = storage_path('logs/peraturan_homepage_' . date('Y-m-d_H-i-s') . '.html');
        file_put_contents($homepagePath, $html);
        $this->line("💾 Homepage saved: {$homepagePath}");

        // Check page title
        $titleElement = $xpath->query('//title')->item(0);
        $title = $titleElement ? trim($titleElement->textContent) : 'No title found';
        $this->line("📄 Page Title: {$title}");

        // Look for search forms
        $this->info("🔍 Looking for search forms...");
        $searchForms = $xpath->query('//form');
        $this->line("Found {$searchForms->length} form(s)");
        
        foreach ($searchForms as $form) {
            $action = $form->getAttribute('action');
            $method = $form->getAttribute('method') ?: 'GET';
            $this->line("  • Form action: '{$action}', method: {$method}");
            
            // Look for input fields in this form
            $inputs = $xpath->query('.//input', $form);
            foreach ($inputs as $input) {
                $name = $input->getAttribute('name');
                $type = $input->getAttribute('type') ?: 'text';
                $placeholder = $input->getAttribute('placeholder');
                if ($name) {
                    $this->line("    - Input: name='{$name}', type='{$type}', placeholder='{$placeholder}'");
                }
            }
        }

        // Look for navigation links
        $this->info("🔗 Looking for navigation/menu links...");
        $navPatterns = [
            '//nav//a',
            '//ul[contains(@class, "menu")]//a',
            '//div[contains(@class, "menu")]//a',
            '//header//a',
            '//*[contains(@class, "nav")]//a'
        ];

        $documentLinks = [];
        foreach ($navPatterns as $pattern) {
            $links = $xpath->query($pattern);
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                
                if ($href && $text) {
                    // Look for document-related keywords
                    if (stripos($text, 'dokumen') !== false || 
                        stripos($text, 'peraturan') !== false ||
                        stripos($text, 'database') !== false ||
                        stripos($text, 'cari') !== false ||
                        stripos($text, 'search') !== false) {
                        
                        // Make absolute URL
                        if (!filter_var($href, FILTER_VALIDATE_URL)) {
                            $href = 'https://peraturan.go.id' . '/' . ltrim($href, '/');
                        }
                        $documentLinks[$href] = $text;
                    }
                }
            }
        }

        if (count($documentLinks) > 0) {
            $this->info("📋 Found potential document/search links:");
            foreach ($documentLinks as $url => $text) {
                $this->line("  • {$text}: {$url}");
            }
        }

        // Look for any links with 'peraturan', 'dokumen', etc in href
        $this->info("🔗 Looking for document-related links in href attributes...");
        $docLinks = $xpath->query('//a[contains(@href, "peraturan") or contains(@href, "dokumen") or contains(@href, "search") or contains(@href, "cari")]');
        
        $this->line("Found {$docLinks->length} document-related links");
        $uniquePatterns = [];
        
        foreach ($docLinks as $link) {
            $href = $link->getAttribute('href');
            $text = trim($link->textContent);
            
            // Extract pattern
            $pattern = parse_url($href, PHP_URL_PATH);
            if (!in_array($pattern, $uniquePatterns)) {
                $uniquePatterns[] = $pattern;
                $this->line("  • Pattern: {$pattern} - Text: {$text}");
            }
        }

        $this->newLine();
    }

    protected function exploreDocumentListings(): void
    {
        $this->info("📋 Testing common document listing patterns...");

        $listingPatterns = [
            '/peraturan',
            '/dokumen',
            '/database',
            '/list',
            '/browse',
            '/katalog',
            '/direktori',
            '/common',
            '/public',
            '/produk-hukum',
            '/daftar-peraturan',
            '/jenis-peraturan'
        ];

        $workingUrls = [];

        foreach ($listingPatterns as $pattern) {
            $testUrl = 'https://peraturan.go.id' . $pattern;
            
            try {
                $response = Http::timeout(10)->get($testUrl);
                
                if ($response->successful()) {
                    $this->info("✅ {$pattern} - HTTP {$response->status()}");
                    
                    // Quick check for document-like content
                    $content = $response->body();
                    if (stripos($content, 'nomor') !== false || 
                        stripos($content, 'tahun') !== false ||
                        stripos($content, 'jenis') !== false) {
                        $this->line("   📄 Contains regulation-like content!");
                        $workingUrls[] = $testUrl;
                        
                        // Save sample for inspection
                        $filename = 'peraturan_' . str_replace('/', '_', $pattern) . '_' . date('His') . '.html';
                        file_put_contents(storage_path("logs/{$filename}"), $content);
                        $this->line("   💾 Sample saved: storage/logs/{$filename}");
                    }
                } else {
                    $this->line("❌ {$pattern} - HTTP {$response->status()}");
                }
                
            } catch (\Exception $e) {
                $this->line("❌ {$pattern} - Error: " . substr($e->getMessage(), 0, 50));
            }
            
            usleep(300000); // 0.3 seconds delay
        }

        if (count($workingUrls) > 0) {
            $this->newLine();
            $this->info("🎉 Found working document listing URLs:");
            foreach ($workingUrls as $url) {
                $this->line("  • {$url}");
            }
        }

        $this->newLine();
    }

    protected function testCategoryUrls(): void
    {
        $this->info("📚 Testing category-based URLs...");

        // Common Indonesian regulation types
        $categories = [
            'uu' => 'Undang-Undang',
            'pp' => 'Peraturan Pemerintah',
            'perpres' => 'Peraturan Presiden',
            'permen' => 'Peraturan Menteri',
            'kepmen' => 'Keputusan Menteri',
            'perda' => 'Peraturan Daerah'
        ];

        foreach ($categories as $code => $name) {
            $testUrls = [
                "https://peraturan.go.id/{$code}",
                "https://peraturan.go.id/kategori/{$code}",
                "https://peraturan.go.id/jenis/{$code}",
                "https://peraturan.go.id/peraturan/{$code}"
            ];

            foreach ($testUrls as $testUrl) {
                try {
                    $response = Http::timeout(8)->get($testUrl);
                    
                    if ($response->successful()) {
                        $this->info("✅ {$name} ({$code}): {$testUrl}");
                        
                        // Save first working sample for each category
                        $filename = "peraturan_category_{$code}_" . date('His') . '.html';
                        file_put_contents(storage_path("logs/{$filename}"), $response->body());
                        $this->line("   💾 Sample saved: storage/logs/{$filename}");
                        break; // Move to next category
                    }
                    
                } catch (\Exception $e) {
                    // Silent fail for category testing
                }
                
                usleep(200000); // 0.2 seconds delay
            }
        }

        $this->newLine();
    }
}