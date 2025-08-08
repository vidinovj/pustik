<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

class LegalDocumentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the legal document configuration
        $this->mergeConfigFrom(__DIR__.'/../../config/legal_documents.php', 'legal_documents');

        // Register HTTP client macro for legal document APIs
        $this->registerHttpClientMacros();

        // Register singleton services (will be implemented in later steps)
        // $this->app->singleton(LegalDocumentService::class);
        // $this->app->singleton(JdihPerpusnasClient::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration file
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/legal_documents.php' => config_path('legal_documents.php'),
            ], 'legal-documents-config');
        }
    }

    /**
     * Register HTTP client macros for legal document services.
     */
    private function registerHttpClientMacros(): void
    {
        // JDIH Perpusnas API client macro
        Http::macro('jdihPerpusnas', function () {
            $config = config('legal_documents.jdih_perpusnas');
            $httpConfig = config('legal_documents.http_client');
            
            return Http::baseUrl($config['base_url'])
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $config['bearer_token'],
                    'x-api-key' => $config['x_api_key'],
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'User-Agent' => $httpConfig['user_agent'],
                ])
                ->timeout($config['timeout'])
                ->connectTimeout($httpConfig['connect_timeout'])
                ->retry($config['retry_attempts'], $config['retry_delay'])
                ->withOptions([
                    'verify' => $httpConfig['verify_ssl'],
                    'allow_redirects' => [
                        'max' => $httpConfig['max_redirects'],
                        'strict' => true,
                        'referer' => true,
                        'protocols' => ['http', 'https'],
                    ],
                ]);
        });

        // General legal document web scraping client macro
        Http::macro('legalDocsScraper', function () {
            $httpConfig = config('legal_documents.http_client');
            
            return Http::withHeaders([
                    'User-Agent' => $httpConfig['user_agent'],
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->timeout($httpConfig['read_timeout'])
                ->connectTimeout($httpConfig['connect_timeout'])
                ->withOptions([
                    'verify' => $httpConfig['verify_ssl'],
                    'allow_redirects' => [
                        'max' => $httpConfig['max_redirects'],
                        'strict' => false,
                        'referer' => true,
                        'protocols' => ['http', 'https'],
                    ],
                ]);
        });

        // URL monitoring client macro
        Http::macro('urlChecker', function () {
            $config = config('legal_documents.url_monitoring');
            
            return Http::withHeaders([
                    'User-Agent' => config('legal_documents.http_client.user_agent'),
                ])
                ->timeout($config['timeout'])
                ->connectTimeout(5)
                ->retry($config['retry_attempts'], 1000)
                ->withOptions([
                    'verify' => config('legal_documents.http_client.verify_ssl'),
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'referer' => false,
                        'protocols' => ['http', 'https'],
                    ],
                ]);
        });
    }
}