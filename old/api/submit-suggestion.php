<?php
// api/submit-suggestion.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

// تحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة']);
    exit;
}

// جلب البيانات من JSON

$input = json_decode(file_get_contents('php://input'), true);
$description = isset($input['suggestion']) ? trim($input['suggestion']) : '';
$title = 'اقتراح جديد'; // يمكنك لاحقًا إضافة حقل عنوان في النموذج
$status = 'pending';

if ($description === '' || mb_strlen($description) < 3) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'يجب كتابة اقتراح صالح']);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

try {
    $stmt = $pdo->prepare('INSERT INTO suggestions (user_id, title, description, status, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$user_id, $title, $description, $status]);
    echo json_encode(['success' => true]);
    exit;
} catch (PDOException $e) {
    error_log('[submit-suggestion] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    exit;
}
