<?php
// إنشاء طلب سحب تجريبي معلق
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // إضافة طلب سحب معلق
    $stmt = $pdo->prepare("
        INSERT INTO withdraw_requests (user_id, amount, phone, method, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");

    // بيانات الطلب التجريبي
    $user_id = 44; // المستخدم mmmm
    $amount = 300.00;
    $phone = '01098765432';
    $method = 'vodafone_cash';

    $stmt->execute([$user_id, $amount, $phone, $method]);
    $withdraw_id = $pdo->lastInsertId();

    // جلب بيانات الطلب المُنشأ
    $checkStmt = $pdo->prepare("
        SELECT w.*, u.name
        FROM withdraw_requests w
        LEFT JOIN users u ON w.user_id = u.id
        WHERE w.id = ?
    ");
    $checkStmt->execute([$withdraw_id]);
    $withdraw = $checkStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء طلب سحب معلق بنجاح',
        'withdraw_id' => $withdraw_id,
        'withdraw' => $withdraw
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
