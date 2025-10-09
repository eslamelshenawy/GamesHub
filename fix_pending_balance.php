<?php
require_once 'api/db.php';

try {
    $pdo->beginTransaction();
    
    // البحث عن الصفقات الممولة التي لها مشاكل في الرصيد المعلق
    $stmt = $pdo->query("
        SELECT d.id, d.buyer_id, d.amount, w.pending_balance, u.name
        FROM deals d 
        JOIN wallets w ON d.buyer_id = w.user_id 
        JOIN users u ON d.buyer_id = u.id
        WHERE d.status = 'FUNDED' 
        AND d.escrow_status = 'PENDING'
        AND w.pending_balance != d.amount
    ");
    
    $issues_found = false;
    
    while ($row = $stmt->fetch()) {
        $issues_found = true;
        echo "مشكلة في الصفقة #{$row['id']} للمشتري {$row['name']}:\n";
        echo "  مبلغ الصفقة: {$row['amount']}\n";
        echo "  الرصيد المعلق الحالي: {$row['pending_balance']}\n";
        
        // تصحيح الرصيد المعلق ليتطابق مع مبلغ الصفقة
        $difference = $row['amount'] - $row['pending_balance'];
        
        if ($difference > 0) {
            // نحتاج لزيادة الرصيد المعلق
            echo "  سيتم زيادة الرصيد المعلق بـ {$difference}\n";
            
            $update_stmt = $pdo->prepare('UPDATE wallets SET pending_balance = ? WHERE user_id = ?');
            $update_stmt->execute([$row['amount'], $row['buyer_id']]);
            
        } else if ($difference < 0) {
            // نحتاج لتقليل الرصيد المعلق
            echo "  سيتم تقليل الرصيد المعلق بـ " . abs($difference) . "\n";
            
            $update_stmt = $pdo->prepare('UPDATE wallets SET pending_balance = ? WHERE user_id = ?');
            $update_stmt->execute([$row['amount'], $row['buyer_id']]);
        }
        
        echo "  تم التصحيح!\n\n";
    }
    
    if (!$issues_found) {
        echo "لا توجد مشاكل في الأرصدة المعلقة للصفقات الممولة.\n";
    }
    
    // التحقق من الصفقات المكتملة التي لا تزال لديها أرصدة معلقة
    echo "\nالتحقق من الصفقات المكتملة...\n";
    
    $stmt = $pdo->query("
        SELECT d.id, d.buyer_id, d.amount, w.pending_balance, u.name
        FROM deals d 
        JOIN wallets w ON d.buyer_id = w.user_id 
        JOIN users u ON d.buyer_id = u.id
        WHERE d.status = 'COMPLETED' 
        AND w.pending_balance > 0
    ");
    
    $completed_issues = false;
    
    while ($row = $stmt->fetch()) {
        $completed_issues = true;
        echo "صفقة مكتملة #{$row['id']} للمشتري {$row['name']} لا تزال لديها رصيد معلق: {$row['pending_balance']}\n";
        
        // حذف الرصيد المعلق للصفقات المكتملة
        $update_stmt = $pdo->prepare('UPDATE wallets SET pending_balance = 0 WHERE user_id = ?');
        $update_stmt->execute([$row['buyer_id']]);
        
        echo "  تم حذف الرصيد المعلق.\n";
    }
    
    if (!$completed_issues) {
        echo "لا توجد مشاكل في الأرصدة المعلقة للصفقات المكتملة.\n";
    }
    
    $pdo->commit();
    echo "\nتم إصلاح جميع المشاكل بنجاح!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "خطأ: " . $e->getMessage() . "\n";
}
?>