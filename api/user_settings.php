<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => 'طريقة الطلب غير مسموحة']);
	exit;
}

$user_id = intval($_SESSION['user_id']);
$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!validate_csrf_token($csrf)) {
	http_response_code(400);
	echo json_encode(['error' => 'طلب غير صالح']);
	exit;
}

// Discover which optional columns exist in the users table so SQL doesn't fail
try {
	$colsStmt = $pdo->query("DESCRIBE users");
	$cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['error' => 'فشل في الوصول لهيكلة قاعدة البيانات']);
	exit;
}

$allowed = ['name','bio','phone','age','gender','image'];
$fields = [];
$params = [];

foreach ($allowed as $field) {
	if (!in_array($field, $cols)) continue; // skip if DB doesn't have the column
	if ($field === 'age') {
		if (isset($_POST['age']) && $_POST['age'] !== '') {
			$val = intval($_POST['age']);
			$fields[] = "`$field` = ?";
			$params[] = $val;
		}
	} else {
		if (isset($_POST[$field]) && $_POST[$field] !== '') {
			$val = trim($_POST[$field]);
			$fields[] = "`$field` = ?";
			$params[] = $val;
		}
	}
}

if (count($fields) === 0) {
	echo json_encode(['success' => true, 'message' => 'لا تغييرات']);
	exit;
}

$params[] = $user_id;
$sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
try {
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	echo json_encode(['success' => true, 'message' => 'تم تحديث البيانات']);
	exit;
} catch (Exception $e) {
	error_log('user_settings.php error: ' . $e->getMessage());
	http_response_code(500);
	echo json_encode(['error' => 'فشل في تحديث البيانات']);
	exit;
}

