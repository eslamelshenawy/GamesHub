<?php
require_once 'db.php';
// Session is now managed in db.php
require_once 'security.php';
ensure_session();

header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'غير مسجل الدخول']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // التحقق من حالة المستخدم
    $stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // التحقق من حالة الحظر
    if ($user['role'] === 'banned') {
        // تسجيل الخروج
        session_destroy();
        echo json_encode([
            'success' => false, 
            'banned' => true,
            'error' => 'تم حظر حسابك من قبل الإدارة'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error in check_user_ban.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في الخادم']);
}
?>