<?php
// Force logout and clear session
require_once 'db.php';

// Clear all session data
$_SESSION = array();

// Delete session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy session
session_destroy();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'تم تسجيل الخروج بنجاح. يرجى تسجيل الدخول مرة أخرى.'
]);
