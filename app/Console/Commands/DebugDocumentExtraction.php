<?php

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
        }

        return Command::SUCCESS;
    }

    protected function debugSingleDocument(string $url): void
    {
        $this->info("🔍 Analyzing: " . basename($url));
        $this->line("URL: {$url}");

        try {
            // Step 1: Fetch the page
            $this->line("📥 Fetching page...");
            $response = Http::timeout(30)->get($url);
            
            if (!$response->successful()) {
                $this->error("❌ HTTP {$response->status()}");
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

            // Step 2: Login page detection
            $this->line("🔍 Checking for login page indicators...");
            $loginIndicators = [
                'E-Pengundangan | Login',
                'Login | E-penerjemah', 
                'login form',
                'username',
                'password',
                'masuk',
                'sign in'
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
                return;
            } else {
                $this->info("✅ Not a login page");
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

            // Step 4: Debug title extraction
            $this->line("🔍 Title extraction analysis:");
            
            // Page title
            $titleElement = $xpath->query('//title')->item(0);
            $pageTitle = $titleElement ? trim($titleElement->textContent) : 'No title found';
            $this->line("  📄 Page <title>: '{$pageTitle}'");

            // Try various title patterns
            $titlePatterns = [
                '//h1[@class="title"]',
                '//h1',
                '//h2[@class="title"]', 
                '//h2',
                '//h3',
                '//*[@class="document-title"]',
                '//*[contains(@class, "judul")]',
                '//*[contains(@class, "title")]'
            ];
            
            $bestTitle = '';
            $bestTitleLength = 0;
            
            foreach ($titlePatterns as $pattern) {
                $elements = $xpath->query($pattern);
                $this->line("  🔍 Pattern '{$pattern}': {$elements->length} matches");
                
                if ($elements->length > 0) {
                    for ($i = 0; $i < min(3, $elements->length); $i++) {
                        $element = $elements->item($i);
                        $text = trim($element->textContent);
                        $length = strlen($text);
                        
                        $this->line("    [{$i}] '{$text}' (length: {$length})");
                        
                        // Track best title candidate
                        if ($length > $bestTitleLength && $length > 15 && !stripos($text, 'login')) {
                            $bestTitle = $text;
                            $bestTitleLength = $length;
                        }
                    }
                }
            }

            $this->newLine();
            if (!empty($bestTitle)) {
                $this->info("✅ Best title found: '{$bestTitle}'");
            } else {
                $this->warn("⚠️  No valid title found (criteria: >15 chars, no 'login')");
            }

            // Step 5: Check for regulation content
            $this->line("🔍 Document content analysis:");
            $documentIndicators = [
                'nomor' => 0,
                'tahun' => 0, 
                'tentang' => 0,
                'peraturan' => 0,
                'menimbang' => 0,
                'mengingat' => 0,
                'memutuskan' => 0,
                'menetapkan' => 0,
                'pasal' => 0
            ];

            foreach ($documentIndicators as $indicator => $count) {
                $documentIndicators[$indicator] = substr_count(strtolower($html), $indicator);
                $count = $documentIndicators[$indicator];
                if ($count > 0) {
                    $this->line("  ✅ '{$indicator}': {$count} occurrences");
                } else {
                    $this->line("  ❌ '{$indicator}': not found");
                }
            }

            $totalScore = array_sum($documentIndicators);
            $this->info("📊 Total document indicators: {$totalScore}");

            // Step 6: Look for document number in URL
            $urlDocNumber = '';
            if (preg_match('/\/id\/(peraturan-[^\/]+)/', $url, $matches)) {
                $urlDocNumber = $matches[1];
                $this->line("🔍 Document number from URL: '{$urlDocNumber}'");
            }

            // Step 7: Content extraction test
            $this->line("🔍 Content extraction test:");
            $contentPatterns = [
                '//div[contains(@class, "content")]',
                '//main',
                '//article',
                '//div[contains(@class, "document")]',
                '//body'
            ];
            
            $bestContent = '';
            foreach ($contentPatterns as $pattern) {
                $elements = $xpath->query($pattern);
                if ($elements->length > 0) {
                    $content = trim($elements->item(0)->textContent);
                    $contentLength = strlen($content);
                    $this->line("  • Pattern '{$pattern}': {$contentLength} chars");
                    
                    if ($contentLength > strlen($bestContent)) {
                        $bestContent = $content;
                    }
                }
            }

            if (strlen($bestContent) > 100) {
                $this->info("✅ Content extracted: " . strlen($bestContent) . " characters");
                $this->line("Preview: " . substr($bestContent, 0, 100) . "...");
            } else {
                $this->warn("⚠️  Minimal content extracted");
            }

            // Step 8: Final assessment
            $this->newLine();
            $this->info("📋 Extraction Assessment:");
            $this->line("  • Valid title: " . (!empty($bestTitle) ? "✅ Yes" : "❌ No"));
            $this->line("  • Document content: " . ($totalScore >= 3 ? "✅ Yes ({$totalScore} indicators)" : "❌ No ({$totalScore} indicators)"));
            $this->line("  • Extractable content: " . (strlen($bestContent) > 100 ? "✅ Yes" : "❌ No"));
            
            if (!empty($bestTitle) && $totalScore >= 3) {
                $this->info("🎉 This document SHOULD be extractable!");
                $this->line("Check why the scraper is rejecting it...");
            } else {
                $this->warn("⚠️  This explains why extraction failed");
            }

        } catch (\Exception $e) {
            $this->error("❌ Debug failed: {$e->getMessage()}");
            $this->line("Stack trace: " . $e->getTraceAsString());
        }
    }
}