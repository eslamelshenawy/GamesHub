<?php
// سكريبت لاختبار تدفق الصفقات للإدارة
require_once 'db.php';
header('Content-Type: text/plain; charset=utf-8');

echo "========== اختبار تدفق الصفقات للإدارة ==========\n\n";

try {
    // 1. جلب الصفقات التي في انتظار مراجعة الإدارة
    echo "=== 1. الصفقات التي تأكيد استلام البيانات (PENDING_ADMIN) ===\n\n";

    $pendingAdminStmt = $pdo->query("
        SELECT
            d.id,
            d.status,
            d.admin_review_status,
            d.escrow_status,
            buyer.name as buyer_name,
            seller.name as seller_name,
            d.amount,
            d.created_at
        FROM deals d
        LEFT JOIN users buyer ON d.buyer_id = buyer.id
        LEFT JOIN users seller ON d.seller_id = seller.id
        WHERE d.status = 'PENDING_ADMIN' OR d.admin_review_status = 'pending'
        ORDER BY d.created_at DESC
        LIMIT 10
    ");

    $pendingAdminDeals = $pendingAdminStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($pendingAdminDeals) > 0) {
        echo "عدد الصفقات: " . count($pendingAdminDeals) . "\n\n";
        foreach ($pendingAdminDeals as $deal) {
            echo "صفقة #{$deal['id']}\n";
            echo "  - المشتري: {$deal['buyer_name']}\n";
            echo "  - البائع: {$deal['seller_name']}\n";
            echo "  - المبلغ: {$deal['amount']} ج.م\n";
            echo "  - الحالة: {$deal['status']}\n";
            echo "  - حالة المراجعة: " . ($deal['admin_review_status'] ?? 'null') . "\n";
            echo "  - Escrow: {$deal['escrow_status']}\n";
            echo "  - التاريخ: {$deal['created_at']}\n";
            echo "  ----\n";
        }
    } else {
        echo "⚠️ لا توجد صفقات في انتظار المراجعة\n";
    }

    echo "\n\n=== 2. الصفقات المطلوب إلغاؤها (PENDING_CANCEL) ===\n\n";

    $pendingCancelStmt = $pdo->query("
        SELECT
            d.id,
            d.status,
            d.cancel_reason,
            d.cancel_requested_by,
            buyer.name as buyer_name,
            seller.name as seller_name,
            d.amount,
            d.cancel_requested_at
        FROM deals d
        LEFT JOIN users buyer ON d.buyer_id = buyer.id
        LEFT JOIN users seller ON d.seller_id = seller.id
        WHERE d.status = 'PENDING_CANCEL'
        ORDER BY d.cancel_requested_at DESC
        LIMIT 10
    ");

    $pendingCancelDeals = $pendingCancelStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($pendingCancelDeals) > 0) {
        echo "عدد الصفقات: " . count($pendingCancelDeals) . "\n\n";
        foreach ($pendingCancelDeals as $deal) {
            echo "صفقة #{$deal['id']}\n";
            echo "  - المشتري: {$deal['buyer_name']}\n";
            echo "  - البائع: {$deal['seller_name']}\n";
            echo "  - المبلغ: {$deal['amount']} ج.م\n";
            echo "  - طلب الإلغاء من: {$deal['cancel_requested_by']}\n";
            echo "  - سبب الإلغاء: {$deal['cancel_reason']}\n";
            echo "  - تاريخ الطلب: " . ($deal['cancel_requested_at'] ?? 'null') . "\n";
            echo "  ----\n";
        }
    } else {
        echo "⚠️ لا توجد صفقات مطلوب إلغاؤها\n";
    }

    echo "\n\n=== 3. ملخص الحالات ===\n\n";

    $summaryStmt = $pdo->query("
        SELECT
            status,
            admin_review_status,
            COUNT(*) as count
        FROM deals
        GROUP BY status, admin_review_status
        ORDER BY count DESC
    ");

    $summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($summary as $row) {
        $reviewStatus = $row['admin_review_status'] ?? 'null';
        echo "الحالة: {$row['status']} | المراجعة: {$reviewStatus} | العدد: {$row['count']}\n";
    }

    echo "\n\n✅ اكتمل الاختبار بنجاح!\n";

} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
