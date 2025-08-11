<?php
// app/Console/Commands/TestSessionFlow.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSessionFlow extends Command
{
    protected $signature = 'legal-docs:test-session';
    protected $description = 'Test session establishment flow for peraturan.go.id';

    public function handle(): int
    {
        $this->info('🧪 Testing Session Establishment Flow');
        $this->newLine();

        $baseUrl = 'https://peraturan.go.id';
        $testDocUrl = 'https://peraturan.go.id/id/permenpar-no-4-tahun-2025';

        // Step 1: Visit homepage first
        $this->info('📍 Step 1: Visiting homepage to establish session');
        
        $homepageResponse = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Cache-Control' => 'max-age=0',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
        ])
        ->timeout(30)
        ->get($baseUrl);

        if (!$homepageResponse->successful()) {
            $this->error("❌ Homepage failed: " . $homepageResponse->status());
            return Command::FAILURE;
        }

        $this->info("✅ Homepage loaded: " . strlen($homepageResponse->body()) . " bytes");

        // Extract potential session data
        $homepageHtml = $homepageResponse->body();
        
        // Look for CSRF tokens or session indicators
        $csrfToken = '';
        if (preg_match('/_token["\']?\s*[:=]\s*["\']([^"\']+)["\']/', $homepageHtml, $matches)) {
            $csrfToken = $matches[1];
            $this->line("🔑 Found CSRF token: " . substr($csrfToken, 0, 20) . "...");
        }

        // Step 2: Try to access browse/category page
        $this->newLine();
        $this->info('📍 Step 2: Accessing category/browse page');
        
        $browseUrl = $baseUrl . '/common/dokumen/ln';
        $browseResponse = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'Referer' => $baseUrl,
        ])
        ->timeout(30)
        ->get($browseUrl);

        if ($browseResponse->successful()) {
            $browseHtml = $browseResponse->body();
            $isLoginPage = stripos($browseHtml, 'sign in') !== false || stripos($browseHtml, 'login') !== false;
            
            if ($isLoginPage) {
                $this->warn("⚠️  Browse page also redirects to login");
            } else {
                $this->info("✅ Browse page accessible: " . strlen($browseHtml) . " bytes");
            }
        } else {
            $this->warn("⚠️  Browse page failed: " . $browseResponse->status());
        }

        // Step 3: Try document with referrer chain
        $this->newLine();
        $this->info('📍 Step 3: Accessing document with full referrer chain');
        
        sleep(2); // Small delay to appear more human
        
        $docResponse = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'same-origin',
            'Sec-Fetch-User' => '?1',
            'Referer' => $browseResponse->successful() ? $browseUrl : $baseUrl,
            'Cache-Control' => 'max-age=0',
        ])
        ->timeout(30)
        ->get($testDocUrl);

        if (!$docResponse->successful()) {
            $this->error("❌ Document request failed: " . $docResponse->status());
            return Command::FAILURE;
        }

        $docHtml = $docResponse->body();
        $this->info("✅ Document response: " . strlen($docHtml) . " bytes");

        // Check if we bypassed login
        $loginIndicators = ['sign in', 'username', 'password', 'login', 'masuk'];
        $loginFound = [];
        
        foreach ($loginIndicators as $indicator) {
            if (stripos($docHtml, $indicator) !== false) {
                $loginFound[] = $indicator;
            }
        }

        $this->newLine();
        if (count($loginFound) > 0) {
            $this->error("❌ Still hitting login page: " . implode(', ', $loginFound));
            $this->line("Session establishment didn't work - need browser automation");
        } else {
            $this->info("🎉 SUCCESS! Document is accessible without login!");
            
            // Try to extract title as proof
            $dom = new \DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML(mb_convert_encoding($docHtml, 'HTML-ENTITIES', 'UTF-8'));
            libxml_clear_errors();
            
            $xpath = new \DOMXPath($dom);
            $titleElement = $xpath->query('//title')->item(0);
            $title = $titleElement ? trim($titleElement->textContent) : 'No title';
            
            $this->line("📄 Document title: {$title}");
        }

        $this->newLine();
        $this->info("💡 RECOMMENDATION:");
        if (count($loginFound) > 0) {
            $this->line("  → Use browser automation: php artisan legal-docs:browser-scrape");
        } else {
            $this->line("  → Update your scraper to use this session flow");
        }

        return Command::SUCCESS;
    }
}