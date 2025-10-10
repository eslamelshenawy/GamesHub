<?php
require_once 'db.php';
// Session is now managed in db.php
require_once 'security.php';
ensure_session();

header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مسجل الدخول', 'is_admin' => false]);
    exit;
}

// التحقق من صلاحيات الأدمن
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

if (!$is_admin) {
    // التحقق من قاعدة البيانات كخطوة إضافية
    try {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && isset($user['role']) && $user['role'] === 'admin') {
            $_SESSION['is_admin'] = 1;
            $is_admin = true;
        }
    } catch (Exception $e) {
        error_log('Error checking admin status: ' . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'is_admin' => $is_admin,
    'user_id' => $_SESSION['user_id'],
    'user_name' => $_SESSION['user_name'] ?? 'غير محدد'
]);
?>