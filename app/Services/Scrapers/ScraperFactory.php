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
        'peraturan_go_id' => FixedPeraturanScraper::class,
        'peraturan_go_id_session_aware' => SessionAwarePeraturanScraper::class,
        'peraturan_go_id_improved' => ImprovedPeraturanScraper::class,
        'bpk' => BpkScraper::class,
        'peraturan_bpk_go_id' => BpkScraper::class,
        'kemlu' => KemluTikScraper::class,
        'jdih_perpusnas_api' => JdihPerpusnasApiScraper::class,
        'komdigi' => KomdigiScraper::class,
        'kemenko' => KemenkoScraper::class,
        'multi_source' => MultiSourceLegalScraper::class,
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
            '/peraturan\.go\.id/' => 'peraturan_go_id_improved',
            '/kemlu\.go\.id/' => 'kemlu',
            '/jdih\.kemenko\.go\.id/' => 'kemenko',
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
        $recommendations = [
            'undang-undang' => 'bpk',
            'peraturan pemerintah' => 'bpk', 
            'peraturan presiden' => 'bpk',
            'peraturan menteri' => 'peraturan_go_id_improved',
            'keputusan presiden' => 'bpk',
        ];

        $type = strtolower($documentType);
        
        foreach ($recommendations as $pattern => $scraper) {
            if (str_contains($type, $pattern)) {
                return $scraper;
            }
        }

        return 'bpk'; // Default to BPK for most legal documents
    }

    /**
     * Test all available scrapers with sample URLs.
     */
    public static function testAllScrapers(): array
    {
        $testUrls = [
            'bpk' => 'https://peraturan.bpk.go.id/Details/274494/uu-no-11-tahun-2008',
            'peraturan_go_id_improved' => 'https://peraturan.go.id/id/uu-no-19-tahun-2016',
            'kemlu' => 'https://kemlu.go.id/',
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