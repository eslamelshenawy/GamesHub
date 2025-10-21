<?php
// تصليح جدول البلاغات - إصلاح البلاغات اللي ID = 0
require_once 'db.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    // 1. أولاً: نتأكد من structure الجدول
    echo "========== فحص بنية جدول reports ==========\n\n";

    $descStmt = $pdo->query("DESCRIBE reports");
    $columns = $descStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "{$col['Field']}: {$col['Type']} | Key: {$col['Key']} | Extra: {$col['Extra']}\n";
    }

    // 2. جلب البلاغات اللي ID = 0
    echo "\n\n========== البلاغات ذات ID = 0 ==========\n\n";

    $zeroReports = $pdo->query("
        SELECT id, reporter_id, reported_user_id, conversation_id, created_at
        FROM reports
        WHERE id = 0
        ORDER BY created_at ASC
    ");
    $zeroReportsList = $zeroReports->fetchAll(PDO::FETCH_ASSOC);

    echo "عدد البلاغات ذات ID = 0: " . count($zeroReportsList) . "\n\n";

    if (count($zeroReportsList) > 0) {
        echo "========== إصلاح البلاغات ==========\n\n";

        // 3. جلب أكبر ID موجود
        $maxIdStmt = $pdo->query("SELECT MAX(id) as max_id FROM reports WHERE id > 0");
        $maxId = (int)$maxIdStmt->fetch(PDO::FETCH_ASSOC)['max_id'];
        echo "أكبر ID موجود: {$maxId}\n\n";

        $pdo->beginTransaction();

        $newId = $maxId + 1;
        $fixed = 0;

        foreach ($zeroReportsList as $report) {
            // تحديث كل بلاغ بـ ID جديد
            $updateStmt = $pdo->prepare("
                UPDATE reports
                SET id = ?
                WHERE id = 0
                  AND reporter_id = ?
                  AND reported_user_id = ?
                  AND created_at = ?
                LIMIT 1
            ");

            $updateStmt->execute([
                $newId,
                $report['reporter_id'],
                $report['reported_user_id'],
                $report['created_at']
            ]);

            if ($updateStmt->rowCount() > 0) {
                echo "✅ تم تحديث البلاغ: ID القديم = 0 → ID الجديد = {$newId}\n";
                echo "   المبلغ: {$report['reporter_id']}, المبلغ عنه: {$report['reported_user_id']}\n";
                $fixed++;
                $newId++;
            }
        }

        $pdo->commit();

        echo "\n========== النتيجة ==========\n";
        echo "✅ تم إصلاح {$fixed} بلاغ بنجاح!\n\n";
    } else {
        echo "✅ لا توجد بلاغات تحتاج إصلاح!\n\n";
    }

    // 4. التأكد من AUTO_INCREMENT
    echo "========== فحص AUTO_INCREMENT ==========\n\n";

    $autoIncStmt = $pdo->query("
        SELECT AUTO_INCREMENT
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'reports'
    ");
    $autoInc = $autoIncStmt->fetch(PDO::FETCH_ASSOC);

    if ($autoInc) {
        echo "قيمة AUTO_INCREMENT الحالية: {$autoInc['AUTO_INCREMENT']}\n";

        // تحديث AUTO_INCREMENT لأكبر ID + 1
        $maxIdStmt = $pdo->query("SELECT MAX(id) as max_id FROM reports");
        $maxId = (int)$maxIdStmt->fetch(PDO::FETCH_ASSOC)['max_id'];
        $nextId = $maxId + 1;

        $pdo->exec("ALTER TABLE reports AUTO_INCREMENT = {$nextId}");
        echo "✅ تم تحديث AUTO_INCREMENT إلى: {$nextId}\n\n";
    }

    echo "========== اكتمل الإصلاح بنجاح! ==========\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ خطأ: " . $e->getMessage() . "\n";
    echo "التفاصيل: " . $e->getTraceAsString() . "\n";
}
?>
