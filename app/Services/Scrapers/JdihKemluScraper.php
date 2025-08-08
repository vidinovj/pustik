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
        $documentUrls = $this->getDocumentUrls();
        
        Log::channel('legal-documents')->info("JDIH Kemlu: Starting scrape of " . count($documentUrls) . " documents");

        foreach ($documentUrls as $url) {
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
            
            // Progress logging every 10 documents
            if (count($results) % 10 === 0) {
                Log::channel('legal-documents')->info("JDIH Kemlu: Processed " . count($results) . " documents");
            }
        }

        $this->source->markAsScraped();
        Log::channel('legal-documents')->info("JDIH Kemlu: Completed scrape with " . count($results) . " documents");
        
        return $results;
    }

    /**
     * Get document URLs from JDIH Kemlu listing pages.
     */
    protected function getDocumentUrls(): array
    {
        $urls = [];
        $baseUrl = 'https://jdih.kemlu.go.id';
        
        // Document listing pages to scrape
        $listingPages = [
            '/dokumen?jenis=Permenlu',           // Ministerial Regulations
            '/dokumen?jenis=Kepdirjen',          // Director General Decisions
            '/dokumen?jenis=Kepmenko',           // Coordinating Minister Decisions
            '/dokumen?jenis=Surat+Edaran',       // Circulars
        ];

        foreach ($listingPages as $listingPage) {
            $pageUrls = $this->scrapeListingPage($baseUrl . $listingPage);
            $urls = array_merge($urls, $pageUrls);
            
            Log::channel('legal-documents')->info("JDIH Kemlu: Found " . count($pageUrls) . " documents on page: {$listingPage}");
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
        $maxPages = $this->source->getConfig('max_pages', 5); // Limit pages for demo

        while ($page <= $maxPages) {
            $pageUrl = $listingUrl . "&page={$page}";
            $html = $this->makeRequest($pageUrl);
            
            if (!$html) {
                break;
            }

            $dom = $this->parseHtml($html);
            $xpath = $this->createXPath($dom);
            
            // Extract document links (adjust selector based on actual HTML structure)
            $documentLinks = $xpath->query('//table//a[contains(@href, "/dokumen/")]');
            
            if ($documentLinks->length === 0) {
                Log::channel('legal-documents')->info("JDIH Kemlu: No more documents found on page {$page}");
                break;
            }

            foreach ($documentLinks as $link) {
                $href = $this->extractHref($link, 'https://jdih.kemlu.go.id');
                if ($href && !in_array($href, $urls)) {
                    $urls[] = $href;
                }
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
            // Extract title (adjust selectors based on actual HTML structure)
            $titleElement = $xpath->query('//h1[@class="document-title"] | //h1 | //title')->item(0);
            $title = $this->cleanText($this->extractText($titleElement));
            
            if (empty($title)) {
                Log::channel('legal-documents')->warning("JDIH Kemlu: No title found for URL: {$url}");
                return null;
            }

            // Extract document number
            $numberElement = $xpath->query('//*[contains(text(), "Nomor")]/following-sibling::*[1] | //*[contains(@class, "document-number")]')->item(0);
            $documentNumber = $this->cleanText($this->extractText($numberElement));

            // Extract document type
            $typeElement = $xpath->query('//*[contains(text(), "Jenis")]/following-sibling::*[1] | //*[contains(@class, "document-type")]')->item(0);
            $documentType = $this->cleanText($this->extractText($typeElement)) ?: $this->extractTypeFromUrl($url);

            // Extract issue date
            $dateElement = $xpath->query('//*[contains(text(), "Tanggal")]/following-sibling::*[1] | //*[contains(@class, "document-date")]')->item(0);
            $issueDateString = $this->cleanText($this->extractText($dateElement));
            $issueDate = $this->parseIndonesianDate($issueDateString);

            // Extract full text content
            $contentElement = $xpath->query('//div[contains(@class, "content")] | //div[contains(@class, "document-content")] | //main')->item(0);
            $fullText = $this->cleanText($this->extractText($contentElement));

            // Build metadata
            $metadata = [
                'source_site' => 'JDIH Kemlu',
                'scraped_at' => now()->toISOString(),
                'original_url' => $url,
                'document_status' => $this->extractDocumentStatus($xpath),
                'related_documents' => $this->extractRelatedDocuments($xpath),
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
        
        return 'Dokumen Hukum';
    }

    /**
     * Extract document status if available.
     */
    protected function extractDocumentStatus(DOMXPath $xpath): ?string
    {
        $statusElement = $xpath->query('//*[contains(text(), "Status")]/following-sibling::*[1]')->item(0);
        return $statusElement ? $this->cleanText($this->extractText($statusElement)) : null;
    }

    /**
     * Extract related documents if available.
     */
    protected function extractRelatedDocuments(DOMXPath $xpath): array
    {
        $related = [];
        $relatedLinks = $xpath->query('//div[contains(@class, "related")]//a | //section[contains(@class, "related")]//a');
        
        foreach ($relatedLinks as $link) {
            $href = $this->extractHref($link, 'https://jdih.kemlu.go.id');
            $text = $this->cleanText($this->extractText($link));
            
            if ($href && $text) {
                $related[] = [
                    'title' => $text,
                    'url' => $href,
                ];
            }
        }

        return $related;
    }
}