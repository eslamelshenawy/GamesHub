<?php
require_once 'db.php';

try {
    // إضافة أعمدة طلب الإلغاء
    $pdo->exec("
        ALTER TABLE deals
        ADD COLUMN IF NOT EXISTS cancel_reason TEXT,
        ADD COLUMN IF NOT EXISTS cancel_requested_by VARCHAR(20),
        ADD COLUMN IF NOT EXISTS cancel_requested_at DATETIME
    ");

    echo "✅ تم إضافة الأعمدة بنجاح!\n";
    echo "- cancel_reason: سبب الإلغاء\n";
    echo "- cancel_requested_by: من طلب الإلغاء (buyer/seller)\n";
    echo "- cancel_requested_at: تاريخ طلب الإلغاء\n";

} catch (PDOException $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
