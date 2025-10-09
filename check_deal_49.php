<?php
require_once 'api/db.php';

try {
    // التحقق من تفاصيل الصفقة 49
    $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = 49');
    $stmt->execute();
    $deal = $stmt->fetch();
    
    if ($deal) {
        echo "تفاصيل الصفقة #49:\n";
        echo "المشتري ID: {$deal['buyer_id']}\n";
        echo "البائع ID: {$deal['seller_id']}\n";
        echo "المبلغ: {$deal['amount']}\n";
        echo "الحالة: {$deal['status']}\n";
        echo "حالة الضمان: {$deal['escrow_status']}\n";
        echo "مبلغ الضمان: {$deal['escrow_amount']}\n";
        echo "تاريخ الإنشاء: {$deal['created_at']}\n";
        echo "تاريخ التحديث: {$deal['updated_at']}\n";
        
        // التحقق من محفظة المشتري
        $stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ?');
        $stmt->execute([$deal['buyer_id']]);
        $wallet = $stmt->fetch();
        
        if ($wallet) {
            echo "\nمحفظة المشتري:\n";
            echo "الرصيد: {$wallet['balance']}\n";
            echo "الرصيد المعلق: {$wallet['pending_balance']}\n";
        }
        
        // التحقق من اسم المشتري
        $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
        $stmt->execute([$deal['buyer_id']]);
        $buyer = $stmt->fetch();
        echo "\nاسم المشتري: {$buyer['name']}\n";
        
    } else {
        echo "لم يتم العثور على الصفقة #49\n";
    }
    
} catch (Exception $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
?>