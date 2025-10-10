<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

// api/release_escrow.php
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
$input = json_decode(file_get_contents('php://input'), true);

$transaction_id = isset($input['transaction_id']) ? intval($input['transaction_id']) : 0;

if ($transaction_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات غير صالحة']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT * FROM escrow_transactions WHERE transaction_id = ? AND buyer_id = ? AND status = "pending" LIMIT 1');
    $stmt->execute([$transaction_id, $user_id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'المعاملة غير موجودة أو غير صالحة']);
        exit;
    }

    $transaction = $stmt->fetch();

    $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
    $stmt->execute([$transaction['amount'], $transaction['seller_id']]);

    $stmt = $pdo->prepare('UPDATE escrow_transactions SET status = "completed", updated_at = NOW() WHERE transaction_id = ?');
    $stmt->execute([$transaction_id]);

    $pdo->commit();

    echo json_encode(['success' => true]);
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[release_escrow] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    exit;
}
