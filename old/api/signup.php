<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
$age = isset($_POST['age']) ? intval($_POST['age']) : 0;
$gender = isset($_POST['gender']) ? $_POST['gender'] : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
// CSRF check
$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (!validate_csrf_token($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'طلب غير صالح (CSRF)']);
    exit;
}

// Optional return target
$raw_return = isset($_POST['return']) ? $_POST['return'] : null;

function safe_return_target_signup($r) {
    if (!$r) return null;
    $r = trim(urldecode($r));
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $r)) return null;
    if (strpos($r, '/') !== 0) return null;
    if (strpos($r, '//') !== false) return null;
    if (preg_match('/login\.html$|signup\.html$/i', $r)) return null;
    return $r;
}

// تحقق من صحة البيانات
//if (empty($username) || strlen($password) < 6 || $password !== $confirm || $age < 1 || empty($gender) || empty($phone)) {
   // http_response_code(400);
    //echo json_encode(['error' => 'بيانات غير صحيحة']);
   // exit;}

// Basic server-side validation
if ($username === '' || strlen($password) < 6 || $password !== $confirm || $age < 1 || empty($gender) || !preg_match('/^\d{8,15}$/', $phone) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'بيانات غير صحيحة، يرجى التحقق من الحقول']);
    exit;
}

try {
    // تحقق من عدم تكرار رقم الهاتف والإيميل
    $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = ? OR email = ? LIMIT 1');
    $stmt->execute([$phone, $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'رقم الهاتف أو الإيميل مستخدم بالفعل']);
        exit;
    }
    
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name, password, age, gender, phone, email) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $hashed, $age, $gender, $phone, $email]);
    $user_id = $pdo->lastInsertId();
    // تسجيل دخول المستخدم تلقائياً
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $username;
    $_SESSION['user_age'] = $age;
    $_SESSION['user_gender'] = $gender;
    $_SESSION['user_phone'] = $phone;
    $_SESSION['user_email'] = $email;
    $target = safe_return_target_signup($raw_return) ?: 'myaccount.html';
    echo json_encode(['success' => true, 'redirect' => $target]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم']);
}
?>