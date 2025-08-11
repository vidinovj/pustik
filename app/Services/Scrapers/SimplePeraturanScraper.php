<?php

namespace App\Services\Scrapers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class SimplePeraturanScraper extends BaseScraper
{
    /**
     * Simple scraper that browses without search functionality.
     */
    public function scrape(): array
    {
        $results = [];
        
        Log::channel('legal-documents')->info("Simple Peraturan.go.id: Starting browse-based scrape");

        try {
            // Instead of searching, let's browse categories/listings
            $browseUrls = $this->findBrowseUrls();
            
            Log::channel('legal-documents')->info("Simple Peraturan.go.id: Found " . count($browseUrls) . " browse URLs");
            
            foreach ($browseUrls as $browseUrl) {
                $documentUrls = $this->getDocumentUrlsFromListing($browseUrl);
                
                Log::channel('legal-documents')->info("Simple Peraturan.go.id: Found " . count($documentUrls) . " documents in {$browseUrl}");
                
                // Process first 5 documents per listing to avoid overwhelming
                $limitedUrls = array_slice($documentUrls, 0, 5);
                
                foreach ($limitedUrls as $url) {
                    $html = $this->makeRequest($url);
                    
                    if ($html) {
                        $dom = $this->parseHtml($html);
                        $documentData = $this->extractDocumentData($dom, $url);
                        
                        if ($documentData) {
                            $document = $this->saveDocument($documentData);
                            if ($document) {
                                $results[] = $document;
                                $this->source->incrementDocumentCount();
                            }
                        }
                    }
                }
                
                // Rate limiting between listings
                sleep(2);
                
                // Stop after getting some results for testing
                if (count($results) >= 10) {
                    break;
                }
            }

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Simple Peraturan.go.id: Scraping failed: {$e->getMessage()}");
            throw $e;
        }

        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("Simple Peraturan.go.id: Completed scrape with " . count($results) . " documents");
        
        return $results;
    }

    /**
     * Find browse/listing URLs by testing common patterns.
     */
    protected function findBrowseUrls(): array
    {
        $workingUrls = [];
        
        // Test common patterns
        $patterns = [
            'https://peraturan.go.id/peraturan',
            'https://peraturan.go.id/dokumen', 
            'https://peraturan.go.id/uu',
            'https://peraturan.go.id/pp',
            'https://peraturan.go.id/perpres',
            'https://peraturan.go.id/permen',
            'https://peraturan.go.id/list',
            'https://peraturan.go.id/browse',
            'https://peraturan.go.id/kategori',
            'https://peraturan.go.id/jenis'
        ];

        foreach ($patterns as $testUrl) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->get($testUrl);
                
                if ($response->successful()) {
                    $content = $response->body();
                    
                    // Check if it looks like a document listing
                    if ($this->looksLikeDocumentListing($content)) {
                        $workingUrls[] = $testUrl;
                        Log::channel('legal-documents')->info("Simple Peraturan.go.id: Found working browse URL: {$testUrl}");
                    }
                }
                
            } catch (\Exception $e) {
                // Silent fail for URL testing
            }
            
