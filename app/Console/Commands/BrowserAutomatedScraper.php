<?php
// app/Console/Commands/BrowserAutomatedScraper.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

class BrowserAutomatedScraper extends Command
{
    protected $signature = 'legal-docs:browser-scrape {--headless=true} {--limit=5}';
    protected $description = 'Use browser automation to scrape peraturan.go.id';

    public function handle(): int
    {
        $this->info('🌐 Browser Automated Scraper for Peraturan.go.id');
        $this->newLine();

        if (!$this->checkDependencies()) {
            return Command::FAILURE;
        }

        $headless = $this->option('headless') === 'true';
        $limit = (int) $this->option('limit');

        $this->info("Configuration:");
        $this->line("  • Headless mode: " . ($headless ? 'Yes' : 'No'));
        $this->line("  • Document limit: {$limit}");
        $this->newLine();

        // Create browser automation script
        $scriptPath = $this->createAutomationScript($headless, $limit);
        
        try {
            $this->info("🚀 Launching browser automation...");
            
            // Run the Node.js script
            $process = new Process(['node', $scriptPath]);
            $process->setTimeout(300); // 5 minutes
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                $this->info("✅ Browser automation completed!");
                $this->newLine();
                
                // Parse the output
                $this->parseAutomationOutput($output);
                
            } else {
                $this->error("❌ Browser automation failed:");
                $this->line($process->getErrorOutput());
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            // Clean up
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }

        return Command::SUCCESS;
    }

    protected function checkDependencies(): bool
    {
        $this->info("🔍 Checking dependencies...");

        // Check Node.js
        $nodeProcess = new Process(['node', '--version']);
        $nodeProcess->run();
        
        if (!$nodeProcess->isSuccessful()) {
            $this->error("❌ Node.js not found. Please install Node.js first.");
            $this->line("   Install from: https://nodejs.org/");
            return false;
        }
        
        $nodeVersion = trim($nodeProcess->getOutput());
        $this->line("  ✅ Node.js: {$nodeVersion}");

        // Check if puppeteer is available
        $puppeteerCheck = new Process(['npm', 'list', 'puppeteer', '--depth=0']);
        $puppeteerCheck->run();
        
        if (!$puppeteerCheck->isSuccessful()) {
            $this->warn("⚠️  Puppeteer not found. Installing...");
            $this->installPuppeteer();
        } else {
            $this->line("  ✅ Puppeteer: Available");
        }

        return true;
    }

    protected function installPuppeteer(): void
    {
        $this->info("📦 Installing Puppeteer...");
        
        $installProcess = new Process(['npm', 'install', 'puppeteer'], null, null, null, 120);
        $installProcess->run();
        
        if ($installProcess->isSuccessful()) {
            $this->info("  ✅ Puppeteer installed successfully");
        } else {
            $this->error("  ❌ Failed to install Puppeteer");
            $this->line("     Please run: npm install puppeteer");
        }
    }

    protected function createAutomationScript(bool $headless, int $limit): string
    {
        $scriptContent = $this->generateNodeScript($headless, $limit);
        $scriptPath = storage_path('app/temp_browser_scraper.js');
        
        file_put_contents($scriptPath, $scriptContent);
        
        return $scriptPath;
    }

