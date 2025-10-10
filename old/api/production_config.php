<?php
// Production configuration file
// This file should be updated with your actual production database credentials

// Production database settings
$production_config = [
    'database' => [
        'host' => 'localhost',
        'dbname' => 'bvize_games_accounts', // Replace with your actual database name
        'username' => 'bvize_bvize',   // Replace with your actual username
        'password' => 'Mm2025@ts2006'    // Replace with your actual password
    ],
    
    'session' => [
        'cookie_secure' => true,     // HTTPS only
        'cookie_httponly' => true,   // Prevent XSS
        'cookie_samesite' => 'None', // Required for cross-origin requests
        'cookie_domain' => 'bvize.com', // Exact domain match (no dot prefix)
        'cookie_path' => '/',        // Site-wide cookies
        'cookie_lifetime' => 86400   // 24 hours
    ],
    
    'security' => [
        'cors_origin' => 'https://bvize.com',
        'allowed_hosts' => ['bvize.com', 'www.bvize.com']
    ],
    
    // Additional cookie settings for production
    'additional_headers' => [
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Origin' => 'https://bvize.com',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With'
    ]
];

// Instructions for deployment:
// 1. Update the database credentials above with your actual production values
// 2. Make sure your hosting provider supports the session settings
// 3. Ensure HTTPS is properly configured on your domain
// 4. Test the cookie functionality after deployment

return $production_config;
?>