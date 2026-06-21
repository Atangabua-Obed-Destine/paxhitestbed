<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DDoS Protection Settings
    |--------------------------------------------------------------------------
    |
    | These settings control the DDoS protection middleware behavior
    |
    */

    'ddos' => [
        'enabled' => true,
        
        // Rate limiting - requests per minute before blocking
        'max_requests_per_minute' => 500,  // Increased from 100 to allow normal browsing
        'block_duration_minutes' => 10,     // Reduced from 15 for shorter lockouts
        
        // Suspicious activity detection
        'suspicious_pattern_enabled' => true,
        'suspicious_strikes_before_block' => 3,  // Require 3 violations before blocking
        'suspicious_strikes_window_minutes' => 15, // Within 15 minutes
        'suspicious_block_hours' => 2,      // Reduced from 24 hours
        
        // Bot detection
        'bot_detection_enabled' => true,
        'bot_request_limit' => 150,         // Increased from 20 to be less aggressive
        'bot_block_duration_hours' => 1,
        
        // Exclude patterns from suspicious activity checks
        'exclude_paths' => [
            'admin/security/*',  // Don't flag admin security pages
            'api/*',             // API endpoints may have special chars
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SQL Injection Protection
    |--------------------------------------------------------------------------
    |
    | Settings for SQL injection detection and prevention
    |
    */

    'sql_injection' => [
        'enabled' => true,
        'log_attempts' => true,
        'block_on_detection' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Global rate limiting settings
    |
    */

    'rate_limiting' => [
        'api_requests_per_minute' => 30,
        'web_requests_per_minute' => 100,
        'login_attempts' => 5,
        'login_lockout_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | HTTP security headers configuration
    |
    */

    'headers' => [
        'x-frame-options' => 'SAMEORIGIN',
        'x-content-type-options' => 'nosniff',
        'x-xss-protection' => '1; mode=block',
        'referrer-policy' => 'strict-origin-when-cross-origin',
        'permissions-policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelisted IPs
    |--------------------------------------------------------------------------
    |
    | IPs that bypass certain security checks (use with caution)
    |
    */

    'whitelisted_ips' => [
        // '127.0.0.1',
        // Add trusted IPs here
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklisted IPs
    |--------------------------------------------------------------------------
    |
    | IPs that are permanently blocked
    |
    */

    'blacklisted_ips' => [
        // Add malicious IPs here
    ],

];
