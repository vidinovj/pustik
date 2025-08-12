<?php

namespace App\Services\Scrapers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class FixedPeraturanScraper extends BaseScraper
{
    /**
     * Fixed scraper using discovered URL structure.
     */
    public function scrape(): array
    {
        $results = [];
        
        Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Starting scrape with discovered URLs");

        try {
            // Use the discovered working category URLs
            $categoryUrls = [
                'https://peraturan.go.id/uu',      // Laws
                'https://peraturan.go.id/pp',      // Government Regulations  
                'https://peraturan.go.id/perpres', // Presidential Regulations
                'https://peraturan.go.id/permen',  // Ministerial Regulations
            ];

            foreach ($categoryUrls as $categoryUrl) {
                Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Processing category: {$categoryUrl}");
                
                // Call getDocumentUrlsFromCategory directly
                $documentUrls = $this->getDocumentUrlsFromCategory($categoryUrl);
                
                Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Found " . count($documentUrls) . " document URLs in category");
                
                // Process first 5 documents per category to avoid overwhelming
                $limitedUrls = array_slice($documentUrls, 0, 5);
                
                foreach ($limitedUrls as $docUrl) {
                    $html = $this->makeRequest($docUrl);
                    
                    if ($html) {
                        if ($this->isLoginPage($html)) {
                            Log::channel('legal-documents-errors')->warning("Fixed Peraturan.go.id: Hit login page at {$docUrl}");
                            continue;
                        }
                        
                        $dom = $this->parseHtml($html);
                        $documentData = $this->extractDocumentData($dom, $docUrl);
                        
                        if ($documentData) {
                            $document = $this->saveDocument($documentData);
                            if ($document) {
                                $results[] = $document;
                                $this->source->incrementDocumentCount();
                            }
                        }
                    }
                }
                
                // Rate limiting between categories
                sleep(2);
                
                // Stop after getting some results for testing
                if (count($results) >= 15) {
                    Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Reached 15 documents limit for testing");
                    break;
                }
            }

            // Remove search functionality for now, focus on category browsing
            // if (count($results) < 10) {
            //     $searchResults = $this->searchUsingDiscoveredForm();
            //     $results = array_merge($results, $searchResults);
            // }

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Fixed Peraturan.go.id: Scraping failed: {$e->getMessage()}");
            throw $e;
        }

        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Completed scrape with " . count($results) . " documents");
        
        return $results;
    }

