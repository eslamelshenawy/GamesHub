<?php
require_once 'db.php';
if (session_status() === PHP_SESSION_NONE) {
    require_once 'security.php';
    ensure_session();
}
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}
$user_id = $_SESSION['user_id'];
$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
if ($account_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'رقم الإعلان غير صحيح']);
    exit;
}
// تحقق إذا كان الإعلان مضاف مسبقاً
$stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND account_id = ?');
$stmt->execute([$user_id, $account_id]);
if ($stmt->fetch()) {
    echo json_encode(['message' => 'الإعلان مضاف بالفعل']);
    exit;
}
// إضافة الإعلان للمفضلة
$stmt = $pdo->prepare('INSERT INTO favorites (user_id, account_id, created_at) VALUES (?, ?, NOW())');
$stmt->execute([$user_id, $account_id]);
echo json_encode(['success' => true, 'message' => 'تمت إضافة الإعلان إلى المفضلة']);
exit;
