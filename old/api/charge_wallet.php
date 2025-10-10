<?php
// api/charge_wallet.php
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

// تحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = isset($input['amount']) ? floatval($input['amount']) : 0;
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'مبلغ غير صالح']);
    exit;
}

try {
    // إذا لم يكن للمستخدم محفظة، أنشئ واحدة
    $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, ?)');
        $stmt->execute([$user_id, $amount]);
        $newBalance = $amount;
    } else {
        $row = $stmt->fetch();
        $newBalance = floatval($row['balance']) + $amount;
        $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?');
        $stmt->execute([$newBalance, $user_id]);
    }
    // سجل العملية في جدول wallet_transactions (اختياري)
    $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, "deposit", "شحن المحفظة يدوي", NOW())');
    $stmt->execute([$user_id, $amount]);
    echo json_encode(['success' => true, 'balance' => $newBalance]);
    exit;
} catch (PDOException $e) {
    error_log('[charge_wallet] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    exit;
}
