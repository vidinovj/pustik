<?php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use App\Models\LegalDocument;
use App\Models\ApiLog;
use App\Models\UrlMonitoring;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;

abstract class BaseScraper
{
    protected DocumentSource $source;
    protected array $config;
    protected int $requestDelay = 2; // seconds between requests
    protected int $timeout = 30;
    protected array $headers = [];

    public function __construct(DocumentSource $source)
    {
        $this->source = $source;
        $this->config = $source->config ?? [];
        $this->setupDefaultHeaders();
        $this->applyConfig();
    }

    /**
     * Main scraping method - implement in child classes.
     */
    abstract public function scrape(): array;

    /**
     * Extract document metadata from HTML - implement in child classes.
     */
    abstract protected function extractDocumentData(DOMDocument $dom, string $url): ?array;

    /**
     * Get list of document URLs to scrape - implement in child classes.
     */
    abstract protected function getDocumentUrls(): array;

    /**
     * Make HTTP request with proper error handling.
     */
    protected function makeRequest(string $url): ?string
    {
        $startTime = microtime(true);
        
        try {
            Log::channel('legal-documents')->info("Scraping URL: {$url}");
            
            // Check rate limiting
            $this->respectRateLimit();
            
            // Make request
            // Path to the Puppeteer script
            $puppeteerScriptPath = base_path('storage/app/puppeteer_scraper.cjs');

            // Build the command to execute the Puppeteer script
            $command = "node {$puppeteerScriptPath} " . escapeshellarg($url);

            // Execute the command
            // Using shell_exec directly here, as Http::legalDocsScraper() is for Guzzle
            $processResult = shell_exec($command . ' 2>&1'); // Capture both stdout and stderr

            $responseTime = (int)((microtime(true) - $startTime) * 1000);

            // Check for errors from the Puppeteer script
            if (str_contains($processResult, 'Error scraping')) {
                ApiLog::logRequest(
                    $this->source->id,
                    $url,
                    'GET',
                    500, // Indicate internal server error from Puppeteer
                    $responseTime,
                    $processResult // Log the error message from Puppeteer
                );
                $monitoring = UrlMonitoring::monitor($url);
                $monitoring->markAsBroken(500, $processResult);
                Log::channel('legal-documents-errors')->error("Puppeteer scraping failed for {$url}: {$processResult}");
                return null;
            }

            // Assuming successful execution, the HTML content is in $processResult
            $html = $processResult;
            $statusCode = 200; // Assume 200 OK if Puppeteer returned content without error

            // Log the request
            ApiLog::logRequest(
                $this->source->id,
                $url,
                'GET',
                $statusCode,
                $responseTime,
                null // No error message from Puppeteer
            );

            // Update URL monitoring
            $monitoring = UrlMonitoring::monitor($url);
            $monitoring->markAsWorking($statusCode);
            
            return $html;

        } catch (\Exception $e) {
            $responseTime = (int)((microtime(true) - $startTime) * 1000);
            
            // Log the error
            ApiLog::logRequest(
                $this->source->id,
                $url,
                'GET',
                0,
                $responseTime,
                $e->getMessage()
            );

            // Update URL monitoring
            $monitoring = UrlMonitoring::monitor($url);
            $monitoring->markAsBroken(0, $e->getMessage());

            Log::channel('legal-documents-errors')->error("Exception scraping {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Parse HTML content into DOMDocument.
     */
    protected function parseHtml(string $html): ?DOMDocument
    {
        $dom = new DOMDocument();
        
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        
        // Load HTML with UTF-8 encoding
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        
        // Clear libxml errors
        libxml_clear_errors();
        
        return $dom;
    }

    /**
     * Create XPath object for DOM queries.
     */
    protected function createXPath(DOMDocument $dom): DOMXPath
    {
        return new DOMXPath($dom);
    }

    /**
     * Extract text content from DOM element.
     */
    protected function extractText($element): string
    {
        if (!$element) {
            return '';
        }
        
        return trim($element->textContent ?? '');
    }

    /**
     * Extract href from link element.
     */
    protected function extractHref($element, string $baseUrl = ''): string
    {
        if (!$element) {
            return '';
        }
        
        $href = $element->getAttribute('href');
        
        // Convert relative URLs to absolute
        if ($href && !filter_var($href, FILTER_VALIDATE_URL)) {
            $href = rtrim($baseUrl ?: $this->source->base_url, '/') . '/' . ltrim($href, '/');
        }
        
        return $href;
    }

    /**
     * Save document to database.
     */
    protected function saveDocument(array $documentData): ?LegalDocument
    {
        try {
            // Generate checksum for duplicate detection
            $checksum = md5(
                ($documentData['title'] ?? '') . 
                ($documentData['document_number'] ?? '') . 
                ($documentData['issue_year'] ?? '')
            );

            // Check for duplicates
            $existing = LegalDocument::where('checksum', $checksum)->first();
            if ($existing) {
                Log::channel('legal-documents')->info("Duplicate document skipped: {$documentData['title']}");
                return $existing;
            }

            // Create new document
            $document = LegalDocument::create([
                'title' => $documentData['title'] ?? '',
                'document_type' => $documentData['document_type'] ?? 'Unknown',
                'document_number' => $documentData['document_number'] ?? '',
                'issue_year' => $documentData['issue_year'] ?? null,
                'source_url' => $documentData['source_url'] ?? '',
                'pdf_url' => $documentData['pdf_url'] ?? null, // ADD THIS
                'metadata' => $documentData['metadata'] ?? [],
                'full_text' => $documentData['full_text'] ?? '',
                'document_source_id' => $this->source->id,
                'status' => 'active',
                'checksum' => $checksum,
                'document_type_code' => $documentData['document_type_code'] ?? null, // ADD THIS
                'tik_relevance_score' => $documentData['tik_relevance_score'] ?? 0, // ADD THIS
                'tik_keywords' => $documentData['tik_keywords'] ?? [], // ADD THIS
                'is_tik_related' => $documentData['is_tik_related'] ?? false, // ADD THIS
            ]);

            Log::channel('legal-documents')->info("Document saved: {$document->title}");
            return $document;

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Failed to save document: {$e->getMessage()}", $documentData);
            return null;
        }
    }

    /**
     * Setup default HTTP headers.
     */
    protected function setupDefaultHeaders(): void
    {
        $this->headers = [
            'User-Agent' => config('legal_documents.http_client.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'DNT' => '1',
            'Connection' => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    /**
     * Apply configuration from document source.
     */
    protected function applyConfig(): void
    {
        $this->requestDelay = $this->source->getConfig('request_delay', 2);
        $this->timeout = $this->source->getConfig('timeout', 30);
        
        $customHeaders = $this->source->getConfig('headers', []);
        $this->headers = array_merge($this->headers, $customHeaders);
    }

    /**
     * Respect rate limiting between requests.
     */
    protected function respectRateLimit(): void
    {
        if ($this->requestDelay > 0) {
            sleep($this->requestDelay);
        }
    }

    /**
     * Clean and normalize text content.
     */
    protected function cleanText(string $text): string
    {
        // Remove extra whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove common noise words/characters
        $text = str_replace(['&nbsp;', '\r\n', '\n', '\r'], ' ', $text);
        
        return trim($text);
    }

    

    /**
     * Parse Indonesian date formats.
     */
    protected function parseIndonesianDate(string $dateString): ?string
    {
        // Common Indonesian date patterns
        $patterns = [
            '/(\d{1,2})\s+(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)\s+(\d{4})/',
            '/(\d{1,2})-(\d{1,2})-(\d{4})/',
            '/(\d{4})-(\d{1,2})-(\d{1,2})/',
        ];

        $monthMap = [
            'Januari' => '01', 'Februari' => '02', 'Maret' => '03', 'April' => '04',
            'Mei' => '05', 'Juni' => '06', 'Juli' => '07', 'Agustus' => '08',
            'September' => '09', 'Oktober' => '10', 'November' => '11', 'Desember' => '12'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $dateString, $matches)) {
                if (count($matches) === 4) {
                    // Indonesian format: day month year
                    if (isset($monthMap[$matches[2]])) {
                        return sprintf('%04d-%02d-%02d', $matches[3], $monthMap[$matches[2]], $matches[1]);
                    }
                    // Numeric formats
                    return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
                }
            }
        }

        return null;
    }
}