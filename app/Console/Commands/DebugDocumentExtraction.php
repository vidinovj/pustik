<?php
// app/Console/Commands/DebugDocumentExtraction.php - SIMPLE FIX

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use DOMDocument;
use DOMXPath;

class DebugDocumentExtraction extends Command
{
    protected $signature = 'legal-docs:debug-extraction';
    protected $description = 'Debug document extraction from actual peraturan.go.id pages';

    public function handle(): int
    {
        $this->info('🔍 Debugging Document Extraction from Real URLs');
        $this->newLine();

        // Test the specific URLs from your logs
        $testUrls = [
            'https://peraturan.go.id/id/permenpar-no-4-tahun-2025',
            'https://peraturan.go.id/id/permenkes-no-1-tahun-2025',
            'https://peraturan.go.id/id/permenpanrb-no-10-tahun-2025'
        ];

        foreach ($testUrls as $url) {
            $this->debugSingleDocument($url);
            $this->line('─────────────────────────────────────────────');
            
            // Add delay between requests
            sleep(3);
        }

        return Command::SUCCESS;
    }

    protected function debugSingleDocument(string $url): void
    {
        $this->info("🔍 Analyzing: " . basename($url));
        $this->line("URL: {$url}");

        try {
            // Step 1: Fetch the page with realistic browser headers
            $this->line("📥 Fetching page with realistic browser headers...");
            
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Cache-Control' => 'max-age=0',
                'DNT' => '1',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"macOS"',
                'Referer' => 'https://google.com/',
            ])
            ->timeout(45)
            ->get($url);
            
            if (!$response->successful()) {
                $this->error("❌ HTTP {$response->status()}");
                $this->line("Response headers: " . json_encode($response->headers()));
                $this->line("Response preview: " . substr($response->body(), 0, 200));
                return;
            }

            $html = $response->body();
            $htmlSize = strlen($html);
            $this->info("✅ Page fetched: {$htmlSize} bytes");

            // Save HTML for manual inspection
            $filename = 'debug_' . str_replace(['/', '-', ':'], '_', basename($url)) . '_' . date('His') . '.html';
            $filePath = storage_path("logs/{$filename}");
            file_put_contents($filePath, $html);
            $this->line("💾 HTML saved: {$filePath}");

            // Step 2: Enhanced login page detection
            $this->line("🔍 Checking for login page indicators...");
            $loginIndicators = [
                'E-Pengundangan | Login',
                'Login | E-penerjemah', 
                'login form',
                'username',
                'password',
                'masuk',
                'sign in',
                'form-login',
                'loginForm',
                'input[type="password"]',
                'name="username"',
                'name="password"',
                'class="login"',
                'id="login"'
            ];
            
            $loginFound = [];
            foreach ($loginIndicators as $indicator) {
                if (stripos($html, $indicator) !== false) {
                    $loginFound[] = $indicator;
                }
            }

            if (count($loginFound) > 0) {
                $this->error("❌ LOGIN PAGE DETECTED: " . implode(', ', $loginFound));
                $this->line("This explains why no data was extracted!");
                $this->analyzeLoginPage($html);
                return;
            } else {
                $this->info("✅ Not a login page - proceeding with extraction");
            }

            // Step 3: Parse HTML
            $this->line("🔍 Parsing HTML...");
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $success = $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();
            
            if (!$success) {
                $this->error("❌ Failed to parse HTML");
                return;
            }
            
            $xpath = new DOMXPath($dom);
            $this->info("✅ HTML parsed successfully");

            // Step 4: Try document extraction
            $this->line("🔍 Attempting document data extraction...");
            
            $documentData = $this->extractDocumentData($dom, $xpath, $url);
            
