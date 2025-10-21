<?php
// تصليح معرفات البلاغات في قاعدة البيانات
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // جلب جميع البلاغات
    $getReports = $pdo->query("SELECT id, reporter_id, reported_user_id, conversation_id FROM reports ORDER BY created_at DESC");
    $reports = $getReports->fetchAll(PDO::FETCH_ASSOC);

    echo "عدد البلاغات: " . count($reports) . "\n\n";

    foreach ($reports as $report) {
        echo "البلاغ ID: {$report['id']}, المبلغ: {$report['reporter_id']}, المبلغ عنه: {$report['reported_user_id']}, المحادثة: {$report['conversation_id']}\n";
    }

    echo "\n✅ البيانات صحيحة!\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
