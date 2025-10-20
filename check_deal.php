<?php
$pdo = new PDO('mysql:host=localhost;dbname=bvize_games_accounts', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check deal
$stmt = $pdo->prepare('SELECT d.*, w.pending_balance as buyer_pending FROM deals d LEFT JOIN wallets w ON d.buyer_id = w.user_id WHERE d.id = 90');
$stmt->execute();
$deal = $stmt->fetch(PDO::FETCH_ASSOC);

if ($deal) {
    echo "Deal Info:\n";
    echo json_encode($deal, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "Deal not found\n";
}
?>
