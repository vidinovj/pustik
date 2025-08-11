<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class QuickSiteCheck extends Command
{
    protected $signature = 'legal-docs:quick-check {url=https://jdih.kemlu.go.id}';
    protected $description = 'Quick check for sitemap, robots.txt, and common endpoints';

    public function handle(): int
    {
        $baseUrl = rtrim($this->argument('url'), '/');
        
        $this->info("🔍 Quick Site Check: {$baseUrl}");
        $this->newLine();

        // Check robots.txt
        $this->checkUrl($baseUrl . '/robots.txt', 'Robots.txt');
        
        // Check sitemaps
        $sitemapUrls = [
            '/sitemap.xml',
            '/sitemap_index.xml',
            '/sitemap/sitemap.xml',
        ];
        
        foreach ($sitemapUrls as $sitemap) {
            $this->checkUrl($baseUrl . $sitemap, 'Sitemap: ' . $sitemap);
        }
        
        // Check if it's WordPress/CMS
        $cmsChecks = [
            '/wp-admin' => 'WordPress',
            '/wp-content' => 'WordPress',
            '/administrator' => 'Joomla',
            '/drupal' => 'Drupal',
        ];
        
        foreach ($cmsChecks as $path => $cms) {
            $response = Http::timeout(5)->get($baseUrl . $path);
            if ($response->status() !== 404) {
                $this->info("📱 Detected CMS: {$cms}");
            }
        }

        return Command::SUCCESS;
    }

    private function checkUrl(string $url, string $description): void
    {
        try {
            $response = Http::timeout(10)->get($url);
            
            if ($response->successful()) {
                $this->info("✅ {$description}: {$url}");
                
                // Save content for inspection
                $content = $response->body();
                if (strlen($content) > 100) {
                    $filename = 'quick_check_' . basename(parse_url($url, PHP_URL_PATH)) . '_' . date('His') . '.txt';
                    file_put_contents(storage_path("logs/{$filename}"), $content);
                    $this->line("   💾 Content saved to: storage/logs/{$filename}");
                }
            } else {
                $this->line("❌ {$description}: HTTP {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->line("❌ {$description}: Error - {$e->getMessage()}");
        }
    }
}