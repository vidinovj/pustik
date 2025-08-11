<?php

namespace App\Services\Scrapers;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

class PeraturanGoIdScraper extends BaseScraper
{
    /**
     * Main scraping method for peraturan.go.id.
     */
    public function scrape(): array
    {
        $results = [];
        
        Log::channel('legal-documents')->info("Peraturan.go.id: Starting scrape");

        try {
            // Search for technology-related documents
            $searchTerms = [
                'teknologi informasi',
                'telekomunikasi',
                'data pribadi',
                'keamanan siber',
                'sistem informasi'
            ];

            foreach ($searchTerms as $term) {
                $documentUrls = $this->searchDocuments($term);
                
                Log::channel('legal-documents')->info("Peraturan.go.id: Found " . count($documentUrls) . " documents for '{$term}'");
                
                // Process first 10 documents per search term to avoid overwhelming
                $limitedUrls = array_slice($documentUrls, 0, 10);
                
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
                
                // Respect rate limiting between search terms
                sleep(2);
            }

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Peraturan.go.id: Scraping failed: {$e->getMessage()}");
            throw $e;
        }

        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("Peraturan.go.id: Completed scrape with " . count($results) . " documents");
        
        return $results;
    }

    /**
     * Search for documents using the site's search functionality.
     */
    protected function searchDocuments(string $searchTerm): array
    {
        $urls = [];
        
        try {
            // Try common search URL patterns for peraturan.go.id
            $searchUrls = [
                "https://peraturan.go.id/search?q=" . urlencode($searchTerm),
                "https://peraturan.go.id/common/dokumen/list?search=" . urlencode($searchTerm),
                "https://peraturan.go.id/home/search?keyword=" . urlencode($searchTerm),
            ];

            foreach ($searchUrls as $searchUrl) {
                $html = $this->makeRequest($searchUrl);
                
                if ($html) {
                    $dom = $this->parseHtml($html);
                    $xpath = $this->createXPath($dom);
                    
                    // Try different link patterns
                    $linkPatterns = [
                        '//a[contains(@href, "/common/dokumen/")]',
                        '//a[contains(@href, "/dokumen/")]',
                        '//a[contains(@href, "/detail/")]',
                        '//table//a[contains(@href, "peraturan")]',
                        '//div[contains(@class, "result")]//a',
                        '//ul[contains(@class, "list")]//a'
                    ];

                    foreach ($linkPatterns as $pattern) {
                        $links = $xpath->query($pattern);
                        
                        if ($links->length > 0) {
                            Log::channel('legal-documents')->info("Peraturan.go.id: Using link pattern '{$pattern}' - found {$links->length} links");
                            
                            foreach ($links as $link) {
                                $href = $this->extractHref($link, 'https://peraturan.go.id');
                                if ($href && !in_array($href, $urls)) {
                                    $urls[] = $href;
                                }
                            }
                            
                            // Break after finding working pattern
                            if (count($urls) > 0) {
                                break 2;
                            }
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Peraturan.go.id: Search failed for '{$searchTerm}': {$e->getMessage()}");
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
            // Extract title using multiple patterns
            $titlePatterns = [
                '//h1[@class="title"]',
                '//h1',
                '//h2[@class="title"]',
                '//h2',
                '//*[@class="document-title"]',
                '//*[contains(@class, "judul")]',
                '//title'
            ];
            
            $title = '';
            foreach ($titlePatterns as $pattern) {
                $titleElement = $xpath->query($pattern)->item(0);
                if ($titleElement) {
                    $title = $this->cleanText($this->extractText($titleElement));
                    if (!empty($title) && strlen($title) > 10) {
                        break;
                    }
                }
            }
            
            if (empty($title)) {
                Log::channel('legal-documents-errors')->warning("Peraturan.go.id: No title found for URL: {$url}");
                return null;
            }

            // Extract document number
            $numberPatterns = [
                '//*[contains(text(), "Nomor")]/following-sibling::*[1]',
                '//*[contains(text(), "Number")]/following-sibling::*[1]',
                '//*[@class="document-number"]',
                '//*[contains(@class, "nomor")]'
            ];
            
            $documentNumber = '';
            foreach ($numberPatterns as $pattern) {
                $numberElement = $xpath->query($pattern)->item(0);
                if ($numberElement) {
                    $documentNumber = $this->cleanText($this->extractText($numberElement));
                    if (!empty($documentNumber)) {
                        break;
                    }
                }
            }

            // Extract document type
            $typePatterns = [
                '//*[contains(text(), "Jenis")]/following-sibling::*[1]',
                '//*[contains(text(), "Type")]/following-sibling::*[1]',
                '//*[@class="document-type"]',
                '//*[contains(@class, "jenis")]'
            ];
            
            $documentType = 'Peraturan';
            foreach ($typePatterns as $pattern) {
                $typeElement = $xpath->query($pattern)->item(0);
                if ($typeElement) {
                    $documentType = $this->cleanText($this->extractText($typeElement));
                    if (!empty($documentType)) {
                        break;
                    }
                }
            }

            // Extract issue date
            $datePatterns = [
                '//*[contains(text(), "Tanggal")]/following-sibling::*[1]',
                '//*[contains(text(), "Date")]/following-sibling::*[1]',
                '//*[@class="document-date"]',
                '//*[contains(@class, "tanggal")]'
            ];
            
            $issueDate = null;
            foreach ($datePatterns as $pattern) {
                $dateElement = $xpath->query($pattern)->item(0);
                if ($dateElement) {
                    $dateString = $this->cleanText($this->extractText($dateElement));
                    $issueDate = $this->parseIndonesianDate($dateString);
                    if ($issueDate) {
                        break;
                    }
                }
            }

            // Extract full text content
            $contentPatterns = [
                '//div[contains(@class, "content")]',
                '//div[contains(@class, "document-content")]',
                '//div[contains(@class, "body")]',
                '//main',
                '//article'
            ];
            
            $fullText = '';
            foreach ($contentPatterns as $pattern) {
                $contentElement = $xpath->query($pattern)->item(0);
                if ($contentElement) {
                    $fullText = $this->cleanText($this->extractText($contentElement));
                    if (strlen($fullText) > 100) {
                        break;
                    }
                }
            }

            // Build metadata
            $metadata = [
                'source_site' => 'Peraturan.go.id',
                'scraped_at' => now()->toISOString(),
                'original_url' => $url,
                'extraction_patterns_used' => [
                    'title' => $titlePatterns,
                    'number' => $numberPatterns,
                    'type' => $typePatterns,
                    'date' => $datePatterns,
                ],
            ];

            return [
                'title' => $title,
                'document_type' => $documentType,
                'document_number' => $documentNumber,
                'issue_date' => $issueDate,
                'source_url' => $url,
                'metadata' => $metadata,
                'full_text' => $fullText,
            ];

        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Peraturan.go.id: Error extracting data from {$url}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Required by BaseScraper - returns search results.
     */
    protected function getDocumentUrls(): array
    {
        // This method is called by the base scraper but we handle URL discovery in scrape()
        return [];
    }
}