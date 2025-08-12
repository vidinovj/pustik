<?php
// app/Services/Scrapers/EnhancedDocumentScraper.php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class EnhancedDocumentScraper
{
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ];

    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'delay_min' => 3,
            'delay_max' => 8,
            'timeout' => 45,
            'retries' => 3,
            'use_proxy' => false,
            'proxy_rotation' => false
        ], $config);
    }

    public function scrapeWithStrategies(string $url, array $strategies = ['stealth', 'basic', 'mobile']): ?array
    {
        foreach ($strategies as $strategy) {
            Log::info("Trying strategy: {$strategy} for {$url}");
            
            $result = match($strategy) {
                'stealth' => $this->scrapeStealth($url),
                'basic' => $this->scrapeBasic($url),
                'mobile' => $this->scrapeMobile($url),
                'selenium' => $this->scrapeSelenium($url),
                default => null
            };

            if ($result) {
                Log::info("Success with strategy: {$strategy}");
                return $result;
            }

            $this->randomDelay();
        }

        return null;
    }

    private function scrapeStealth(string $url): ?array
    {
        $nodeScript = $this->generateStealthScript($url);
        $scriptPath = storage_path('app/temp_stealth_' . uniqid() . '.js');
        
        try {
            file_put_contents($scriptPath, $nodeScript);
            
            $command = "node {$scriptPath} 2>&1";
            $output = shell_exec($command);
            
            unlink($scriptPath);
            
            if (!$output || stripos($output, 'error') !== false) {
                return null;
            }

            $data = json_decode($output, true);
            return $this->extractDocumentInfo($data['html'] ?? '', $url);
            
        } catch (\Exception $e) {
            Log::error("Stealth scraping failed: " . $e->getMessage());
            if (file_exists($scriptPath)) unlink($scriptPath);
            return null;
        }
    }

    private function scrapeBasic(string $url): ?array
    {
        try {
            $response = Http::withHeaders($this->getRandomHeaders())
                ->timeout($this->config['timeout'])
                ->get($url);

            if ($response->successful()) {
                return $this->extractDocumentInfo($response->body(), $url);
            }
            
        } catch (\Exception $e) {
            Log::error("Basic scraping failed: " . $e->getMessage());
        }
        
        return null;
    }

    private function scrapeMobile(string $url): ?array
    {
        try {
            $headers = $this->getMobileHeaders();
            
            $response = Http::withHeaders($headers)
                ->timeout($this->config['timeout'])
                ->get($url);

            if ($response->successful()) {
                return $this->extractDocumentInfo($response->body(), $url);
            }
            
        } catch (\Exception $e) {
            Log::error("Mobile scraping failed: " . $e->getMessage());
        }
        
        return null;
    }

    private function scrapeSelenium(string $url): ?array
    {
        // For sites with heavy JS - requires selenium setup
        $pythonScript = $this->generateSeleniumScript($url);
        $scriptPath = storage_path('app/temp_selenium_' . uniqid() . '.py');
        
        try {
            file_put_contents($scriptPath, $pythonScript);
            
            $command = "python3 {$scriptPath} 2>&1";
            $output = shell_exec($command);
            
            unlink($scriptPath);
            
            if (!$output || stripos($output, 'error') !== false) {
                return null;
            }

            $data = json_decode($output, true);
            return $this->extractDocumentInfo($data['html'] ?? '', $url);
            
        } catch (\Exception $e) {
            Log::error("Selenium scraping failed: " . $e->getMessage());
            if (file_exists($scriptPath)) unlink($scriptPath);
            return null;
        }
    }

    private function generateStealthScript(string $url): string
    {
        return "
const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');

puppeteer.use(StealthPlugin());

(async () => {
    try {
        const browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });

        const page = await browser.newPage();
        
        // Mimic real user behavior
        await page.setViewport({width: 1366, height: 768});
        await page.setUserAgent('{$this->getRandomUserAgent()}');
        
        // Set realistic headers
        await page.setExtraHTTPHeaders({
            'Accept-Language': 'en-US,en;q=0.9,id;q=0.8',
            'Accept-Encoding': 'gzip, deflate, br',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1'
        });

        // Random delay before navigation
        await page.evaluateOnNewDocument(() => {
            Object.defineProperty(navigator, 'webdriver', {
                get: () => undefined,
            });
        });

        await new Promise(resolve => setTimeout(resolve, Math.random() * 3000 + 2000));
        
        const response = await page.goto('{$url}', {
            waitUntil: 'networkidle2',
            timeout: 45000
        });

        if (!response.ok()) {
            throw new Error('Response not ok: ' + response.status());
        }

        // Wait for content to load
        await page.waitForTimeout(Math.random() * 3000 + 2000);
        
        // Scroll to simulate reading
        await page.evaluate(() => {
            return new Promise(resolve => {
                let totalHeight = 0;
                const distance = 100;
                const timer = setInterval(() => {
                    const scrollHeight = document.body.scrollHeight;
                    window.scrollBy(0, distance);
                    totalHeight += distance;

                    if(totalHeight >= scrollHeight){
                        clearInterval(timer);
                        resolve();
                    }
                }, 100);
            });
        });

        const html = await page.content();
        const title = await page.title();
        
        console.log(JSON.stringify({
            html: html,
            title: title,
            url: page.url(),
            status: response.status()
        }));

        await browser.close();
    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    }
})();
";
    }

    private function generateSeleniumScript(string $url): string
    {
        return "
import json
import sys
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time
import random

try:
    options = Options()
    options.add_argument('--headless')
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-gpu')
    options.add_argument('--user-agent={$this->getRandomUserAgent()}')
    
    driver = webdriver.Chrome(options=options)
    driver.set_page_load_timeout(45)
    
    driver.get('{$url}')
    
    # Random wait
    time.sleep(random.uniform(2, 5))
    
    # Scroll down slowly
    driver.execute_script('window.scrollTo(0, document.body.scrollHeight/2);')
    time.sleep(random.uniform(1, 3))
    driver.execute_script('window.scrollTo(0, document.body.scrollHeight);')
    time.sleep(random.uniform(1, 2))
    
    html = driver.page_source
    title = driver.title
    
    result = {
        'html': html,
        'title': title,
        'url': driver.current_url
    }
    
    print(json.dumps(result))
    driver.quit()
    
except Exception as e:
    print(f'Error: {str(e)}', file=sys.stderr)
    sys.exit(1)
";
    }

    private function extractDocumentInfo(string $html, string $url): ?array
    {
        if (empty($html) || strlen($html) < 100) {
            return null;
        }

        // Check for common blocking patterns
        $blockingPatterns = [
            'cloudflare',
            'just a moment',
            'checking your browser',
            'security check',
            'captcha',
            'blocked'
        ];

        $htmlLower = strtolower($html);
        foreach ($blockingPatterns as $pattern) {
            if (stripos($htmlLower, $pattern) !== false) {
                Log::warning("Detected blocking pattern: {$pattern} in {$url}");
                return null;
            }
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        // Extract based on known patterns for Indonesian gov sites
        return $this->extractPeraturanData($xpath, $url, $html);
    }

    private function extractPeraturanData(\DOMXPath $xpath, string $url, string $html): ?array
    {
        $patterns = [
            'title' => [
                '//h1',
                '//title',
                '//div[@class="title"]//text()',
                '//div[contains(@class, "document-title")]//text()',
                '//*[contains(@class, "judul")]//text()'
            ],
            'number' => [
                '//*[contains(text(), "Nomor")]//text()',
                '//*[contains(text(), "No.")]//text()',
                '//*[contains(@class, "nomor")]//text()'
            ],
            'date' => [
                '//*[contains(text(), "Tahun")]//text()',
                '//*[contains(@class, "tahun")]//text()',
                '//time/@datetime',
                '//*[contains(@class, "date")]//text()'
            ],
            'pdf_url' => [
                '//a[contains(@href, ".pdf")]/@href',
                '//a[img[contains(@src, "pdf")]]/@href',
                '//a[contains(text(), "PDF")]/@href',
                '//a[contains(text(), "Download")]/@href'
            ]
        ];

        $data = [];
        
        foreach ($patterns as $field => $xpaths) {
            foreach ($xpaths as $xpathQuery) {
                $nodes = $xpath->query($xpathQuery);
                if ($nodes->length > 0) {
                    $value = trim($nodes->item(0)->textContent ?? $nodes->item(0)->nodeValue ?? '');
                    if (!empty($value)) {
                        $data[$field] = $value;
                        break;
                    }
                }
            }
        }

        // Fallback: extract from raw HTML using regex
        if (empty($data['title'])) {
            if (preg_match('/<title[^>]*>(.+?)<\/title>/is', $html, $matches)) {
                $data['title'] = trim(strip_tags($matches[1]));
            }
        }

        // Validate we have minimum required data
        if (empty($data['title']) && !isset($data['number'])) {
            return null;
        }

        return [
            'title' => $data['title'] ?? 'Unknown Document',
            'document_number' => $data['number'] ?? null,
            'issue_date' => $this->parseDate($data['date'] ?? ''),
            'pdf_url' => $this->resolveUrl($data['pdf_url'] ?? '', $url),
            'source_url' => $url,
            'extracted_at' => now(),
            'extraction_method' => 'enhanced_multi_strategy'
        ];
    }

    private function parseDate(string $dateText): ?string
    {
        if (preg_match('/(\d{4})/', $dateText, $matches)) {
            return $matches[1] . '-01-01';
        }
        return null;
    }

    private function resolveUrl(string $relativeUrl, string $baseUrl): ?string
    {
        if (empty($relativeUrl)) return null;
        
        if (filter_var($relativeUrl, FILTER_VALIDATE_URL)) {
            return $relativeUrl;
        }
        
        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';
        
        if (strpos($relativeUrl, '/') === 0) {
            return "{$scheme}://{$host}{$relativeUrl}";
        }
        
        return "{$scheme}://{$host}/" . ltrim($relativeUrl, '/');
    }

    private function getRandomHeaders(): array
    {
        return [
            'User-Agent' => $this->getRandomUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    private function getMobileHeaders(): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Accept-Encoding' => 'gzip, deflate',
            'Connection' => 'keep-alive',
        ];
    }

    private function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    private function randomDelay(): void
    {
        $delay = rand($this->config['delay_min'], $this->config['delay_max']);
        sleep($delay);
    }
}