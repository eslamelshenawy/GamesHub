<?php
// إنشاء بلاغ تجريبي معلق
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // إضافة بلاغ معلق
    $stmt = $pdo->prepare("
        INSERT INTO reports (reporter_id, reported_user_id, conversation_id, reason, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ");

    // بيانات البلاغ التجريبي
    $reporter_id = 43; // mmm
    $reported_user_id = 44; // mmmm
    $conversation_id = 11; // محادثة موجودة
    $reason = 'تصرفات مشبوهة في الصفقة - بلاغ تجريبي للاختبار';

    $stmt->execute([$reporter_id, $reported_user_id, $conversation_id, $reason]);
    $report_id = $pdo->lastInsertId();

    // جلب بيانات البلاغ المُنشأ
    $checkStmt = $pdo->prepare("
        SELECT
            r.*,
            reporter.name as reporter_name,
            reported.name as reported_user_name
        FROM reports r
        LEFT JOIN users reporter ON r.reporter_id = reporter.id
        LEFT JOIN users reported ON r.reported_user_id = reported.id
        WHERE r.id = ?
    ");
    $checkStmt->execute([$report_id]);
    $report = $checkStmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء بلاغ معلق بنجاح',
        'report_id' => $report_id,
        'report' => $report
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
