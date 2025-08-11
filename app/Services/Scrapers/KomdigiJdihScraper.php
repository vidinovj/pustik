<?php

namespace App\Services\Scrapers;

use App\Models\DocumentSource;
use Illuminate\Support\Facades\Log;
use DOMDocument;

class KomdigiJdihScraper extends BaseScraper
{
    public function scrape(): array
    {
        Log::channel('legal-documents')->info("Komdigi JDIH scraper not yet implemented");
        return [];
    }

    protected function extractDocumentData(DOMDocument $dom, string $url): ?array
    {
        // TODO: Implement Komdigi data extraction
        return null;
    }

    protected function getDocumentUrls(): array
    {
        // TODO: Implement Komdigi URL discovery
        return [];
    }
}
