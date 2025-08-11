<?php

namespace App\Services\Scrapers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class JdihKemluScraper extends BaseScraper
{
    /**
     * Main scraping method for JDIH Kemlu.
     */
    public function scrape(): array
    {
        $results = [];
        
        // Start with debugging the listing page first
        $this->debugListingPage();
        
        $documentUrls = $this->getDocumentUrls();
        
        Log::channel('legal-documents')->info("JDIH Kemlu: Starting scrape of " . count($documentUrls) . " documents");

        // Limit to first 5 URLs for debugging
        $debugUrls = array_slice($documentUrls, 0, 5);
        
        foreach ($debugUrls as $url) {
            Log::channel('legal-documents')->info("JDIH Kemlu: Processing URL: {$url}");
            
            $html = $this->makeRequest($url);
            
            if ($html) {
                $this->debugDocumentPage($html, $url);
                
                $dom = $this->parseHtml($html);
                $documentData = $this->extractDocumentData($dom, $url);
                
                if ($documentData) {
                    Log::channel('legal-documents')->info("JDIH Kemlu: Extracted data for {$url}", $documentData);
                    $document = $this->saveDocument($documentData);
                    if ($document) {
                        $results[] = $document;
                        $this->source->incrementDocumentCount();
                    }
                } else {
                    Log::channel('legal-documents-errors')->error("JDIH Kemlu: Failed to extract data from {$url}");
                }
            } else {
                Log::channel('legal-documents-errors')->error("JDIH Kemlu: Failed to fetch HTML from {$url}");
            }
        }

        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("JDIH Kemlu: Completed debug scrape with " . count($results) . " documents");
        
        return $results;
    }

    /**
     * Debug the listing page structure.
     */
    protected function debugListingPage(): void
    {
        $testUrl = 'https://jdih.kemlu.go.id/dokumen?jenis=Permenlu';
        Log::channel('legal-documents')->info("JDIH Kemlu: Debugging listing page: {$testUrl}");
        
        $html = $this->makeRequest($testUrl);
        
        if ($html) {
            $dom = $this->parseHtml($html);
            $xpath = $this->createXPath($dom);
            
            // Debug: Check page title
            $titleElement = $xpath->query('//title')->item(0);
            $title = $titleElement ? $this->extractText($titleElement) : 'No title found';
            Log::channel('legal-documents')->info("JDIH Kemlu: Page title: {$title}");
            
            // Debug: Check for any links
            $allLinks = $xpath->query('//a[@href]');
            Log::channel('legal-documents')->info("JDIH Kemlu: Found {$allLinks->length} total links");
            
            // Debug: Check specific link patterns
            $documentLinks = $xpath->query('//a[contains(@href, "/dokumen/")]');
            Log::channel('legal-documents')->info("JDIH Kemlu: Found {$documentLinks->length} document links");
            
            // Alternative patterns to try
            $altPatterns = [
                '//a[contains(@href, "detail")]',
                '//a[contains(@href, "permenlu")]',
                '//table//a',
                '//div[@class="content"]//a',
                '//ul//a',
                '//tbody//a'
            ];
            
            foreach ($altPatterns as $pattern) {
                $matches = $xpath->query($pattern);
                Log::channel('legal-documents')->info("JDIH Kemlu: Pattern '{$pattern}' found {$matches->length} matches");
                
                if ($matches->length > 0 && $matches->length < 20) {
                    for ($i = 0; $i < min(3, $matches->length); $i++) {
                        $link = $matches->item($i);
                        $href = $link->getAttribute('href');
                        $text = trim($link->textContent);
                        Log::channel('legal-documents')->info("JDIH Kemlu: Sample link {$i}: {$href} - {$text}");
                    }
                }
            }
            
            // Save sample HTML for manual inspection
            if (strlen($html) > 0) {
                $samplePath = storage_path('logs/jdih_kemlu_sample.html');
                file_put_contents($samplePath, $html);
                Log::channel('legal-documents')->info("JDIH Kemlu: Sample HTML saved to {$samplePath}");
            }
        }
    }

    /**
     * Debug document page structure.
     */
    protected function debugDocumentPage(string $html, string $url): void
    {
        $dom = $this->parseHtml($html);
        $xpath = $this->createXPath($dom);
        
        Log::channel('legal-documents')->info("JDIH Kemlu: Debugging document page: {$url}");
        
        // Check page title
        $titleElement = $xpath->query('//title')->item(0);
        $title = $titleElement ? $this->extractText($titleElement) : 'No title found';
        Log::channel('legal-documents')->info("JDIH Kemlu: Document page title: {$title}");
        
        // Try different title patterns
        $titlePatterns = [
            '//h1',
            '//h2',
            '//h3',
            '//*[@class="title"]',
            '//*[@class="document-title"]',
            '//*[contains(@class, "judul")]',
            '//*[contains(@class, "title")]'
        ];
        
        foreach ($titlePatterns as $pattern) {
            $matches = $xpath->query($pattern);
            if ($matches->length > 0) {
                $text = $this->extractText($matches->item(0));
                Log::channel('legal-documents')->info("JDIH Kemlu: Title pattern '{$pattern}': {$text}");
            }
        }
        
        // Check for metadata patterns
        $metadataPatterns = [
            '//*[contains(text(), "Nomor")]',
            '//*[contains(text(), "Tanggal")]',
            '//*[contains(text(), "Jenis")]',
            '//*[@class="meta"]',
            '//*[@class="metadata"]'
        ];
        
        foreach ($metadataPatterns as $pattern) {
            $matches = $xpath->query($pattern);
            if ($matches->length > 0) {
                for ($i = 0; $i < min(2, $matches->length); $i++) {
                    $text = $this->extractText($matches->item($i));
                    Log::channel('legal-documents')->info("JDIH Kemlu: Metadata pattern '{$pattern}': {$text}");
                }
            }
        }
    }

