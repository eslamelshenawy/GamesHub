<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'error'=>'غير مصرح']);
    exit;
}
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);
$id = isset($data['topup_id']) ? intval($data['topup_id']) : 0;

// Debug: log what we received
error_log("approve_topup.php - Raw input: " . $raw_input);
error_log("approve_topup.php - Decoded data: " . json_encode($data));
error_log("approve_topup.php - Topup ID: " . $id);

if ($id <= 0) {
    echo json_encode([
        'success'=>false,
        'error'=>'طلب غير صالح',
        'debug' => [
            'raw_input' => $raw_input,
            'decoded_data' => $data,
            'topup_id' => $id
        ]
    ]);
    exit;
}
// جلب الطلب
$stmt = $pdo->prepare("SELECT * FROM wallet_topups WHERE id = ?");
$stmt->execute([$id]);
$topup = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topup || $topup['status'] !== 'pending') {
    echo json_encode(['success'=>false, 'error'=>'الطلب غير موجود أو تم مراجعته']);
    exit;
}
// تحديث الرصيد

$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE wallet_topups SET status = 'approved', reviewed_at = NOW() WHERE id = ?")->execute([$id]);
    // تحقق من وجود محفظة للمستخدم، إذا لم توجد أنشئ واحدة
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? LIMIT 1");
    $stmt->execute([$topup['user_id']]);
    if ($stmt->rowCount() === 0) {
        $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)")->execute([$topup['user_id'], $topup['amount']]);
    } else {
        $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
            ->execute([$topup['amount'], $topup['user_id']]);
    }
    $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'deposit', 'شحن عبر الإدارة', NOW())")
        ->execute([$topup['user_id'], $topup['amount']]);
    $pdo->commit();
    echo json_encode(['success'=>true, 'message'=>'تمت الموافقة وشحن الرصيد']);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false, 'error'=>'فشل التنفيذ: ' . $e->getMessage()]);
}
