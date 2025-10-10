<?php
require_once 'db.php';
require_once 'security.php';

header('Content-Type: application/json; charset=utf-8');
ensure_session();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

try {
    // التحقق من حالة المستخدم
    $stmt = $pdo->prepare("SELECT id, username, is_banned FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // إرجاع حالة المستخدم
    echo json_encode([
        'success' => true,
        'user_id' => $user['id'],
        'username' => $user['username'],
        'is_banned' => (bool)$user['is_banned']
    ]);
    
} catch (Exception $e) {
    error_log('check_ban.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم']);
}
?>