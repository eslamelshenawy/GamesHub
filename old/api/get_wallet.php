<?php
// api/get_wallet.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}
$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare('SELECT balance, pending_balance FROM wallets WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    $balance = $row ? floatval($row['balance']) : 0.0;
    $pending_balance = $row ? floatval($row['pending_balance']) : 0.0;
    echo json_encode(['success' => true, 'balance' => $balance, 'pending_balance' => $pending_balance]);
    exit;
} catch (PDOException $e) {
    error_log('[get_wallet] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    exit;
}
