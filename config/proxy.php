<?php
/**
 * MSHW-proxy - Central Configuration
 * Ephemeral-ready, GitHub Actions optimized
 */

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Core Proxy Settings
    |--------------------------------------------------------------------------
    */
    'proxy' => [
        'port' => (int) getenv('PROXY_PORT') ?: 8080,
        'timeout' => (int) getenv('PROXY_TIMEOUT') ?: 30,
        'max_concurrent' => (int) getenv('MAX_CONCURRENT') ?: 5,
        'max_response_size' => (int) getenv('MAX_RESPONSE_MB') ?: 50 * 1048576, // 50MB
        'stream_chunk_size' => 8192, // bytes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Bypass Strategy
    |--------------------------------------------------------------------------
    | Options: 'auto' | 'headers_only' | 'cookie_inject' | 'manual_fallback'
    */
    'cloudflare' => [
        'strategy' => getenv('CF_STRATEGY') ?: 'auto',
        'user_agents' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ],
        'headers' => [
            'sec-ch-ua' => '"Chromium";v="124", "Google Chrome";v="124", "Not-A.Brand";v="99"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'none',
            'sec-fetch-user' => '?1',
            'upgrade-insecure-requests' => '1',
        ],
        'retry' => [
            'max_attempts' => 3,
            'backoff_base' => 3, // seconds
            'backoff_max' => 12,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard & Auth
    |--------------------------------------------------------------------------
    */
    'dashboard' => [
        'enabled' => filter_var(getenv('DASHBOARD_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'path' => '/dashboard',
        'auth' => [
            'password_hash' => getenv('DASHBOARD_PASS'), // bcrypt hash recommended
            'session_lifetime' => 3600, // 1 hour
        ],
        'tty' => [
            'enabled' => filter_var(getenv('TTY_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            'port' => (int) getenv('TTY_PORT') ?: 7681,
            'auth' => getenv('TTY_AUTH') ?: getenv('DASHBOARD_PASS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cookie Management (RFC 6265)
    |--------------------------------------------------------------------------
    */
    'cookies' => [
        'storage' => 'memory', // 'memory' | 'apcu' (no Redis/DB for ephemeral)
        'strict_domain_match' => true,
        'auto_sync' => true, // changes in dashboard apply instantly
        'max_per_domain' => 50, // prevent memory bloat
    ],

    /*
    |--------------------------------------------------------------------------
    | Security & Headers
    |--------------------------------------------------------------------------
    */
    'security' => [
        'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; connect-src 'self' https:;",
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'referrer_policy' => 'no-referrer-when-downgrade',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging (Ephemeral-aware)
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'level' => getenv('LOG_LEVEL') ?: 'info', // debug | info | warning | error
        'max_entries' => 500, // RAM limit
        'websocket_stream' => filter_var(getenv('LOG_WS') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    ],
];