    /**
     * Get document URLs from category page using discovered URL patterns.
     */
    protected function getDocumentUrlsFromCategory(string $categoryUrl): array
    {
        $urls = [];
        
        try {
            $html = $this->makeRequest($categoryUrl);
            
            if ($html) {
                $dom = $this->parseHtml($html);
                $xpath = $this->createXPath($dom);
                
                // Look for links within the document strips
                $documentLinks = $xpath->query('//div[@class="strip grid"]//p/a[contains(@href, "/id/uu-") or contains(@href, "/id/pp-") or contains(@href, "/id/perpres-") or contains(@href, "/id/permen-")]');
                
                Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Found {" . $documentLinks->length . "} document links with specific patterns");
                
                foreach ($documentLinks as $link) {
                    $href = $this->extractHref($link, 'https://peraturan.go.id');
                    if ($href && !in_array($href, $urls)) {
                        $urls[] = $href;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Fixed Peraturan.go.id: Failed to get document URLs from {$categoryUrl}: {$e->getMessage()}");
        }
        
        return $urls;
    }

    /**
     * Search using the discovered form structure.
     */
    protected function searchUsingDiscoveredForm(): array
    {
        $results = [];
        
        try {
            $searchTerms = ['teknologi informasi', 'data pribadi', 'digital'];
            
            foreach ($searchTerms as $term) {
                // Use discovered form action and input name
                $searchUrl = 'https://peraturan.go.id/cariglobal?' . http_build_query([
                    'PeraturanSearch[idglobal]' => $term
                ]);
                
                Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Searching with discovered form: {$searchUrl}");
                
                $html = $this->makeRequest($searchUrl);
                
                if ($html && !$this->isLoginPage($html)) {
                    $dom = $this->parseHtml($html);
                    $xpath = $this->createXPath($dom);
                    
                    // Look for document links in search results
                    $resultLinks = $xpath->query('//a[starts-with(@href, "/id/peraturan-")]');
                    
                    Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Found {" . $resultLinks->length . "} search results for '{$term}'");
                    
                    foreach ($resultLinks as $link) {
                        $href = $this->extractHref($link, 'https://peraturan.go.id');
                        
                        if ($href) {
                            $docHtml = $this->makeRequest($href);
                            
                            if ($docHtml && !$this->isLoginPage($docHtml)) {
                                $docDom = $this->parseHtml($docHtml);
                                $documentData = $this->extractDocumentData($docDom, $href);
                                
                                if ($documentData) {
                                    $document = $this->saveDocument($documentData);
                                    if ($document) {
                                        $results[] = $document;
                                    }
                                }
                            }
                        }
                        
                        // Limit search results
                        if (count($results) >= 5) {
                            break 2;
                        }
                    }
                }
                
                // Rate limiting between searches
                sleep(2);
            }
            
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Fixed Peraturan.go.id: Search failed: {$e->getMessage()}");
        }
        
        return $results;
    }

    /**
     * Check if HTML content is a login page.
     */
    protected function isLoginPage(string $html): bool
    {
        $loginIndicators = [
            'E-Pengundangan | Login',
            'Login | E-penerjemah',
            'login form',
            'username',
            'password',
            'masuk',
            'sign in'
        ];
        
        foreach ($loginIndicators as $indicator) {
            if (stripos($html, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract document data with better validation.
     */
    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        $xpath = $this->createXPath($dom);
        
        try {
            // Extract title
            $titleElement = $xpath->query('//div[@class="strip grid"]//p[not(@style)]/a')->item(0);
            $title = $titleElement ? $this->cleanText($this->extractText($titleElement)) : '';
            
            // Extract document type
            $documentTypeElement = $xpath->query('//div[@class="strip grid"]//a[@class="float-right"]')->item(0);
            $documentType = $documentTypeElement ? $this->cleanText($this->extractText($documentTypeElement)) : 'Unknown';

            // Extract document number
            $documentNumberElement = $xpath->query('//div[@class="strip grid"]//p[@style="padding-top: -2;"]')->item(0);
            $documentNumberFull = $documentNumberElement ? $this->cleanText($this->extractText($documentNumberElement)) : '';
            preg_match('/Nomor\s+(\d+[\/\\w\\-\d]*)/i', $documentNumberFull, $matches);
            $documentNumber = $matches[1] ?? '';

            // Extract issue date (year)
            $issueYearElement = $xpath->query('//div[@class="strip grid"]//a[@class="wish_bt"]')->item(0);
            $issueYear = $issueYearElement ? $this->cleanText($this->extractText($issueYearElement)) : '';
            $issueDate = !empty($issueYear) ? "{$issueYear}-01-01" : null; // Default to Jan 1st of the year

            // Extract source URL (from the title link)
            $sourceUrlElement = $xpath->query('//div[@class="strip grid"]//p[not(@style)]/a')->item(0);
            $sourceUrl = $sourceUrlElement ? $this->extractHref($sourceUrlElement, 'https://peraturan.go.id') : $url;

            // Extract full text content (can be simplified for now, or use a more specific selector if available)
            // For now, let's combine title and document number as a basic full text
            $fullText = $title . ' ' . $documentNumberFull;

            // Extract metadata (agency and PDF URL)
            $agencyElement = $xpath->query('//div[@class="strip grid"]//span[@class="loc_open"]')->item(0);
            $agency = $agencyElement ? $this->cleanText($this->extractText($agencyElement)) : 'Unknown Agency';

            $pdfUrlElement = $xpath->query('//div[@class="strip grid"]//li/a[img[contains(@src, "pdf")]]')->item(0);
            $pdfUrl = $pdfUrlElement ? $this->extractHref($pdfUrlElement, 'https://peraturan.go.id') : null;

            return [
                'title' => $title,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'issue_date' => $issueDate,
                'source_url' => $sourceUrl,
                'metadata' => [
                    'source_site' => 'Peraturan.go.id',
                    'agency' => $agency,
                    'pdf_url' => $pdfUrl,
                    'extraction_method' => 'puppeteer_fixed',
                    'scraped_at' => now()->toISOString(),
                ],
                'full_text' => $fullText,
            ];

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Fixed Peraturan.go.id: Error extracting data from {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Scrape with a limit, for compatibility with UnifiedScraperCommand.
     */
    public function scrapeWithLimit(int $limit): array
    {
        $allResults = $this->scrape(); // Call the existing scrape method
        return array_slice($allResults, 0, $limit);
    }

    /**
     * Required by BaseScraper.
     */
    protected function getDocumentUrls(): array
    {
        return [];
    }
}