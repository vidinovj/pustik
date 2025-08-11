<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JDIH Perpusnas API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to JDIH Perpusnas API and other legal
    | document sources. These settings control API endpoints, authentication,
    | rate limiting, and fallback mechanisms.
    |
    */

    'jdih_perpusnas' => [
        'base_url' => env('JDIH_PERPUSNAS_BASE_URL', 'https://api-jdih.perpusnas.go.id'),
        'api_version' => env('JDIH_PERPUSNAS_API_VERSION', 'v1'),
        'bearer_token' => env('JDIH_PERPUSNAS_BEARER_TOKEN'),
        'x_api_key' => env('JDIH_PERPUSNAS_X_API_KEY'),
        'timeout' => env('JDIH_PERPUSNAS_TIMEOUT', 30),
        'retry_attempts' => env('JDIH_PERPUSNAS_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('JDIH_PERPUSNAS_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting to respect API limitations and prevent
    | overwhelming government servers.
    |
    */

    'rate_limits' => [
        'jdih_perpusnas' => [
            'requests_per_minute' => env('JDIH_PERPUSNAS_RATE_LIMIT', 60),
            'requests_per_hour' => env('JDIH_PERPUSNAS_HOURLY_LIMIT', 1000),
            'burst_limit' => env('JDIH_PERPUSNAS_BURST_LIMIT', 10),
        ],
        'web_scraping' => [
            'requests_per_minute' => env('WEB_SCRAPING_RATE_LIMIT', 30),
            'delay_between_requests' => env('WEB_SCRAPING_DELAY', 2), // seconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for HTTP clients used for API calls and web scraping.
    |
    */

    'http_client' => [
        // More realistic User-Agent that rotates
        'user_agents' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        ],
        'user_agent' => env('LEGAL_DOCS_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'),
        'verify_ssl' => env('LEGAL_DOCS_VERIFY_SSL', true),
        'connect_timeout' => env('LEGAL_DOCS_CONNECT_TIMEOUT', 15),
        'read_timeout' => env('LEGAL_DOCS_READ_TIMEOUT', 45),
        'follow_redirects' => env('LEGAL_DOCS_FOLLOW_REDIRECTS', true),
        'max_redirects' => env('LEGAL_DOCS_MAX_REDIRECTS', 10),
        
        // Enhanced browser simulation
        'browser_simulation' => [
            'enabled' => env('LEGAL_DOCS_BROWSER_SIMULATION', true),
            'session_persistence' => env('LEGAL_DOCS_SESSION_PERSISTENCE', true),
            'javascript_delay' => env('LEGAL_DOCS_JS_DELAY', 2), // seconds to wait for JS
        ],
        
        // Anti-detection measures
        'anti_detection' => [
            'random_delays' => env('LEGAL_DOCS_RANDOM_DELAYS', true),
            'min_delay' => env('LEGAL_DOCS_MIN_DELAY', 1),
            'max_delay' => env('LEGAL_DOCS_MAX_DELAY', 5),
            'rotate_user_agents' => env('LEGAL_DOCS_ROTATE_UA', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching strategies for legal documents to reduce API calls
    | and improve performance.
    |
    */

    'cache' => [
        'default_ttl' => env('LEGAL_DOCS_CACHE_TTL', 3600), // 1 hour
        'metadata_ttl' => env('LEGAL_DOCS_METADATA_TTL', 86400), // 24 hours
        'search_results_ttl' => env('LEGAL_DOCS_SEARCH_CACHE_TTL', 1800), // 30 minutes
        'failed_requests_ttl' => env('LEGAL_DOCS_FAILED_CACHE_TTL', 300), // 5 minutes
        'prefix' => env('LEGAL_DOCS_CACHE_PREFIX', 'legal_docs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Sources Configuration
    |--------------------------------------------------------------------------
    |
    | Configure alternative sources when primary API fails.
    |
    */

    'fallback_sources' => [
        'jdih_kemlu' => [
            'base_url' => 'https://jdih.kemlu.go.id',
            'enabled' => env('ENABLE_JDIH_KEMLU_FALLBACK', true),
        ],
        'web_scraping' => [
            'enabled' => env('ENABLE_WEB_SCRAPING_FALLBACK', true),
            'respect_robots_txt' => env('WEB_SCRAPING_RESPECT_ROBOTS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring document URL permanence and availability.
    |
    */

    'url_monitoring' => [
        'enabled' => env('ENABLE_URL_MONITORING', true),
        'check_interval' => env('URL_CHECK_INTERVAL', 86400), // 24 hours
        'timeout' => env('URL_CHECK_TIMEOUT', 15),
        'retry_attempts' => env('URL_CHECK_RETRY_ATTEMPTS', 2),
        'notification_threshold' => env('URL_CHECK_NOTIFICATION_THRESHOLD', 3), // failures before notification
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for document metadata processing and standardization.
    |
    */

    'processing' => [
        'batch_size' => env('LEGAL_DOCS_BATCH_SIZE', 50),
        'queue_connection' => env('LEGAL_DOCS_QUEUE_CONNECTION', 'default'),
        'queue_name' => env('LEGAL_DOCS_QUEUE_NAME', 'legal-documents'),
        'max_processing_time' => env('LEGAL_DOCS_MAX_PROCESSING_TIME', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for legal document operations.
    |
    */

    'logging' => [
        'channel' => env('LEGAL_DOCS_LOG_CHANNEL', 'stack'),
        'level' => env('LEGAL_DOCS_LOG_LEVEL', 'info'),
        'log_api_requests' => env('LOG_LEGAL_DOCS_API_REQUESTS', false),
        'log_processing_details' => env('LOG_LEGAL_DOCS_PROCESSING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for document search and indexing.
    |
    */

    'search' => [
        'driver' => env('LEGAL_DOCS_SEARCH_DRIVER', 'database'), // database, algolia, meilisearch
        'index_name' => env('LEGAL_DOCS_SEARCH_INDEX', 'legal_documents'),
        'batch_size' => env('LEGAL_DOCS_SEARCH_BATCH_SIZE', 100),
        'auto_index' => env('LEGAL_DOCS_AUTO_INDEX', true),
    ],
];