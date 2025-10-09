<?php
// Central security helpers: session management and CSRF utilities
function get_current_user_id()
{
	ensure_session();
	if (isset($_SESSION['user_id']) && intval($_SESSION['user_id']) > 0) {
		return intval($_SESSION['user_id']);
	}
	return 0;
}
if (session_status() === PHP_SESSION_NONE) {
	// do not start session here unconditionally; allow callers to control timing
	// but provide a helper to ensure session is started when needed
}

function ensure_session()
{
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
}

// Generate CSRF token if not set
function ensure_csrf_token()
{
	ensure_session();
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
}

function get_csrf_token()
{
	ensure_csrf_token();
	return $_SESSION['csrf_token'];
}

function validate_csrf_token($token)
{
	ensure_session();
	return isset($_SESSION['csrf_token']) && $token && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_or_die($token)
{
	if (!validate_csrf_token($token)) {
		http_response_code(403);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['error' => 'طلب غير صالح (CSRF)']);
		exit;
	}
}

function require_login_or_die()
{
	ensure_session();
	if (!isset($_SESSION['user_id']) || intval($_SESSION['user_id']) <= 0) {
		http_response_code(401);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
		exit;
	}
}