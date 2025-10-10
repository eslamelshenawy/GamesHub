<?php
// Test session functionality
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'check';

$result = [
    'action' => $action,
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_save_path' => session_save_path(),
    'session_name' => session_name(),
    'cookie_params' => session_get_cookie_params(),
    'php_version' => PHP_VERSION,
];

if ($action === 'set') {
    // Set test session data
    $_SESSION['test_user_id'] = 12345;
    $_SESSION['test_time'] = time();
    $_SESSION['test_data'] = 'This is test data';

    // Force write
    session_write_close();

    $result['message'] = 'Session data set successfully';
    $result['session_data'] = [
        'test_user_id' => 12345,
        'test_time' => time(),
        'test_data' => 'This is test data'
    ];

} elseif ($action === 'get') {
    // Get session data
    $result['session_data'] = $_SESSION;
    $result['has_test_data'] = isset($_SESSION['test_user_id']);

} elseif ($action === 'check') {
    // Check session configuration
    $result['session_data'] = $_SESSION;
    $result['cookies'] = $_COOKIE;

    // Check if session path is writable
    $save_path = session_save_path();
    if (empty($save_path)) {
        $save_path = sys_get_temp_dir();
    }

    $result['save_path_exists'] = file_exists($save_path);
    $result['save_path_writable'] = is_writable($save_path);
    $result['save_path_readable'] = is_readable($save_path);

    // List session files
    if (file_exists($save_path) && is_readable($save_path)) {
        $files = glob($save_path . '/sess_*');
        $result['session_files_count'] = count($files);
        $result['session_files_sample'] = array_slice($files, 0, 5);
    }
}

// Add PHP ini settings
$result['php_ini'] = [
    'session.save_handler' => ini_get('session.save_handler'),
    'session.save_path' => ini_get('session.save_path'),
    'session.use_cookies' => ini_get('session.use_cookies'),
    'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
    'session.cookie_path' => ini_get('session.cookie_path'),
    'session.cookie_domain' => ini_get('session.cookie_domain'),
    'session.cookie_secure' => ini_get('session.cookie_secure'),
    'session.cookie_httponly' => ini_get('session.cookie_httponly'),
    'session.cookie_samesite' => ini_get('session.cookie_samesite'),
    'session.gc_probability' => ini_get('session.gc_probability'),
    'session.gc_divisor' => ini_get('session.gc_divisor'),
    'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
