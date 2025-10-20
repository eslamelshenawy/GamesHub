<?php
$pdo = new PDO('mysql:host=localhost;dbname=bvize_games_accounts', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== التحقق من الأرصدة بعد رفض الصفقة ===\n\n";

// Admin/System
$stmt = $pdo->prepare("SELECT u.id, u.name, u.role, w.balance, w.pending_balance FROM users u LEFT JOIN wallets w ON u.id = w.user_id WHERE u.role IN ('system', 'admin')");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📌 حسابات الإدارة/النظام:\n";
foreach ($admins as $admin) {
    echo sprintf("   %-20s (ID: %2d, Role: %s) | رصيد: %8.2f | معلق: %8.2f\n",
        $admin['name'], $admin['id'], $admin['role'],
        $admin['balance'] ?? 0, $admin['pending_balance'] ?? 0);
}

echo "\n📌 المشتري (ID: 36):\n";
$stmt = $pdo->prepare("SELECT u.name, w.balance, w.pending_balance FROM users u LEFT JOIN wallets w ON u.id = w.user_id WHERE u.id = 36");
$stmt->execute();
$buyer = $stmt->fetch(PDO::FETCH_ASSOC);
echo sprintf("   %-20s | رصيد: %8.2f | معلق: %8.2f\n",
    $buyer['name'], $buyer['balance'] ?? 0, $buyer['pending_balance'] ?? 0);

echo "\n📌 سجل المعاملات للصفقة 92:\n";
$stmt = $pdo->prepare("SELECT type, amount, from_user, to_user, description, created_at FROM financial_logs WHERE deal_id = 92 ORDER BY created_at");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as $log) {
    echo sprintf("   %s: %s → %s | %8.2f | %s\n",
        $log['type'], $log['from_user'], $log['to_user'], $log['amount'], $log['description']);
}
?>
