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
// تم حذف متغير البريد الإلكتروني
$password = isset($_POST['password']) ? $_POST['password'] : '';
if (empty($name) || strlen($password) < 6) {
	http_response_code(400);
	echo json_encode(['error' => 'بيانات غير صحيحة']);
	exit;
}

try {
	$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
	$stmt->execute([$email]);
	if ($stmt->fetch()) {
		http_response_code(409);
		echo json_encode(['error' => 'البريد الإلكتروني مستخدم بالفعل']);
		exit;
	}
	$hashed = password_hash($password, PASSWORD_DEFAULT);
	$stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
	$stmt->execute([$name, $email, $hashed]);
	echo json_encode(['success' => true]);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['error' => 'خطأ في الخادم']);
}