            if ($documentData) {
                $this->info("🎉 EXTRACTION SUCCESSFUL!");
                $this->line("  Title: " . $documentData['title']);
                $this->line("  Type: " . $documentData['document_type']);
                $this->line("  Number: " . $documentData['document_number']);
                $this->line("  Content Length: " . strlen($documentData['full_text']) . " characters");
                $this->info("✅ This document should be scrapable by your main scraper!");
            } else {
                $this->warn("⚠️  Could not extract valid document data");
            }

        } catch (\Exception $e) {
            $this->error("❌ Debug failed: {$e->getMessage()}");
            $this->line("Stack trace: " . $e->getTraceAsString());
        }
    }

    protected function analyzeLoginPage(string $html): void
    {
        $this->newLine();
        $this->warn("🔍 LOGIN PAGE ANALYSIS:");
        
        // Check for specific patterns that might help us bypass
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Look for javascript redirects
        $scripts = $xpath->query('//script');
        foreach ($scripts as $script) {
            $scriptContent = $script->textContent;
            if (stripos($scriptContent, 'location.href') !== false || 
                stripos($scriptContent, 'window.location') !== false ||
                stripos($scriptContent, 'redirect') !== false) {
                $this->line("  📜 JavaScript redirect detected");
                break;
            }
        }
        
        // Look for meta refresh
        $metaRefresh = $xpath->query('//meta[@http-equiv="refresh"]');
        if ($metaRefresh->length > 0) {
            $this->line("  🔄 Meta refresh detected");
        }
        
        $this->newLine();
        $this->info("💡 NEXT STEPS:");
        $this->line("  1. Wait for Puppeteer installation to complete");
        $this->line("  2. Run: php artisan legal-docs:browser-scrape");
        $this->line("  3. Or try accessing homepage first to establish session");
    }

    protected function extractDocumentData(DOMDocument $dom, DOMXPath $xpath, string $url): ?array
    {
        try {
            // Get page title
            $titleElement = $xpath->query('//title')->item(0);
            $pageTitle = $titleElement ? trim($titleElement->textContent) : '';
            
            // Extract content title
            $titlePatterns = [
                '//h1[@class="title"]',
                '//h1',
                '//h2[@class="title"]', 
                '//h2',
                '//*[@class="document-title"]',
                '//*[contains(@class, "judul")]'
            ];
            
            $title = '';
            foreach ($titlePatterns as $pattern) {
                $titleElement = $xpath->query($pattern)->item(0);
                if ($titleElement) {
                    $title = trim($titleElement->textContent);
                    if (!empty($title) && strlen($title) > 15 && !stripos($title, 'login')) {
                        break;
                    }
                }
            }
            
            // Fallback to page title if no good content title
            if (empty($title) || stripos($title, 'login') !== false) {
                $title = $pageTitle;
            }
            
            // Final validation
            if (empty($title) || strlen($title) < 15 || stripos($title, 'login') !== false) {
                return null;
            }

            // Extract document number from URL pattern
            $documentNumber = '';
            if (preg_match('/\/id\/([\w\-]+)/', $url, $matches)) {
                $documentNumber = $matches[1];
            }

            // Extract document type from URL
            $documentType = 'Peraturan Perundang-undangan';
            if (stripos($url, '/uu/') !== false) {
                $documentType = 'Undang-Undang';
            } elseif (stripos($url, '/pp/') !== false) {
                $documentType = 'Peraturan Pemerintah';
            } elseif (stripos($url, '/perpres/') !== false) {
                $documentType = 'Peraturan Presiden';
            } elseif (stripos($url, '/permen/') !== false) {
                $documentType = 'Peraturan Menteri';
            }

            // Extract content for full text
            $contentPatterns = [
                '//div[contains(@class, "content")]',
                '//main',
                '//article',
                '//div[contains(@class, "document")]'
            ];
            
            $fullText = '';
            foreach ($contentPatterns as $pattern) {
                $contentElement = $xpath->query($pattern)->item(0);
                if ($contentElement) {
                    $fullText = trim($contentElement->textContent);
                    if (strlen($fullText) > 100 && !stripos($fullText, 'login')) {
                        break;
                    }
                }
            }

            return [
                'title' => $title,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'issue_date' => now()->format('Y-m-d'),
                'source_url' => $url,
                'metadata' => [
                    'source_site' => 'Peraturan.go.id (Debug)',
                    'extraction_method' => 'enhanced_headers',
                    'scraped_at' => now()->toISOString(),
                ],
                'full_text' => $fullText ?: substr($title, 0, 500),
            ];

        } catch (\Exception $e) {
            return null;
        }
    }
}