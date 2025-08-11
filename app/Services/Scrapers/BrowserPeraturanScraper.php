<?php
// app/Services/Scrapers/BrowserPeraturanScraper.php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Storage;

class BrowserPeraturanScraper extends BaseScraper
{
    protected array $tikKeywords = [
        'teknologi informasi', 'informatika', 'telekomunikasi', 'digital',
        'elektronik', 'cyber', 'internet', 'data', 'sistem informasi',
        'tik', 'ict', 'komputer', 'software', 'jaringan'
    ];

    public function scrape(): array
    {
        return $this->scrapeWithLimit(50);
    }

    public function scrapeWithLimit(int $limit): array
    {
        Log::channel('legal-documents')->info("Browser Peraturan Scraper: Starting with limit {$limit}");
        
        // Create Node.js script for browser automation
        $scriptPath = $this->createBrowserScript($limit);
        
        try {
            // Run browser automation
            $results = $this->runBrowserScript($scriptPath);
            
            // Process and save documents
            $documents = $this->processScrapedData($results);
            
            Log::channel('legal-documents')->info("Browser Peraturan Scraper: Processed " . count($documents) . " documents");
            
            return $documents;
            
        } finally {
            // Clean up
            if (file_exists($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    protected function createBrowserScript(int $limit): string
    {
        $scriptContent = <<<'EOD'
const puppeteer = require('puppeteer');

(async () => {
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
    });

    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    const results = [];
    const limit = LIMIT_PLACEHOLDER;
    
    try {
        console.log('🌐 Browser automation started');
        
        // Strategy: Try different document discovery methods
        const discoveryMethods = [
            'recentDocuments',
            'categoryBrowse',
            'searchByYear'
        ];
        
        for (const method of discoveryMethods) {
            console.log('🔍 Trying discovery method: ' + method);
            
            const urls = await discoverDocumentUrls(page, method, limit);
            console.log('📄 Found ' + urls.length + ' document URLs');
            
            for (const url of urls.slice(0, limit)) {
                try {
                    const docData = await scrapeDocument(page, url);
                    if (docData) {
                        results.push(docData);
                        console.log('✅ Scraped: ' + docData.title.substring(0, 50) + '...');
                    }
                    
                    // Respectful delay
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    
                } catch (error) {
                    console.log('❌ Error scraping ' + url + ': ' + error.message);
                }
                
                if (results.length >= limit) break;
            }
            
            if (results.length >= limit) break;
        }
        
    } catch (error) {
        console.error('❌ Browser automation error:', error);
    } finally {
        await browser.close();
    }
    
    // Output results as JSON
    console.log('--- RESULTS START ---');
    console.log(JSON.stringify(results, null, 2));
    console.log('--- RESULTS END ---');
})();

async function discoverDocumentUrls(page, method, limit) {
    const baseUrl = 'https://peraturan.go.id';
    
    switch (method) {
        case 'recentDocuments':
            return await discoverRecentDocuments(page, baseUrl, limit);
        case 'categoryBrowse':
            return await discoverFromCategories(page, baseUrl, limit);
        case 'searchByYear':
            return await discoverByYearSearch(page, baseUrl, limit);
        default:
            return [];
    }
}

async function discoverRecentDocuments(page, baseUrl, limit) {
    try {
        await page.goto(baseUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await new Promise(resolve => setTimeout(resolve, 3000));
        
        // Look for recent document links on homepage
        const urls = await page.evaluate(() => {
            const links = Array.from(document.querySelectorAll('a[href*="/id/"]'));
            return links
                .map(link => link.href)
                .filter(href => href.includes('peraturan.go.id/id/'))
                .slice(0, 20);
        });
        
        return urls;
    } catch (error) {
        console.log('Recent documents discovery failed: ' + error.message);
        return [];
    }
}

async function discoverFromCategories(page, baseUrl, limit) {
    const categoryUrls = [
        baseUrl + '/ln/2025',
        baseUrl + '/ln/2024',
        baseUrl + '/common/dokumen'
    ];
    
    const allUrls = [];
    
    for (const categoryUrl of categoryUrls) {
        try {
            await page.goto(categoryUrl, { waitUntil: 'domcontentloaded', timeout: 15000 });
            await new Promise(resolve => setTimeout(resolve, 2000));
            
            const urls = await page.evaluate(() => {
                const links = Array.from(document.querySelectorAll('a[href*="/id/"]'));
                return links
                    .map(link => link.href)
                    .filter(href => href.includes('peraturan.go.id/id/'));
            });
            
            allUrls.push(...urls);
            
        } catch (error) {
            console.log('Category browse failed for ' + categoryUrl + ': ' + error.message);
        }
        
        if (allUrls.length >= limit) break;
    }
    
    return [...new Set(allUrls)]; // Remove duplicates
}

async function discoverByYearSearch(page, baseUrl, limit) {
    // Generate test URLs based on common patterns
    const testUrls = [];
    const year = 2025;
    const types = ['uu', 'pp', 'perpres', 'permen', 'permenpar', 'permenkes', 'permenkeu'];
    
    for (const type of types) {
        for (let num = 1; num <= 10; num++) {
            testUrls.push(baseUrl + '/id/' + type + '-no-' + num + '-tahun-' + year);
        }
    }
    
    return testUrls.slice(0, limit);
}

async function scrapeDocument(page, url) {
    try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Check if login page
        const isLoginPage = await page.evaluate(() => {
            const bodyText = document.body.innerText.toLowerCase();
            const title = document.title.toLowerCase();
            return bodyText.includes('sign in') || bodyText.includes('login') || 
                   title.includes('login');
        });
        
        if (isLoginPage) {
            return null;
        }
        
        // Extract document metadata
        const docData = await page.evaluate((sourceUrl) => {
            // Extract title
            const titleElement = document.querySelector('title');
            let title = titleElement ? titleElement.textContent.trim() : '';
            
            // Clean title (remove site name suffixes)
            title = title.replace(/\s*-\s*Peraturan\.go\.id.*$/i, '').trim();
            
            if (!title || title.length < 15) return null;
            
            // Extract metadata from page content
            const extractMetadata = () => {
                const metadata = {};
                
                // Look for structured data in the page
                const detailElements = document.querySelectorAll('*');
                
                for (const element of detailElements) {
                    const text = element.textContent || '';
                    
                    // Extract document type
                    if (text.includes('Jenis/Bentuk Peraturan') || text.includes('PERATURAN')) {
                        const typeMatch = text.match(/(PERATURAN\s+\w+|UNDANG-UNDANG|KEPUTUSAN\s+\w+)/i);
                        if (typeMatch) metadata.document_type = typeMatch[1];
                    }
                    
                    // Extract agency
                    if (text.includes('Pemrakarsa') || text.includes('KEMENTERIAN')) {
                        const agencyMatch = text.match(/KEMENTERIAN\s+[\w\s]+/i);
                        if (agencyMatch) metadata.agency = agencyMatch[0];
                    }
                    
                    // Extract number
                    if (text.includes('Nomor')) {
                        const numberMatch = text.match(/Nomor\s+(\d+)/i);
                        if (numberMatch) metadata.number = numberMatch[1];
                    }
                    
                    // Extract year
                    if (text.includes('Tahun')) {
                        const yearMatch = text.match(/Tahun\s+(\d{4})/i);
                        if (yearMatch) metadata.year = yearMatch[1];
                    }
                    
                    // Extract date
                    if (text.includes('Ditetapkan Tanggal')) {
                        const dateMatch = text.match(/Ditetapkan Tanggal\s+([^\\n]+)/i);
                        if (dateMatch) metadata.issue_date = dateMatch[1].trim();
                    }
                    
                    // Extract status
                    if (text.includes('Status')) {
                        const statusMatch = text.match(/Status\s+(\w+)/i);
                        if (statusMatch) metadata.status = statusMatch[1];
                    }
                    
                    // Extract subject/about
                    if (text.includes('Tentang')) {
                        const subjectMatch = text.match(/Tentang\s+([^\\n]+)/i);
                        if (subjectMatch) metadata.subject = subjectMatch[1].trim();
                    }
                }
                
                return metadata;
            };
            
            const metadata = extractMetadata();
            
            // Get document number from URL if not found
            if (!metadata.number) {
                const urlMatch = sourceUrl.match(/no-(\d+)-tahun/);
                if (urlMatch) metadata.number = urlMatch[1];
            }
            
            // Get year from URL if not found
            if (!metadata.year) {
                const urlMatch = sourceUrl.match(/tahun-(\d{4})/);
                if (urlMatch) metadata.year = urlMatch[1];
            }
            
            // Generate document number
            const docNumber = (metadata.number && metadata.year) ? 
                metadata.number + '/' + metadata.year : '';
            
            return {
                title: title,
                document_type: metadata.document_type || 'Peraturan',
                document_number: docNumber,
                issue_date: metadata.issue_date || null,
                source_url: sourceUrl,
                metadata: {
                    agency: metadata.agency || '',
                    subject: metadata.subject || '',
                    status: metadata.status || '',
                    source_site: 'Peraturan.go.id',
                    extraction_method: 'browser_automation',
                    scraped_at: new Date().toISOString()
                },
                full_text: title // For now, use title as searchable text
            };
        }, url);
        
        return docData;
        
    } catch (error) {
        console.log('Error scraping document ' + url + ': ' + error.message);
        return null;
    }
}
EOD;

        // Replace placeholder with actual limit
        $scriptContent = str_replace('LIMIT_PLACEHOLDER', $limit, $scriptContent);
        
        $scriptPath = storage_path('app/peraturan_browser_scraper.cjs');
        file_put_contents($scriptPath, $scriptContent);
        
        return $scriptPath;
    }

    protected function runBrowserScript(string $scriptPath): array
    {
        $process = new Process(['node', $scriptPath]);
        $process->setTimeout(300); // 5 minutes timeout
        $process->run();
        
        if (!$process->isSuccessful()) {
            throw new \Exception("Browser script failed: " . $process->getErrorOutput());
        }
        
        $output = $process->getOutput();
        
        // Extract JSON results
        if (preg_match('/--- RESULTS START ---\s*(.+?)\s*--- RESULTS END ---/s', $output, $matches)) {
            $jsonData = trim($matches[1]);
            $results = json_decode($jsonData, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON in browser script output: " . json_last_error_msg());
            }
            
            return $results ?: [];
        }
        
        Log::channel('legal-documents-errors')->warning("No results section found in browser output: " . $output);
        return [];
    }

    protected function processScrapedData(array $results): array
    {
        $documents = [];
        
        foreach ($results as $data) {
            if (!$data || !isset($data['title'])) {
                continue;
            }
            
            try {
                $document = $this->saveDocument($data);
                if ($document) {
                    $documents[] = $document;
                }
            } catch (\Exception $e) {
                Log::channel('legal-documents-errors')->error("Failed to save browser-scraped document: " . $e->getMessage(), $data);
            }
        }
        
        return $documents;
    }

    // Required by BaseScraper
    protected function extractDocumentData(\DOMDocument $dom, string $url): ?array
    {
        // Not used in browser scraper
        return null;
    }

    protected function getDocumentUrls(): array
    {
        // Not used in browser scraper
        return [];
    }
}