<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'طريقة الطلب غير مسموحة']);
    exit;
}
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}
if (!validate_csrf_token($csrf)) {
    http_response_code(400);
    echo json_encode(['error' => 'طلب غير صالح']);
    exit;
}
// بيانات أخرى
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$bio = isset($_POST['bio']) ? trim($_POST['bio']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';

// تحديث البيانات الأساسية
$stmt = $pdo->prepare('UPDATE users SET name = ?, bio = ?, phone = ?, gender = ? WHERE id = ?');
$stmt->execute([$name, $bio, $phone, $gender, $user_id]);

// معالجة رفع الصورة
$image_path = null;
if (!empty($_FILES['profile_image']['name'])) {
    $upload_dir = '../uploads/';
    $file_name = uniqid('avatar_') . '_' . basename($_FILES['profile_image']['name']);
    $target = $upload_dir . $file_name;
    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target)) {
        $image_path = 'uploads/' . $file_name;
        $stmt = $pdo->prepare('UPDATE users SET image = ? WHERE id = ?');
        $stmt->execute([$image_path, $user_id]);
    }
}

$response = ['success' => true];
if ($image_path) {
    $response['image'] = $image_path;
}
echo json_encode($response);
exit;
