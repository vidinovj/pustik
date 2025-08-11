<?php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use Illuminate\Support\Facades\Log;

class ScraperFactory
{
    /**
     * Available scraper classes mapped to source names.
     */
    protected static array $scrapers = [
        'peraturan_go_id' => FixedPeraturanScraper::class,
        'jdih_kemlu' => JdihKemluScraper::class,
        'jdih_perpusnas_api' => JdihPerpusnasApiScraper::class,
        'jdihn' => JdihnScraper::class,
        'bpk_jdih' => BpkJdihScraper::class,
        'kominfo_jdih' => KominfoJdihScraper::class,
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
}

// Placeholder classes for other scrapers (implement these later)
class JdihnScraper extends BaseScraper
{
    public function scrape(): array
    {
        // TODO: Implement JDIHN scraper
        Log::channel('legal-documents')->info("JDIHN scraper not yet implemented");
        return [];
    }

    protected function extractDocumentData(\DOMDocument $dom, string $url): ?array
    {
        // TODO: Implement JDIHN data extraction
        return null;
    }

    protected function getDocumentUrls(): array
    {
        // TODO: Implement JDIHN URL discovery
        return [];
    }
}

class BpkJdihScraper extends BaseScraper
{
    public function scrape(): array
    {
        // TODO: Implement BPK JDIH scraper
        Log::channel('legal-documents')->info("BPK JDIH scraper not yet implemented");
        return [];
    }

    protected function extractDocumentData(\DOMDocument $dom, string $url): ?array
    {
        // TODO: Implement BPK data extraction
        return null;
    }

    protected function getDocumentUrls(): array
    {
        // TODO: Implement BPK URL discovery
        return [];
    }
}

class KominfoJdihScraper extends BaseScraper
{
    public function scrape(): array
    {
        // TODO: Implement Kominfo JDIH scraper
        Log::channel('legal-documents')->info("Kominfo JDIH scraper not yet implemented");
        return [];
    }

    protected function extractDocumentData(\DOMDocument $dom, string $url): ?array
    {
        // TODO: Implement Kominfo data extraction
        return null;
    }

    protected function getDocumentUrls(): array
    {
        // TODO: Implement Kominfo URL discovery
        return [];
    }
}