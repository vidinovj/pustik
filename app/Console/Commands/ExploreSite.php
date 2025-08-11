<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class ExploreSite extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'legal-docs:explore-site {url=https://jdih.kemlu.go.id}';

    /**
     * The console command description.
     */
    protected $description = 'Explore a legal document site to find correct URLs and structure';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $baseUrl = $this->argument('url');
        
        $this->info("🔍 Exploring Site: {$baseUrl}");
        $this->newLine();

        try {
            // Step 1: Get the homepage
            $this->info("📥 Fetching homepage...");
            $response = Http::timeout(30)->get($baseUrl);
            
            if (!$response->successful()) {
                $this->error("❌ Failed to fetch homepage: HTTP {$response->status()}");
                return Command::FAILURE;
            }

            $html = $response->body();
            $this->info("✅ Homepage fetched successfully");

            // Step 2: Analyze the homepage
            $this->analyzeHomepage($html, $baseUrl);

            // Step 3: Test common document listing patterns
            $this->testCommonPatterns($baseUrl);

            // Step 4: Save sample HTML for inspection
            $this->saveSampleHtml($html, 'homepage');

        } catch (\Exception $e) {
            $this->error("❌ Exploration failed: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Analyze the homepage structure.
     */
    protected function analyzeHomepage(string $html, string $baseUrl): void
    {
        $this->info("🔍 Analyzing homepage structure...");
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);

        // Check page title
        $titleElement = $xpath->query('//title')->item(0);
        $title = $titleElement ? trim($titleElement->textContent) : 'No title found';
        $this->line("📄 Page Title: {$title}");

        // Find navigation links
        $this->info("🔗 Finding navigation links...");
        $navPatterns = [
            '//nav//a',
            '//ul[@class="menu"]//a',
            '//div[@class="menu"]//a',
            '//header//a',
            '//*[contains(@class, "nav")]//a',
            '//*[contains(@class, "menu")]//a'
        ];

        $allNavLinks = [];
        foreach ($navPatterns as $pattern) {
            $links = $xpath->query($pattern);
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $text = trim($link->textContent);
                
                if ($href && $text) {
                    // Make absolute URL
                    if (!filter_var($href, FILTER_VALIDATE_URL)) {
                        $href = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
                    }
                    $allNavLinks[$href] = $text;
                }
            }
        }

        // Filter for document-related links
        $documentLinks = [];
        $keywords = ['dokumen', 'document', 'peraturan', 'regulation', 'hukum', 'legal', 'jdih', 'database'];
        
        foreach ($allNavLinks as $url => $text) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false || stripos($url, $keyword) !== false) {
                    $documentLinks[$url] = $text;
                    break;
                }
            }
        }

        if (count($documentLinks) > 0) {
            $this->info("📋 Found potential document links:");
            foreach ($documentLinks as $url => $text) {
                $this->line("  • {$text}: {$url}");
            }
        } else {
            $this->warn("⚠️  No obvious document links found in navigation");
        }

        // Look for search functionality
        $searchForms = $xpath->query('//form[.//input[@type="search"] or contains(@action, "search") or contains(@action, "cari")]');
        if ($searchForms->length > 0) {
            $this->info("🔍 Found {$searchForms->length} search form(s)");
            foreach ($searchForms as $form) {
                $action = $form->getAttribute('action');
                $this->line("  • Search form action: {$action}");
            }
        }

        $this->newLine();
    }

    /**
     * Test common URL patterns for document listings.
     */
    protected function testCommonPatterns(string $baseUrl): void
    {
        $this->info("🧪 Testing common URL patterns...");

        $patterns = [
            // Original patterns
            '/dokumen?jenis=Permenlu',
            '/dokumen?jenis=Kepdirjen',
            '/dokumen?jenis=Surat+Edaran',
            
            // Alternative patterns
            '/dokumen',
            '/documents',
            '/peraturan',
            '/regulations',
            '/database',
            '/search',
            '/cari',
            '/jdih',
            '/legal',
            
            // With different parameters
            '/dokumen?type=permenlu',
            '/dokumen?kategori=permenlu',
            '/dokumen/permenlu',
            '/peraturan/menteri',
            '/database/dokumen',
            
            // API endpoints
            '/api/dokumen',
            '/api/documents',
        ];

        $workingUrls = [];
        
        foreach ($patterns as $pattern) {
            $testUrl = rtrim($baseUrl, '/') . $pattern;
            
            try {
                $response = Http::timeout(10)->get($testUrl);
                
                if ($response->successful()) {
                    $this->info("✅ {$pattern} - HTTP {$response->status()}");
                    $workingUrls[] = $testUrl;
                    
                    // Quick check if it contains document-like content
                    $content = $response->body();
                    if (stripos($content, 'dokumen') !== false || 
                        stripos($content, 'peraturan') !== false ||
                        stripos($content, 'nomor') !== false) {
                        $this->line("   📄 Contains document-related content!");
                        
                        // Save this page for inspection
                        $this->saveSampleHtml($content, 'working_' . str_replace(['/', '?', '='], '_', $pattern));
                    }
                } else {
                    $this->line("❌ {$pattern} - HTTP {$response->status()}");
                }
                
            } catch (\Exception $e) {
                $this->line("❌ {$pattern} - Error: {$e->getMessage()}");
            }
            
            // Small delay to be respectful
            usleep(500000); // 0.5 seconds
        }

        if (count($workingUrls) > 0) {
            $this->newLine();
            $this->info("🎉 Found working URLs:");
            foreach ($workingUrls as $url) {
                $this->line("  • {$url}");
            }
        } else {
            $this->warn("⚠️  No working document URLs found with common patterns");
        }

        $this->newLine();
    }

    /**
     * Save HTML content for manual inspection.
     */
    protected function saveSampleHtml(string $html, string $suffix): void
    {
        $filename = "jdih_exploration_{$suffix}_" . date('Y-m-d_H-i-s') . '.html';
        $path = storage_path("logs/{$filename}");
        
        file_put_contents($path, $html);
        $this->line("💾 Sample HTML saved: {$path}");
    }
}