            // Rate limiting
            usleep(300000); // 0.3 seconds
        }

        // Fallback: try to extract URLs from homepage
        if (empty($workingUrls)) {
            $workingUrls = $this->extractUrlsFromHomepage();
        }

        return $workingUrls;
    }

    /**
     * Check if content looks like a document listing.
     */
    protected function looksLikeDocumentListing(string $content): bool
    {
        $indicators = [
            'nomor', 'tahun', 'jenis', 'tentang', 'peraturan', 
            'undang-undang', 'keputusan', 'instruksi'
        ];
        
        $matches = 0;
        foreach ($indicators as $indicator) {
            if (stripos($content, $indicator) !== false) {
                $matches++;
            }
        }
        
        return $matches >= 3; // Must have at least 3 indicators
    }

    /**
     * Extract document URLs from homepage navigation.
     */
    protected function extractUrlsFromHomepage(): array
    {
        $urls = [];
        
        try {
            $html = $this->makeRequest('https://peraturan.go.id');
            
            if ($html) {
                $dom = $this->parseHtml($html);
                $xpath = $this->createXPath($dom);
                
                // Look for navigation links that might lead to document listings
                $navLinks = $xpath->query('//nav//a | //ul[contains(@class, "menu")]//a | //header//a');
                
                foreach ($navLinks as $link) {
                    $href = $this->extractHref($link, 'https://peraturan.go.id');
                    $text = trim($link->textContent);
                    
                    // Filter for document-related links
                    if ($href && (
                        stripos($text, 'peraturan') !== false ||
                        stripos($text, 'dokumen') !== false ||
                        stripos($text, 'database') !== false ||
                        stripos($href, 'peraturan') !== false ||
                        stripos($href, 'dokumen') !== false
                    )) {
                        $urls[] = $href;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Simple Peraturan.go.id: Failed to extract URLs from homepage: {$e->getMessage()}");
        }
        
        return array_unique($urls);
    }

    /**
     * Get document URLs from a listing page.
     */
    protected function getDocumentUrlsFromListing(string $listingUrl): array
    {
        $urls = [];
        
        try {
            $html = $this->makeRequest($listingUrl);
            
            if ($html) {
                $dom = $this->parseHtml($html);
                $xpath = $this->createXPath($dom);
                
                // Try different patterns for document links
                $linkPatterns = [
                    '//a[contains(@href, "/detail/")]',
                    '//a[contains(@href, "/view/")]', 
                    '//a[contains(@href, "/dokumen/")]',
                    '//a[contains(@href, "/peraturan/")]',
                    '//table//a',
                    '//tbody//a',
                    '//ul//a[contains(@href, "peraturan")]',
                    '//div[contains(@class, "list")]//a'
                ];

                foreach ($linkPatterns as $pattern) {
                    $links = $xpath->query($pattern);
                    
                    if ($links->length > 0) {
                        Log::channel('legal-documents')->info("Simple Peraturan.go.id: Using pattern '{$pattern}' - found {$links->length} links");
                        
                        foreach ($links as $link) {
                            $href = $this->extractHref($link, 'https://peraturan.go.id');
                            if ($href && !in_array($href, $urls)) {
                                $urls[] = $href;
                            }
                        }
                        
                        // Use first working pattern
                        if (count($urls) > 0) {
                            break;
                        }
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Simple Peraturan.go.id: Failed to get document URLs from {$listingUrl}: {$e->getMessage()}");
        }
        
        return $urls;
    }

    /**
     * Extract document data using flexible patterns.
     */
    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        $xpath = $this->createXPath($dom);
        
        try {
            // Get page title as fallback
            $pageTitle = '';
            $titleElement = $xpath->query('//title')->item(0);
            if ($titleElement) {
                $pageTitle = $this->cleanText($this->extractText($titleElement));
            }

            // Try to extract title from various elements
            $titlePatterns = [
                '//h1',
                '//h2[@class="title"]',
                '//h2',
                '//*[@class="title"]',
                '//*[contains(@class, "judul")]'
            ];
            
            $title = '';
            foreach ($titlePatterns as $pattern) {
                $titleElement = $xpath->query($pattern)->item(0);
                if ($titleElement) {
                    $title = $this->cleanText($this->extractText($titleElement));
                    if (!empty($title) && strlen($title) > 10 && $title !== $pageTitle) {
                        break;
                    }
                }
            }
            
            // Use page title if no content title found
            if (empty($title)) {
                $title = $pageTitle;
            }
            
            if (empty($title) || strlen($title) < 10) {
                Log::channel('legal-documents-errors')->warning("Simple Peraturan.go.id: No adequate title found for URL: {$url}");
                return null;
            }

            // Simple extraction for demo purposes
            return [
                'title' => $title,
                'document_type' => 'Peraturan Perundang-undangan',
                'document_number' => 'EXTRACTED-' . date('YmdHis'),
                'issue_date' => now()->format('Y-m-d'),
                'source_url' => $url,
                'metadata' => [
                    'source_site' => 'Peraturan.go.id',
                    'extraction_method' => 'browse_based',
                    'scraped_at' => now()->toISOString(),
                ],
                'full_text' => substr($title, 0, 500),
            ];

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Simple Peraturan.go.id: Error extracting data from {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Required by BaseScraper.
     */
    protected function getDocumentUrls(): array
    {
        return [];
    }
}