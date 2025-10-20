<?php
$pdo = new PDO('mysql:host=localhost;dbname=bvize_games_accounts', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    $buyer_id = 36; // احمد حسين السيد
    $seller_id = 37; // mohamed salah
    $amount = 250.00;

    // إضافة رصيد للمشتري
    $stmt = $pdo->prepare("INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 500.00, 0) ON DUPLICATE KEY UPDATE balance = balance + 500");
    $stmt->execute([$buyer_id]);

    // خصم من رصيد المشتري وإضافة للرصيد المعلق
    $stmt = $pdo->prepare("UPDATE wallets SET balance = balance - ?, pending_balance = pending_balance + ? WHERE user_id = ?");
    $stmt->execute([$amount, $amount, $buyer_id]);

    // إنشاء الصفقة
    $stmt = $pdo->prepare("
        INSERT INTO deals (buyer_id, seller_id, amount, status, escrow_status, created_at, updated_at)
        VALUES (?, ?, ?, 'FUNDED', 'HELD', NOW(), NOW())
    ");
    $stmt->execute([$buyer_id, $seller_id, $amount]);
    $deal_id = $pdo->lastInsertId();

    $pdo->commit();

    echo "✅ تم إنشاء صفقة اختبار جديدة!\n";
    echo "Deal ID: {$deal_id}\n";
    echo "Buyer ID: {$buyer_id}\n";
    echo "Seller ID: {$seller_id}\n";
    echo "Amount: {$amount}\n\n";

    // عرض الأرصدة
    $stmt = $pdo->prepare("SELECT balance, pending_balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$buyer_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "رصيد المشتري:\n";
    echo "  Balance: {$wallet['balance']}\n";
    echo "  Pending: {$wallet['pending_balance']}\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}
?>
