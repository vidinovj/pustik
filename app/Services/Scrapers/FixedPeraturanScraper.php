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
                
                $documentUrls = $this->getDocumentUrlsFromCategory($categoryUrl);
                
                Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Found " . count($documentUrls) . " document URLs in category");
                
                // Process first 5 documents per category to avoid overwhelming
                $limitedUrls = array_slice($documentUrls, 0, 5);
                
                foreach ($limitedUrls as $docUrl) {
                    $html = $this->makeRequest($docUrl);
                    
                    if ($html) {
                        // Check if we got a login page
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

            // Try search functionality as well
            if (count($results) < 10) {
                $searchResults = $this->searchUsingDiscoveredForm();
                $results = array_merge($results, $searchResults);
            }

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
                
                // Look for links matching the discovered pattern: /id/peraturan-*
                $documentLinks = $xpath->query('//a[starts-with(@href, "/id/peraturan-")]');
                
                Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Found {$documentLinks->length} document links with /id/peraturan- pattern");
                
                foreach ($documentLinks as $link) {
                    $href = $this->extractHref($link, 'https://peraturan.go.id');
                    if ($href && !in_array($href, $urls)) {
                        $urls[] = $href;
                    }
                }
                
                // Fallback: try other patterns if no /id/peraturan- links found
                if (count($urls) === 0) {
                    $fallbackPatterns = [
                        '//a[contains(@href, "/peraturan")]',
                        '//a[contains(@href, "/id/")]',
                        '//table//a',
                        '//tbody//a'
                    ];
                    
                    foreach ($fallbackPatterns as $pattern) {
                        $links = $xpath->query($pattern);
                        
                        if ($links->length > 0) {
                            Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Using fallback pattern '{$pattern}' - found {$links->length} links");
                            
                            foreach ($links as $link) {
                                $href = $this->extractHref($link, 'https://peraturan.go.id');
                                $text = trim($link->textContent);
                                
                                // Skip obvious non-document links
                                if ($href && !in_array($href, $urls) && 
                                    !stripos($text, 'login') && 
                                    !stripos($text, 'download') &&
                                    !stripos($href, 'login')) {
                                    $urls[] = $href;
                                }
                            }
                            
                            // Use first working fallback pattern
                            if (count($urls) > 0) {
                                break;
                            }
                        }
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
                    
                    Log::channel('legal-documents')->info("Fixed Peraturan.go.id: Found {$resultLinks->length} search results for '{$term}'");
                    
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
            // Get page title
            $titleElement = $xpath->query('//title')->item(0);
            $pageTitle = $titleElement ? $this->cleanText($this->extractText($titleElement)) : '';
            
            // Skip if it's a login page
            if ($this->isLoginPage($pageTitle)) {
                Log::channel('legal-documents-errors')->warning("Fixed Peraturan.go.id: Skipping login page: {$url}");
                return null;
            }
            
            // Extract content title
            $titlePatterns = [
                '//h1[@class="title"]',
                '//h1',
                '//h2[@class="title"]', 
                '//h2',
                '//*[@class="document-title"]',
                '//*[contains(@class, "judul")]'
            ];
            
            $title = '';
            foreach ($titlePatterns as $pattern) {
                $titleElement = $xpath->query($pattern)->item(0);
                if ($titleElement) {
                    $title = $this->cleanText($this->extractText($titleElement));
                    if (!empty($title) && strlen($title) > 15 && !stripos($title, 'login')) {
                        break;
                    }
                }
            }
            
            // Fallback to page title if no good content title
            if (empty($title) || stripos($title, 'login') !== false) {
                $title = $pageTitle;
            }
            
            // Final validation
            if (empty($title) || strlen($title) < 15 || stripos($title, 'login') !== false) {
                Log::channel('legal-documents-errors')->warning("Fixed Peraturan.go.id: Invalid or login title for URL: {$url} - Title: {$title}");
                return null;
            }

            // Extract document number from URL pattern
            $documentNumber = '';
            if (preg_match('/\/id\/(peraturan-[^\/]+)/', $url, $matches)) {
                $documentNumber = $matches[1];
            }

            // Extract document type from URL
            $documentType = 'Peraturan Perundang-undangan';
            if (stripos($url, '/uu/') !== false) {
                $documentType = 'Undang-Undang';
            } elseif (stripos($url, '/pp/') !== false) {
                $documentType = 'Peraturan Pemerintah';
            } elseif (stripos($url, '/perpres/') !== false) {
                $documentType = 'Peraturan Presiden';
            } elseif (stripos($url, '/permen/') !== false) {
                $documentType = 'Peraturan Menteri';
            }

            // Extract content for full text
            $contentPatterns = [
                '//div[contains(@class, "content")]',
                '//main',
                '//article',
                '//div[contains(@class, "document")]'
            ];
            
            $fullText = '';
            foreach ($contentPatterns as $pattern) {
                $contentElement = $xpath->query($pattern)->item(0);
                if ($contentElement) {
                    $fullText = $this->cleanText($this->extractText($contentElement));
                    if (strlen($fullText) > 100 && !stripos($fullText, 'login')) {
                        break;
                    }
                }
            }

            return [
                'title' => $title,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'issue_date' => now()->format('Y-m-d'), // TODO: Extract real date
                'source_url' => $url,
                'metadata' => [
                    'source_site' => 'Peraturan.go.id (Fixed)',
                    'extraction_method' => 'category_browse_fixed',
                    'scraped_at' => now()->toISOString(),
                    'url_pattern' => 'discovered_structure',
                ],
                'full_text' => $fullText ?: substr($title, 0, 500),
            ];

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Fixed Peraturan.go.id: Error extracting data from {$url}: {$e->getMessage()}");
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