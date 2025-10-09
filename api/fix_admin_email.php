<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'db.php';

try {
    // تحديث الإيميل للمستخدم
    $stmt = $pdo->prepare("
        UPDATE users 
        SET email = 'admin@admin.com',
            password = ?
        WHERE phone = '01000000000'
    ");
    
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt->execute([$hashedPassword]);

    // التحقق من النتيجة
    $checkStmt = $pdo->prepare("SELECT id, name, email, phone, role FROM users WHERE phone = '01000000000'");
    $checkStmt->execute();
    $user = $checkStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث البيانات بنجاح',
        'user' => $user
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
