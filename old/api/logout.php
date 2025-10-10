<?php
if (session_status() === PHP_SESSION_NONE) {
	require_once 'db.php';
	require_once 'security.php';
	ensure_session();
}
// Clear session data
$_SESSION = [];
// Delete session cookie
if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000,
		$params['path'], $params['domain'], $params['secure'], $params['httponly']
	);
}
// Destroy session
session_unset();
session_destroy();
// Regenerate session id
if (function_exists('session_regenerate_id')) session_regenerate_id(true);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
exit;
