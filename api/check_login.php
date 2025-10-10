<?php
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

// Debug: Log session status
error_log("check_login.php - Session ID: " . session_id());
error_log("check_login.php - Session data: " . json_encode($_SESSION));
error_log("check_login.php - Cookie data: " . json_encode($_COOKIE));

// التحقق من حالة تسجيل الدخول
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    // التحقق من وجود المستخدم في قاعدة البيانات
    try {
        $stmt = $pdo->prepare('SELECT id, name, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            // التحقق من حالة الحظر
            if ($user['role'] === 'banned') {
                // تسجيل الخروج إذا كان محظور
                session_destroy();
                echo json_encode([
                    'logged_in' => false,
                    'banned' => true,
                    'message' => 'تم حظر حسابك من قبل الإدارة'
                ]);
            } else {
                echo json_encode([
                    'logged_in' => true,
                    'user_id' => $user['id'],
                    'name' => $user['name'],
                    'is_admin' => ($user['role'] === 'admin')
                ]);
            }
        } else {
            // المستخدم غير موجود في قاعدة البيانات
            session_destroy();
            echo json_encode(['logged_in' => false]);
        }
    } catch (Exception $e) {
        error_log('Error in check_login.php: ' . $e->getMessage());
        echo json_encode(['logged_in' => false, 'error' => 'خطأ في الخادم']);
    }
} else {
    echo json_encode(['logged_in' => false]);
}
?>