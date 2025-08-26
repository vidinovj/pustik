<?php
// app/Services/Scrapers/ScraperFactory.php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use Illuminate\Support\Facades\Log;
use App\Services\Scrapers\Enhanced\KemluTikScraper;

class ScraperFactory
{
    /**
     * Available scraper classes mapped to source names.
     */
    protected static array $scrapers = [
        'bpk' => BpkScraper::class,
        'peraturan_bpk_go_id' => BpkScraper::class,
    ];

    /**
     * Create a scraper instance for the given source.
     */
    public static function create(DocumentSource $source): ?BaseScraper
    {
        $sourceType = $source->name;
        
        if (!isset(static::$scrapers[$sourceType])) {
            Log::channel('legal-documents-errors')->error("No scraper found for source: {$sourceType}");
            return null;
        }

        $scraperClass = static::$scrapers[$sourceType];
        
        if (!class_exists($scraperClass)) {
            Log::channel('legal-documents-errors')->error("Scraper class not found: {$scraperClass}");
            return null;
        }

        try {
            return new $scraperClass($source);
        } catch (\Exception $e) {
            Log::channel('legal-documents-errors')->error("Failed to create scraper for {$sourceType}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Create a scraper by detecting source type from URL.
     */
    public static function createFromUrl(string $url): ?BaseScraper
    {
        $sourceType = static::detectSourceTypeFromUrl($url);
        
        if (!$sourceType) {
            return null;
        }

        // Create a temporary DocumentSource for the scraper
        $tempSource = new DocumentSource([
            'name' => $sourceType,
            'base_url' => $url,
            'status' => 'active'
        ]);

        return static::create($tempSource);
    }

    /**
     * Detect source type from URL pattern.
     */
    private static function detectSourceTypeFromUrl(string $url): ?string
    {
        $patterns = [
            '/peraturan\.bpk\.go\.id/' => 'bpk',
        ];

        foreach ($patterns as $pattern => $type) {
            if (preg_match($pattern, $url)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Get list of available scraper types.
     */
    public static function getAvailableScrapers(): array
    {
        return array_keys(static::$scrapers);
    }

    /**
     * Register a new scraper type.
     */
    public static function register(string $sourceType, string $scraperClass): void
    {
        if (!is_subclass_of($scraperClass, BaseScraper::class)) {
            throw new \InvalidArgumentException("Scraper class must extend BaseScraper");
        }

        static::$scrapers[$sourceType] = $scraperClass;
    }

    /**
     * Check if scraper exists for source type.
     */
    public static function hasScraperFor(string $sourceType): bool
    {
        return isset(static::$scrapers[$sourceType]);
    }

    /**
     * Get all active sources that have scrapers.
     */
    public static function getScrapableSources(): \Illuminate\Database\Eloquent\Collection
    {
        return DocumentSource::active()
            ->whereIn('name', array_keys(static::$scrapers))
            ->get();
    }

    /**
     * Get recommended scraper for a document type.
     */
    public static function getRecommendedScraper(string $documentType): ?string
    {
        return 'bpk'; // Default to BPK for all legal documents
    }

    /**
     * Test all available scrapers with sample URLs.
     */
    public static function testAllScrapers(): array
    {
        $testUrls = [
            'bpk' => 'https://peraturan.bpk.go.id/Details/274494/uu-no-11-tahun-2008',
        ];

        $results = [];

        foreach ($testUrls as $type => $url) {
            if (isset(static::$scrapers[$type])) {
                try {
                    $tempSource = new DocumentSource([
                        'name' => $type,
                        'base_url' => $url,
                        'status' => 'active'
                    ]);

                    $scraper = static::create($tempSource);
                    $results[$type] = $scraper ? 'available' : 'failed';
                } catch (\Exception $e) {
                    $results[$type] = 'error: ' . $e->getMessage();
                }
            }
        }

        return $results;
    }
}