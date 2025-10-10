<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

// دعم فحص حالة تسجيل الدخول فقط
if (isset($_GET['check']) && $_GET['check'] == '1') {
	if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
		echo json_encode(['logged_in' => true, 'user_id' => $_SESSION['user_id']]);
	} else {
		echo json_encode(['logged_in' => false]);
	}
	exit;
}

// CSRF token check (if implemented)
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
//     http_response_code(403);
//     echo json_encode(['error' => 'Invalid CSRF token']);
//     exit;
// }

// Debug: Log the request method
error_log("Login request method: " . $_SERVER['REQUEST_METHOD']);

// Input validation
// تم حذف متغير البريد الإلكتروني

// استقبال البيانات من JSON أو POST
$input = json_decode(file_get_contents('php://input'), true);
$email = isset($input['email']) ? trim($input['email']) : (isset($_POST['email']) ? trim($_POST['email']) : '');
$password = isset($input['password']) ? $input['password'] : (isset($_POST['password']) ? $_POST['password'] : '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
	http_response_code(400);
	echo json_encode(['error' => 'بيانات غير صحيحة']);
	exit;
}

try {
	$stmt = $pdo->prepare('SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1');
	$stmt->execute([$email]);
	$user = $stmt->fetch();
	if ($user && password_verify($password, $user['password'])) {
		// التحقق من حالة الحظر
		if ($user['role'] === 'banned') {
			http_response_code(403);
			echo json_encode([
				'error' => 'تم حظرك من استخدام الموقع',
				'banned' => true,
				'message' => 'تم حظر حسابك من قبل الإدارة. إذا كنت تعتقد أن هذا خطأ، يرجى التواصل مع الدعم الفني.'
			]);
			return;
		}
		
		$_SESSION['user_id'] = $user['id'];
		$_SESSION['user_name'] = $user['name'];
		$_SESSION['user_email'] = $email;
		$_SESSION['is_admin'] = ($user['role'] === 'admin') ? 1 : 0;
		
		echo json_encode([
			'success' => true,
			'message' => 'تم تسجيل الدخول بنجاح',
			'user_id' => $user['id'],
			'user_name' => $user['name'],
			'is_admin' => ($user['role'] === 'admin')
		]);
	} else {
		http_response_code(401);
		echo json_encode(['error' => 'اسم المستخدم أو كلمة المرور غير صحيحة']);
	}
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(['error' => 'خطأ في الخادم']);
}
