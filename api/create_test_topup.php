<?php
// إنشاء طلب شحن تجريبي معلق
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // إضافة طلب شحن معلق
    $stmt = $pdo->prepare("
        INSERT INTO wallet_topups (user_id, method, amount, phone, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");

    // بيانات الطلب التجريبي
    $user_id = 43; // المستخدم mmm
    $method = 'vodafone_cash';
    $amount = 500.00;
    $phone = '01234567890';

    $stmt->execute([$user_id, $method, $amount, $phone]);
    $topup_id = $pdo->lastInsertId();

    // جلب بيانات الطلب المُنشأ
    $checkStmt = $pdo->prepare("
        SELECT t.*, u.name as username
        FROM wallet_topups t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.id = ?
    ");
    $checkStmt->execute([$topup_id]);
    $topup = $checkStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء طلب شحن معلق بنجاح',
        'topup_id' => $topup_id,
        'topup' => $topup
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
