<?php
require_once 'api/db.php';

try {
    $pdo->beginTransaction();
    
    echo "إصلاح الأرصدة المعلقة للصفقات الممولة...\n";
    echo "==========================================\n";
    
    // البحث عن الصفقات الممولة التي يجب أن يكون لها رصيد معلق
    $stmt = $pdo->query("
        SELECT d.id, d.buyer_id, d.amount, w.pending_balance, u.name
        FROM deals d 
        JOIN wallets w ON d.buyer_id = w.user_id 
        JOIN users u ON d.buyer_id = u.id
        WHERE d.status = 'FUNDED' 
        AND d.escrow_status = 'PENDING'
        AND d.amount > 0
    ");
    
    $fixed_count = 0;
    
    while ($row = $stmt->fetch()) {
        echo "الصفقة #{$row['id']} للمشتري {$row['name']}:\n";
        echo "  مبلغ الصفقة: {$row['amount']}\n";
        echo "  الرصيد المعلق الحالي: {$row['pending_balance']}\n";
        
        if ($row['pending_balance'] != $row['amount']) {
            // تصحيح الرصيد المعلق ليتطابق مع مبلغ الصفقة
            $update_stmt = $pdo->prepare('UPDATE wallets SET pending_balance = ? WHERE user_id = ?');
            $update_stmt->execute([$row['amount'], $row['buyer_id']]);
            
            echo "  تم تصحيح الرصيد المعلق إلى: {$row['amount']}\n";
            $fixed_count++;
        } else {
            echo "  الرصيد المعلق صحيح\n";
        }
        echo "\n";
    }
    
    $pdo->commit();
    echo "تم إصلاح {$fixed_count} صفقة بنجاح!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "خطأ: " . $e->getMessage() . "\n";
}
?>