    /**
     * Get document URLs from JDIH Kemlu listing pages.
     */
    protected function getDocumentUrls(): array
    {
        $urls = [];
        $baseUrl = 'https://jdih.kemlu.go.id';
        
        // Start with just one listing page for debugging
        $listingPages = [
            '/dokumen?jenis=Permenlu',
        ];

        foreach ($listingPages as $listingPage) {
            $pageUrls = $this->scrapeListingPage($baseUrl . $listingPage);
            $urls = array_merge($urls, $pageUrls);
            
            Log::channel('legal-documents')->info("JDIH Kemlu: Found " . count($pageUrls) . " documents on page: {$listingPage}");
            
            // Break after first page for debugging
            break;
        }

        return array_unique($urls);
    }

    /**
     * Scrape document URLs from a listing page.
     */
    protected function scrapeListingPage(string $listingUrl): array
    {
        $urls = [];
        $page = 1;
        $maxPages = 1; // Limit to 1 page for debugging

        while ($page <= $maxPages) {
            $pageUrl = $listingUrl . "&page={$page}";
            $html = $this->makeRequest($pageUrl);
            
            if (!$html) {
                Log::channel('legal-documents-errors')->error("JDIH Kemlu: Failed to get HTML for {$pageUrl}");
                break;
            }

            $dom = $this->parseHtml($html);
            $xpath = $this->createXPath($dom);
            
            // Try multiple link patterns
            $linkPatterns = [
                '//table//a[contains(@href, "/dokumen/")]',
                '//a[contains(@href, "/dokumen/")]',
                '//a[contains(@href, "detail")]',
                '//tbody//a',
                '//div[contains(@class, "content")]//a'
            ];
            
            $foundLinks = false;
            foreach ($linkPatterns as $pattern) {
                $documentLinks = $xpath->query($pattern);
                
                if ($documentLinks->length > 0) {
                    Log::channel('legal-documents')->info("JDIH Kemlu: Using pattern '{$pattern}' - found {$documentLinks->length} links");
                    
                    foreach ($documentLinks as $link) {
                        $href = $this->extractHref($link, 'https://jdih.kemlu.go.id');
                        if ($href && !in_array($href, $urls)) {
                            $urls[] = $href;
                            Log::channel('legal-documents')->info("JDIH Kemlu: Added URL: {$href}");
                        }
                    }
                    $foundLinks = true;
                    break; // Use first working pattern
                }
            }
            
            if (!$foundLinks) {
                Log::channel('legal-documents')->warning("JDIH Kemlu: No document links found on page {$page}");
            }

            $page++;
        }

        return $urls;
    }

    /**
     * Extract document data from individual document page.
     */
    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        $xpath = $this->createXPath($dom);
        
        try {
            // Try multiple title patterns
            $titlePatterns = [
                '//h1[@class="document-title"]',
                '//h1',
                '//h2',
                '//title',
                '//*[contains(@class, "judul")]',
                '//*[contains(@class, "title")]'
            ];
            
            $title = '';
            foreach ($titlePatterns as $pattern) {
                $titleElement = $xpath->query($pattern)->item(0);
                if ($titleElement) {
                    $title = $this->cleanText($this->extractText($titleElement));
                    if (!empty($title) && strlen($title) > 10) {
                        Log::channel('legal-documents')->info("JDIH Kemlu: Found title with pattern '{$pattern}': {$title}");
                        break;
                    }
                }
            }
            
            if (empty($title)) {
                Log::channel('legal-documents-errors')->error("JDIH Kemlu: No title found for URL: {$url}");
                return null;
            }

            // Simplified extraction for debugging
            $documentData = [
                'title' => $title,
                'document_type' => $this->extractTypeFromUrl($url),
                'document_number' => 'DEBUG-' . date('YmdHis'),
                'issue_date' => now()->format('Y-m-d'),
                'source_url' => $url,
                'metadata' => [
                    'source_site' => 'JDIH Kemlu',
                    'scraped_at' => now()->toISOString(),
                    'debug_mode' => true,
                ],
                'full_text' => substr($title, 0, 500), // Truncated for debugging
            ];

            Log::channel('legal-documents')->info("JDIH Kemlu: Successfully extracted data", $documentData);
            return $documentData;

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("JDIH Kemlu: Error extracting data from {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Extract document type from URL pattern.
     */
    protected function extractTypeFromUrl(string $url): string
    {
        if (str_contains($url, 'permenlu')) return 'Peraturan Menteri Luar Negeri';
        if (str_contains($url, 'kepdirjen')) return 'Keputusan Direktur Jenderal';
        if (str_contains($url, 'kepmenko')) return 'Keputusan Menteri Koordinator';
        if (str_contains($url, 'surat-edaran')) return 'Surat Edaran';
        
        return 'Dokumen Hukum (Debug)';
    }
}