    protected function generateNodeScript(bool $headless, int $limit): string
    {
        $headlessStr = $headless ? 'true' : 'false';
        
        return <<<JS
const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const browser = await puppeteer.launch({
        headless: {$headlessStr},
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const page = await browser.newPage();
    
    // Set realistic browser properties
    await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    await page.setViewport({ width: 1366, height: 768 });
    
    // Set extra headers
    await page.setExtraHTTPHeaders({
        'Accept-Language': 'id-ID,id;q=0.9,en;q=0.8',
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8'
    });

    const results = [];
    const baseUrl = 'https://peraturan.go.id';
    
    try {
        console.log('🌐 Navigating to homepage...');
        await page.goto(baseUrl, { waitUntil: 'networkidle2', timeout: 30000 });
        
        // Wait a bit for any JS to load
        await page.waitForTimeout(3000);
        
        console.log('✅ Homepage loaded successfully');
        
        // Try to find and access document sections
        console.log('🔍 Looking for document links...');
        
        // Test specific document URLs
        const testUrls = [
            'https://peraturan.go.id/id/permenpar-no-4-tahun-2025',
            'https://peraturan.go.id/id/permenkes-no-1-tahun-2025',
            'https://peraturan.go.id/id/permenpanrb-no-10-tahun-2025'
        ];
        
        for (let i = 0; i < Math.min(testUrls.length, {$limit}); i++) {
            const url = testUrls[i];
            console.log(\`📄 Testing document: \${url}\`);
            
            try {
                await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });
                await page.waitForTimeout(2000);
                
                // Check if we're on a login page
                const title = await page.title();
                const bodyText = await page.evaluate(() => document.body.innerText.toLowerCase());
                
                const isLoginPage = title.toLowerCase().includes('login') || 
                                   bodyText.includes('username') || 
                                   bodyText.includes('password') ||
                                   bodyText.includes('sign in');
                
                if (isLoginPage) {
                    console.log(\`❌ Document redirected to login: \${url}\`);
                    results.push({
                        url: url,
                        status: 'login_required',
                        title: title
                    });
                } else {
                    // Try to extract document data
                    const documentData = await page.evaluate(() => {
                        // Look for document title
                        const titleSelectors = ['h1', 'h2', '.title', '.document-title'];
                        let documentTitle = '';
                        
                        for (const selector of titleSelectors) {
                            const element = document.querySelector(selector);
                            if (element && element.textContent.trim().length > 15) {
                                documentTitle = element.textContent.trim();
                                break;
                            }
                        }
                        
                        // Look for document content
                        const contentSelectors = ['.content', 'main', 'article', '.document-body'];
                        let content = '';
                        
                        for (const selector of contentSelectors) {
                            const element = document.querySelector(selector);
                            if (element && element.textContent.trim().length > 100) {
                                content = element.textContent.trim().substring(0, 500);
                                break;
                            }
                        }
                        
                        return {
                            title: documentTitle || document.title,
                            content: content,
                            hasContent: content.length > 100
                        };
                    });
                    
                    console.log(\`✅ Document accessible: \${documentData.title}\`);
                    results.push({
                        url: url,
                        status: 'accessible',
                        title: documentData.title,
                        hasContent: documentData.hasContent,
                        content: documentData.content
                    });
                }
                
                // Random delay between requests
                await page.waitForTimeout(Math.random() * 3000 + 2000);
                
            } catch (error) {
                console.log(\`❌ Error accessing \${url}: \${error.message}\`);
                results.push({
                    url: url,
                    status: 'error',
                    error: error.message
                });
            }
        }
        
    } catch (error) {
        console.error('❌ Browser automation error:', error);
    } finally {
        await browser.close();
    }
    
    // Output results as JSON
    console.log('\\n--- AUTOMATION RESULTS ---');
    console.log(JSON.stringify(results, null, 2));
})();
JS;
    }

    protected function parseAutomationOutput(string $output): void
    {
        // Look for the JSON results
        if (preg_match('/--- AUTOMATION RESULTS ---\s*(.+)$/s', $output, $matches)) {
            $jsonData = trim($matches[1]);
            
            try {
                $results = json_decode($jsonData, true);
                
                if ($results) {
                    $this->displayResults($results);
                } else {
                    $this->warn("Could not parse automation results");
                    $this->line($output);
                }
                
            } catch (\Exception $e) {
                $this->warn("Error parsing results: " . $e->getMessage());
                $this->line($output);
            }
        } else {
            $this->line($output);
        }
    }

    protected function displayResults(array $results): void
    {
        $this->info("📊 AUTOMATION RESULTS:");
        $this->newLine();
        
        $accessible = 0;
        $loginRequired = 0;
        $errors = 0;
        
        foreach ($results as $result) {
            $status = $result['status'];
            $url = basename($result['url']);
            
            switch ($status) {
                case 'accessible':
                    $this->line("  ✅ {$url}: ACCESSIBLE");
                    $this->line("     Title: " . substr($result['title'], 0, 60) . "...");
                    $this->line("     Has Content: " . ($result['hasContent'] ? 'Yes' : 'No'));
                    $accessible++;
                    break;
                    
                case 'login_required':
                    $this->line("  🔒 {$url}: LOGIN REQUIRED");
                    $this->line("     Page Title: " . $result['title']);
                    $loginRequired++;
                    break;
                    
                case 'error':
                    $this->line("  ❌ {$url}: ERROR");
                    $this->line("     Error: " . $result['error']);
                    $errors++;
                    break;
            }
            $this->newLine();
        }
        
        $this->info("📈 SUMMARY:");
        $this->line("  • Accessible: {$accessible}");
        $this->line("  • Login Required: {$loginRequired}");
        $this->line("  • Errors: {$errors}");
        
        if ($accessible > 0) {
            $this->info("🎉 Great! Some documents are accessible. Update your scraper to use browser automation.");
        } elseif ($loginRequired > 0) {
            $this->warn("⚠️  All documents require login. Consider:");
            $this->line("     1. Implementing authentication flow");
            $this->line("     2. Using alternative data sources");
            $this->line("     3. Contacting site administrators for API access");
        }
    }
}