<?php
require_once 'db.php';
require_once 'security.php';
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
$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
$game_name = isset($_POST['game_name']) ? trim($_POST['game_name']) : '';
$price = isset($_POST['price']) ? trim($_POST['price']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}
if ($account_id <= 0 || !validate_csrf_token($csrf)) {
    http_response_code(400);
    echo json_encode(['error' => 'طلب غير صالح']);
    exit;
}

// تحقق ملكية الإعلان
$stmt = $pdo->prepare('SELECT user_id FROM accounts WHERE id = ?');
$stmt->execute([$account_id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'الإعلان غير موجود']);
    exit;
}
if (intval($row['user_id']) !== $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'ليس لديك صلاحية لتعديل هذا الإعلان']);
    exit;
}

// تحديث الحقول المرسلة فقط
$fields = [];
$params = [];
if ($game_name !== '') { $fields[] = 'game_name = ?'; $params[] = $game_name; }
if ($price !== '') { $fields[] = 'price = ?'; $params[] = $price; }
if ($description !== '') { $fields[] = 'description = ?'; $params[] = $description; }

if (count($fields) === 0) {
    echo json_encode(['success' => true, 'message' => 'لا تغييرات']);
    exit;
}

$params[] = $account_id;
$sql = 'UPDATE accounts SET ' . implode(', ', $fields) . ' WHERE id = ?';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success' => true, 'message' => 'تم تحديث الإعلان']);
exit;
