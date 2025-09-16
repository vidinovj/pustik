<?php

// app/Services/Scrapers/EnhancedDocumentScraper.php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnhancedDocumentScraper
{
    private array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
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
            'proxy_rotation' => false,
        ], $config);
    }

    public function scrapeWithStrategies(string $url, array $strategies = ['stealth', 'basic', 'mobile']): ?array
    {
        foreach ($strategies as $strategy) {
            Log::info("Trying strategy: {$strategy} for {$url}");

            $result = match ($strategy) {
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
        $scriptPath = storage_path('app/temp_stealth_'.uniqid().'.js');

        try {
            file_put_contents($scriptPath, $nodeScript);

            $command = "node {$scriptPath} 2>&1";
            $output = shell_exec($command);

            unlink($scriptPath);

            if (! $output || stripos($output, 'error') !== false) {
                return null;
            }

            $data = json_decode($output, true);

            return $this->extractDocumentInfo($data['html'] ?? '', $url);

        } catch (\Exception $e) {
            Log::error('Stealth scraping failed: '.$e->getMessage());
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }

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
            Log::error('Basic scraping failed: '.$e->getMessage());
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
            Log::error('Mobile scraping failed: '.$e->getMessage());
        }

        return null;
    }

    private function scrapeSelenium(string $url): ?array
    {
        // For sites with heavy JS - requires selenium setup
        $pythonScript = $this->generateSeleniumScript($url);
        $scriptPath = storage_path('app/temp_selenium_'.uniqid().'.py');

        try {
            file_put_contents($scriptPath, $pythonScript);

            $command = "python3 {$scriptPath} 2>&1";
            $output = shell_exec($command);

            unlink($scriptPath);

            if (! $output || stripos($output, 'error') !== false) {
                return null;
            }

            $data = json_decode($output, true);

            return $this->extractDocumentInfo($data['html'] ?? '', $url);

        } catch (\Exception $e) {
            Log::error('Selenium scraping failed: '.$e->getMessage());
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }

            return null;
        }
    }

    private function generateStealthScript(string $url): string
    {
        return "\nconst puppeteer = require('puppeteer-extra');\nconst StealthPlugin = require('puppeteer-extra-plugin-stealth');\n
puppeteer.use(StealthPlugin());\n
(async () => {\n    try {\n        const browser = await puppeteer.launch({\n            headless: true,\n            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });\n
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
        return "\nimport json\nimport sys\nfrom selenium import webdriver\nfrom selenium.webdriver.chrome.options import Options\nfrom selenium.webdriver.common.by import By\nfrom selenium.webdriver.support.ui import WebDriverWait\nfrom selenium.webdriver.support import expected_conditions as EC\nimport time\nimport random\n
try:\n    options = Options()
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
            'blocked',
        ];

        $htmlLower = strtolower($html);
        foreach ($blockingPatterns as $pattern) {
            if (stripos($htmlLower, $pattern) !== false) {
                Log::warning("Detected blocking pattern: {$pattern} in {$url}");

                return null;
            }
        }

        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        // Extract based on known patterns for Indonesian gov sites
        return $this->extractPeraturanData($xpath, $url, $html);
    }

    private function extractPeraturanData(\DOMXPath $xpath, string $url, string $html): ?array
    {
        // Enhanced PDF URL extraction patterns for BPK site
        $patterns = [
            'title' => [
                '//h1[contains(@class, "title")]',
                '//h1',
                '//div[@class="document-title"]',
                '//div[@class="title"]//text()',
                '//div[contains(@class, "document-title")]//text()',
                '//*[contains(@class, "judul")]//text()',
                '//title',
            ],
            'number' => [
                '//*[contains(text(), "Nomor")]//text()',
                '//*[contains(text(), "No.")]//text()',
                '//*[contains(@class, "nomor")]//text()',
            ],
            'date' => [
                '//*[contains(text(), "Tahun")]//text()',
                '//*[contains(@class, "tahun")]//text()',
                '//time/@datetime',
                '//*[contains(@class, "date")]//text()',
            ],
            'pdf_url' => [
                '//a[contains(@href, ".pdf")]/@href',
                '//a[contains(@href, "/pdf/")]/@href',
                '//a[contains(@href, "download")]/@href',
                '//a[img[contains(@alt, "PDF")]]/@href',
                '//a[img[contains(@src, "pdf")]]/@href',
                '//a[contains(text(), "PDF")]/@href',
                '//a[contains(text(), "Download")]/@href',
                '//a[contains(@class, "pdf")]/@href',
                '//a[contains(@class, "download")]/@href',
                '//*[@data-pdf-url]/@data-pdf-url',
                '//a[contains(@title, "PDF")]/@href',
                '//a[contains(@title, "Download")]/@href',
            ],
        ];

        $data = [];

        foreach ($patterns as $field => $xpaths) {
            foreach ($xpaths as $xpathQuery) {
                $nodes = $xpath->query($xpathQuery);
                if ($nodes->length > 0) {
                    $value = trim($nodes->item(0)->textContent ?? $nodes->item(0)->nodeValue ?? '');
                    if (! empty($value)) {
                        $data[$field] = $value;
                        if ($field === 'pdf_url') {
                            Log::info("PDF URL found via XPath: {$value} using pattern: {$xpathQuery}");
                        }
                        break;
                    }
                }
            }
        }

        // Enhanced fallback: Look for PDF links in raw HTML with multiple patterns
        if (empty($data['pdf_url'])) {
            $pdfPatterns = [
                '/<a[^>]*href=["\']([^"\']*.pdf[\'"]*)["\'][^>]*>/i',
                '/<a[^>]*href=["\']([^"\']*\/pdf\/[^"\']*)["\'][^>]*>/i',
                '/<a[^>]*href=["\']([^"\']*download[^"\']*.pdf[^"\']*)["\'][^>]*>/i',
            ];

            foreach ($pdfPatterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $data['pdf_url'] = $matches[1];
                    Log::info("PDF URL found via regex: {$data['pdf_url']} using pattern: {$pattern}");
                    break;
                }
            }
        }

        // Fallback: extract title from HTML if XPath failed
        if (empty($data['title'])) {
            if (preg_match('/<title[^>]*>(.+?)<\/title>/is', $html, $matches)) {
                $title = trim(strip_tags($matches[1]));
                // Filter out generic titles
                if (! empty($title) && ! in_array(strtolower($title), ['bpk', 'loading', 'error', 'untitled'])) {
                    $data['title'] = $title;
                }
            }
        }

        // Extract data from BPK URL structure
        $urlData = $this->extractFromBpkUrl($url);
        if ($urlData) {
            $data = array_merge($data, $urlData); // URL data as fallback, extracted data takes priority
        }

        // Validate we have minimum required data
        if (empty($data['title'])) {
            return null;
        }

        return [
            'title' => $data['title'] ?? 'Unknown Document',
            'document_number' => $data['document_number'] ?? null,
            'issue_year' => $data['issue_year'] ?? null,
            'document_type_code' => $data['document_type_code'] ?? null,
            'pdf_url' => $this->resolveUrl($data['pdf_url'] ?? '', $url),
            'source_url' => $url,
            'full_text' => $data['title'] ?? '', // Basic full_text, will be enhanced by filtering
            'extracted_at' => now(),
            'extraction_method' => 'enhanced_multi_strategy',
        ];
    }

    private function extractFromBpkUrl(string $url): array
    {
        $data = [];

        Log::info('=== URL PARSING DEBUG ===');
        Log::info("Input URL: {$url}");

        // Step 1: Extract the slug part
        if (preg_match('#/Details/(\d+)/([^/?]+)#i', $url, $urlMatches)) {
            $detailId = $urlMatches[1];
            $fullSlug = $urlMatches[2];

            Log::info("Detail ID: {$detailId}");
            Log::info("Full slug: '{$fullSlug}'");

            // Step 2: Multiple parsing strategies

            // Strategy 1: Standard pattern
            if (preg_match('/^(.+?)-no-(\d+)-tahun-(\d{4})$/i', $fullSlug, $matches)) {
                $typeSlug = $matches[1];
                $number = $matches[2];
                $year = $matches[3];

                Log::info("Strategy 1 success - Type slug: '{$typeSlug}', Number: {$number}, Year: {$year}");
            }
            // Strategy 2: Handle variations like "permenlu-no-9-tahun-2018"
            elseif (preg_match('/^([a-z]+(?:[a-z0-9]*)*)-no-(\d+)-tahun-(\d{4})$/i', $fullSlug, $matches)) {
                $typeSlug = $matches[1];
                $number = $matches[2];
                $year = $matches[3];

                Log::info("Strategy 2 success - Type slug: '{$typeSlug}', Number: {$number}, Year: {$year}");
            }
            // Strategy 3: Split and parse manually
            else {
                $parts = explode('-', $fullSlug);
                Log::info('Strategy 3 - Manual split: '.json_encode($parts));

                if (count($parts) >= 5) { // type-no-number-tahun-year
                    $typeSlug = $parts[0];
                    $number = $parts[2] ?? null;
                    $year = $parts[4] ?? null;

                    if ($parts[1] === 'no' && $parts[3] === 'tahun' && is_numeric($number) && is_numeric($year)) {
                        Log::info("Strategy 3 success - Type slug: '{$typeSlug}', Number: {$number}, Year: {$year}");
                    } else {
                        Log::warning('Strategy 3 failed - Invalid parts structure');

                        return $data;
                    }
                } else {
                    Log::warning('Strategy 3 failed - Not enough parts: '.count($parts));

                    return $data;
                }
            }

            // Now map the type slug to codes
            $typeMapping = [
                'uu' => 'uu',
                'pp' => 'pp',
                'perpres' => 'perpres',
                'keppres' => 'keppres',
                'inpres' => 'inpres',
                'perda' => 'perda',
            ];

            // All ministry variations map to 'permen'
            $ministryPrefixes = [
                'permen', 'permenkominfo', 'permenkomdigi', 'permenkumham', 'permendagri',
                'permenkes', 'permendikbud', 'permendikbudristek', 'permenkeu', 'permentan',
                'permenhub', 'permenlu', 'permenristekdikti', 'permenedag', 'permenperin',
                'permenkopukm', 'permenpopar', 'permenjamsos', 'permenaker', 'permentrans',
                'permenpan', 'permendesa', 'permenpera', 'permensos', 'permenlhk',
            ];

            if (in_array($typeSlug, $ministryPrefixes) || str_starts_with($typeSlug, 'permen')) {
                $typeMapping[$typeSlug] = 'permen';
            }

            $data['document_number'] = $number;
            $data['issue_year'] = (int) $year;
            $data['document_type_code'] = $typeMapping[$typeSlug] ?? null;

            // Derive full document type from code
            $typeNameMapping = [
                'uu' => 'Undang-undang',
                'pp' => 'Peraturan Pemerintah',
                'perpres' => 'Peraturan Presiden',
                'permen' => 'Peraturan Menteri',
                'keppres' => 'Keputusan Presiden',
                'kepmen' => 'Keputusan Menteri',
                'inpres' => 'Instruksi Presiden',
                'perda' => 'Peraturan Daerah',
            ];

            $data['document_type'] = $typeNameMapping[$data['document_type_code']] ?? 'Lainnya';

            Log::info("FINAL RESULT - Code: '{$data['document_type_code']}', Type: '{$data['document_type']}', Number: {$data['document_number']}, Year: {$data['issue_year']}");

            if (! $data['document_type_code']) {
                Log::warning("Type mapping failed for slug: '{$typeSlug}'");
            }

        } else {
            Log::warning("Could not match URL pattern for: {$url}");
        }

        Log::info('=== END URL PARSING DEBUG ===');

        return $data;
    }

    private function parseDate(string $dateText): ?int
    {
        if (preg_match('/(\d{4})/', $dateText, $matches)) {
            $year = (int) $matches[1];
            // Sanity check for reasonable year range
            if ($year >= 1945 && $year <= date('Y') + 1) {
                return $year;
            }
        }

        return null;
    }

    private function resolveUrl(string $relativeUrl, string $baseUrl): ?string
    {
        if (empty($relativeUrl)) {
            return null;
        }

        if (filter_var($relativeUrl, FILTER_VALIDATE_URL)) {
            return $relativeUrl;
        }

        $parsedBase = parse_url($baseUrl);
        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';

        if (strpos($relativeUrl, '/') === 0) {
            return "{$scheme}://{$host}{$relativeUrl}";
        }

        return "{$scheme}://{$host}/".ltrim($relativeUrl, '/');
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
