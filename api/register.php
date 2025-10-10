<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
	require_once 'security.php';
	ensure_session();
}
header('Content-Type: application/json; charset=utf-8');

// CSRF token check (if implemented)
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
//     http_response_code(403);
//     echo json_encode(['error' => 'Invalid CSRF token']);
//     exit;
// }

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validate input
if (empty($name) || empty($email) || strlen($password) < 6) {
	http_response_code(400);
	echo json_encode(['error' => 'بيانات غير صحيحة']);
	exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	http_response_code(400);
	echo json_encode(['error' => 'البريد الإلكتروني غير صالح']);
	exit;
}

try {
	// Check if email already exists
	$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
	$stmt->execute([$email]);
	if ($stmt->fetch()) {
		http_response_code(409);
		echo json_encode(['error' => 'البريد الإلكتروني مستخدم بالفعل']);
		exit;
	}

	// Create new user
	$hashed = password_hash($password, PASSWORD_DEFAULT);
	$stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
	$stmt->execute([$name, $email, $hashed]);
	$user_id = $pdo->lastInsertId();

	// Automatically log in the user after registration
	$_SESSION['user_id'] = $user_id;
	$_SESSION['user_name'] = $name;
	$_SESSION['user_email'] = $email;

	// Force PHP to write session data immediately
	session_write_close();

	echo json_encode(['success' => true, 'user_id' => $user_id]);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['error' => 'خطأ في الخادم']);
}
