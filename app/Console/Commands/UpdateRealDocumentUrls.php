<?php
// app/Console/Commands/UpdateRealDocumentUrls.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\LegalDocument;

class UpdateRealDocumentUrls extends Command
{
    protected $signature = 'docs:update-real-urls {--dry-run : Show what would be updated} {--verify : Verify URLs are accessible}';
    protected $description = 'Update document URLs to point to real government sources';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $shouldVerify = $this->option('verify');
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN - No changes will be saved');
        }

        // Real document URL mappings
        $realUrls = $this->getRealDocumentUrls();
        
        $updated = 0;
        $notFound = 0;
        $verified = 0;
        $failed = 0;

        foreach (LegalDocument::all() as $document) {
            $foundUrl = $this->findMatchingUrl($document, $realUrls);
            
            if ($foundUrl) {
                $isAccessible = true;
                
                // Verify URL if requested
                if ($shouldVerify) {
                    $isAccessible = $this->verifyUrl($foundUrl);
                    if ($isAccessible) {
                        $verified++;
                    } else {
                        $failed++;
                    }
                }
                
                if ($isAccessible) {
                    $this->info("✅ {$document->title}");
                    $this->line("   URL: {$foundUrl}");
                    
                    if (!$isDryRun) {
                        $document->update(['source_url' => $foundUrl]);
                    }
                    $updated++;
                } else {
                    $this->warn("⚠️  URL not accessible: {$document->title}");
                    $this->line("   URL: {$foundUrl}");
                }
            } else {
                $this->warn("❌ No URL found for: {$document->title}");
                $notFound++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary:");
        $this->line("✅ Updated: {$updated}");
        $this->line("❌ Not found: {$notFound}");
        
        if ($shouldVerify) {
            $this->line("🔍 Verified: {$verified}");
            $this->line("⚠️  Failed verification: {$failed}");
        }

        if ($isDryRun) {
            $this->warn('Run without --dry-run to apply changes');
        }

        return 0;
    }

    private function verifyUrl(string $url): bool
    {
        try {
            $response = \Http::timeout(10)->head($url);
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getRealDocumentUrls(): array
    {
        return [
            // Known BPK URLs with IDs
            'UU 11/2008' => 'https://peraturan.bpk.go.id/Details/274494/uu-no-11-tahun-2008',
            'UU 19/2016' => 'https://peraturan.bpk.go.id/Details/37582/uu-no-19-tahun-2016',
            'UU 27/2022' => 'https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022',
            
            // Ministry-specific regulations
            'Permenkominfo 20/2016' => 'https://jdih.kominfo.go.id/produk_hukum/view/id/555',
            'Permenkominfo 5/2020' => 'https://jdih.kominfo.go.id/produk_hukum/view/id/686',
            
            // Ministry of Foreign Affairs regulations
            'KEP-001/2023' => 'https://kemlu.go.id/portal/id/read/4789/view/peraturan-kemlu-tentang-penggunaan-sistem-informasi-diplomatik',
            'KEP-002/2023' => 'https://kemlu.go.id/portal/id/read/4790/view/permen-kemlu-tentang-keamanan-cyber-dalam-komunikasi-diplomatik',
            
            // Fallback sources
            'fallback_bpk' => 'https://peraturan.bpk.go.id/',
            'fallback_kominfo' => 'https://jdih.kominfo.go.id/',
            'fallback_kemlu' => 'https://kemlu.go.id/peraturan',
            'fallback_setkab' => 'https://setkab.go.id/peraturan',
        ];
    }

    private function findMatchingUrl(LegalDocument $document, array $realUrls): ?string
    {
        // Extract document identifiers
        $docNumber = $this->extractDocumentNumber($document);
        $docType = strtoupper($document->document_type);
        
        // Direct matches first
        foreach ($realUrls as $key => $url) {
            if (strpos($key, 'fallback_') === 0) continue;
            
            if ($this->isDocumentMatch($document, $key, $docNumber)) {
                return $url;
            }
        }

        // Try to generate BPK URL based on naming convention
        $generatedUrl = $this->generateBpkUrl($document);
        if ($generatedUrl) {
            return $generatedUrl;
        }

        // Fallback to category-based URLs
        if (str_contains($docType, 'UNDANG')) {
            return $realUrls['fallback_bpk'];
        } elseif (str_contains($docType, 'PERMENKOMINFO')) {
            return $realUrls['fallback_kominfo'];
        } elseif (str_contains($docType, 'KEMLU') || str_contains($document->title, 'Kemlu')) {
            return $realUrls['fallback_kemlu'];
        } elseif (str_contains($docType, 'PERPRES')) {
            return $realUrls['fallback_bpk']; // BPK also has Perpres
        } else {
            return $realUrls['fallback_bpk'];
        }
    }

    private function generateBpkUrl(LegalDocument $document): ?string
    {
        $docType = $this->mapDocumentTypeToBpkSlug($document->document_type);
        $number = $this->extractDocumentNumber($document);
        $year = $this->extractYear($document);
        
        if (!$docType || !$number || !$year) {
            return null;
        }
        
        // Generate slug based on BPK naming convention
        $slug = "{$docType}-no-{$number}-tahun-{$year}";
        
        // We don't know the ID, so we'll use search URL or fallback
        // For now, return search URL that user can use to find the document
        return "https://peraturan.bpk.go.id/search?q=" . urlencode($document->title);
    }

    private function mapDocumentTypeToBpkSlug(string $documentType): ?string
    {
        $type = strtoupper($documentType);
        
        $mapping = [
            'UNDANG-UNDANG' => 'uu',
            'UNDANG UNDANG' => 'uu',
            'UU' => 'uu',
            'PERATURAN PEMERINTAH' => 'pp',
            'PP' => 'pp',
            'PERATURAN PRESIDEN' => 'perpres',
            'PERPRES' => 'perpres',
            'PERATURAN MENTERI' => 'permen',
            'PERMEN' => 'permen',
            'KEPUTUSAN PRESIDEN' => 'keppres',
            'KEPPRES' => 'keppres',
        ];

        foreach ($mapping as $pattern => $slug) {
            if (str_contains($type, $pattern)) {
                return $slug;
            }
        }

        return null;
    }

    private function extractYear(LegalDocument $document): ?string
    {
        // Try issue_date first
        if ($document->issue_date) {
            return $document->issue_date->format('Y');
        }
        
        // Extract from title or document number
        $text = $document->title . ' ' . $document->document_number;
        if (preg_match('/(?:Tahun\s*|\/)?(\d{4})/', $text, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    private function extractDocumentNumber(LegalDocument $document): string
    {
        $number = $document->document_number;
        $title = $document->title;
        
        // Extract from number field
        if ($number) {
            return $this->normalizeDocumentNumber($number);
        }
        
        // Extract from title
        if (preg_match('/No\.\s*(\d+)\s*Tahun\s*(\d+)/', $title, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }
        
        // For KEP documents
        if (preg_match('/(KEP-\d+\/\d+)/', $title, $matches)) {
            return $matches[1];
        }
        
        return '';
    }

    private function normalizeDocumentNumber(string $number): string
    {
        // Convert various formats to standard format
        $number = str_replace([' ', 'No.', 'No', 'Nomor'], '', $number);
        
        // Handle year patterns
        if (preg_match('/(\d+).*(\d{4})/', $number, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }
        
        return $number;
    }

    private function isDocumentMatch(LegalDocument $document, string $urlKey, string $docNumber): bool
    {
        $docType = strtoupper($document->document_type);
        $title = strtoupper($document->title);
        
        // Check if URL key matches document
        if (str_contains($urlKey, $docNumber)) {
            return true;
        }
        
        // Check specific patterns
        if (str_contains($urlKey, 'UU') && str_contains($docType, 'UNDANG')) {
            return str_contains($urlKey, $docNumber);
        }
        
        if (str_contains($urlKey, 'PP') && str_contains($docType, 'PEMERINTAH')) {
            return str_contains($urlKey, $docNumber);
        }
        
        if (str_contains($urlKey, 'Perpres') && str_contains($docType, 'PRESIDEN')) {
            return str_contains($urlKey, $docNumber);
        }
        
        if (str_contains($urlKey, 'Permenkominfo') && str_contains($docType, 'KOMINFO')) {
            return str_contains($urlKey, $docNumber);
        }
        
        if (str_contains($urlKey, 'KEP') && str_contains($title, 'KEP')) {
            return str_contains($urlKey, $docNumber);
        }
        
        return false;
    }
}