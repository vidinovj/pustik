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
            
            // Rotate User-Agent if enabled
            $userAgent = $httpConfig['user_agent'];
            if (!empty($httpConfig['user_agents']) && $httpConfig['anti_detection']['rotate_user_agents']) {
                $userAgent = $httpConfig['user_agents'][array_rand($httpConfig['user_agents'])];
            }
            
            return Http::withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Cache-Control' => 'max-age=0',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                    'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                    'Sec-Ch-Ua-Mobile' => '?0',
                    'Sec-Ch-Ua-Platform' => '"macOS"',
                ])
                ->timeout($httpConfig['read_timeout'])
                ->connectTimeout($httpConfig['connect_timeout'])
                ->withOptions([
                    'verify' => $httpConfig['verify_ssl'],
                    'allow_redirects' => [
                        'max' => $httpConfig['max_redirects'],
                        'strict' => false,
                        'referer' => true,
                        'track_redirects' => true,
                        'protocols' => ['http', 'https'],
                    ],
                    'cookies' => true, // Enable cookie jar
                ]);
        });

        Http::macro('sessionAwareScraper', function () {
            $httpConfig = config('legal_documents.http_client');
            
            return Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                ])
                ->timeout(60)
                ->connectTimeout(15)
                ->withOptions([
                    'verify' => $httpConfig['verify_ssl'],
                    'allow_redirects' => [
                        'max' => 10,
                        'strict' => false,
                        'referer' => true,
                        'track_redirects' => true,
                        'protocols' => ['http', 'https'],
                    ],
                    'cookies' => true,
                    'http_errors' => false, // Don't throw on 4xx/5xx
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