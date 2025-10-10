<?php
// Database configuration with environment detection

// Detect environment
$isProduction = isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'bvize.com';
$isLocalhost = isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

if ($isProduction) {
    // Load production configuration
    if (file_exists(__DIR__ . '/production_config.php')) {
        $config = require __DIR__ . '/production_config.php';
        $host = $config['database']['host'];
        $dbname = $config['database']['dbname'];
        $username = $config['database']['username'];
        $password = $config['database']['password'];
    } else {
        // Fallback production settings
        $host = 'localhost';
        $dbname = 'bvize_games_accounts';
        $username = 'bvize_dbuser';
        $password = 'your_production_password';
    }
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
} elseif ($isLocalhost) {
    // Local XAMPP environment
    $host = 'localhost';
    $dbname = 'games_accounts';
    $username = 'root';
    $password = '';
    $socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
    $dsn = "mysql:unix_socket=$socket;dbname=$dbname;charset=utf8mb4";
} else {
    // Default fallback (standard localhost)
    $host = 'localhost';
    $dbname = 'games_accounts';
    $username = 'root';
    $password = '';
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
}

try {
    // Create PDO connection
    $pdo = new PDO($dsn, $username, $password);
    
    // Set PDO attributes
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Set charset to UTF-8
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
} catch(PDOException $e) {
    // Log error with environment info
    $env = $isProduction ? 'production' : ($isLocalhost ? 'localhost' : 'default');
    error_log("Database connection failed in $env environment: " . $e->getMessage());
    
    // Set global variable to indicate database error
    $GLOBALS['db_connection_error'] = true;
    $GLOBALS['db_error_message'] = $e->getMessage();
    
    // Don't die here - let individual API files handle the error
    $pdo = null;
}

// Session configuration for different environments
if ($isProduction) {
    // Set CORS headers for production
    if (isset($config) && isset($config['additional_headers'])) {
        foreach ($config['additional_headers'] as $header => $value) {
            header("$header: $value");
        }
    } else {
        // Fallback CORS headers
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Origin: https://bvize.com');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }
    
    // Production session settings
    if (isset($config) && isset($config['session'])) {
        $sessionConfig = $config['session'];
        ini_set('session.cookie_secure', $sessionConfig['cookie_secure'] ? '1' : '0');
        ini_set('session.cookie_httponly', $sessionConfig['cookie_httponly'] ? '1' : '0');
        ini_set('session.cookie_samesite', $sessionConfig['cookie_samesite']);
        ini_set('session.cookie_domain', $sessionConfig['cookie_domain']);
        ini_set('session.cookie_path', $sessionConfig['cookie_path']);
        ini_set('session.cookie_lifetime', $sessionConfig['cookie_lifetime']);
    } else {
        // Fallback production settings
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'None');
        ini_set('session.cookie_domain', 'bvize.com');
        ini_set('session.cookie_path', '/');
    }
    
    // Additional production session security
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    
} else {
    // Local development settings
    ini_set('session.cookie_secure', '0'); // HTTP allowed
    ini_set('session.cookie_httponly', '1'); // Prevent XSS
    ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}