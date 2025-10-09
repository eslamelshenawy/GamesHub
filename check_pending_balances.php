<?php
require_once 'api/db.php';

try {
    $stmt = $pdo->query('SELECT u.name, w.balance, w.pending_balance FROM wallets w JOIN users u ON w.user_id = u.id WHERE w.pending_balance > 0');
    
    echo "المستخدمون الذين لديهم أرصدة معلقة:\n";
    echo "=====================================\n";
    
    $found = false;
    while($row = $stmt->fetch()) {
        $found = true;
        echo $row['name'] . ': الرصيد=' . $row['balance'] . ', المعلق=' . $row['pending_balance'] . "\n";
    }
    
    if (!$found) {
        echo "لا توجد أرصدة معلقة\n";
    }
    
    // التحقق من الصفقات النشطة
    echo "\nالصفقات النشطة:\n";
    echo "================\n";
    
    $stmt = $pdo->query('SELECT d.id, d.status, d.amount, d.escrow_status, ub.name as buyer_name, us.name as seller_name FROM deals d JOIN users ub ON d.buyer_id = ub.id JOIN users us ON d.seller_id = us.id WHERE d.status NOT IN ("COMPLETED", "CANCELLED", "REFUNDED")');
    
    $active_found = false;
    while($row = $stmt->fetch()) {
        $active_found = true;
        echo "صفقة #{$row['id']}: {$row['buyer_name']} -> {$row['seller_name']}, المبلغ: {$row['amount']}, الحالة: {$row['status']}, الضمان: {$row['escrow_status']}\n";
    }
    
    if (!$active_found) {
        echo "لا توجد صفقات نشطة\n";
    }
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
?>