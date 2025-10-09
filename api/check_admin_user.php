<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    // جلب بيانات المستخدم
    $stmt = $pdo->prepare("SELECT id, name, email, phone, role FROM users WHERE email = 'admin@admin.com' OR phone = '01000000000'");
    $stmt->execute();
    $user = $stmt->fetch();

    if ($user) {
        echo json_encode([
            'success' => true,
            'user' => $user
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'المستخدم غير موجود'
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
