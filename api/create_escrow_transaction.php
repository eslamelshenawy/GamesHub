<?php
// api/create_escrow_transaction.php
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

$seller_id = isset($input['seller_id']) ? intval($input['seller_id']) : 0;
$amount = isset($input['amount']) ? floatval($input['amount']) : 0;
$payment_method = isset($input['payment_method']) ? $input['payment_method'] : '';

// تسجيل القيم المستلمة من الطلب
file_put_contents('debug.log', json_encode(['seller_id'=>$seller_id, 'payment_method'=>$payment_method, 'amount'=>$amount, 'user_id'=>$user_id]) . "\n", FILE_APPEND);
file_put_contents('debug.log', "START ESCROW TRANSACTION REQUEST\n", FILE_APPEND);

// تحقق من صحة البيانات
if ($seller_id <= 0 || $amount <= 0 || !$payment_method) {
    file_put_contents('debug.log', "INVALID DATA: " . json_encode($input) . "\n", FILE_APPEND);
    file_put_contents('debug.log', "RECEIVED SELLER ID: " . $seller_id . "\n", FILE_APPEND);
    file_put_contents('debug.log', "ERROR: بيانات غير صالحة\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات غير صالحة']);
    exit;
}

// تحقق من وجود seller_id في جدول المستخدمين
$stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
$stmt->execute([$seller_id]);
$seller_exists = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$seller_exists) {
    file_put_contents('debug.log', "SELLER NOT FOUND: $seller_id\n", FILE_APPEND);
    file_put_contents('debug.log', "ERROR: البائع غير موجود\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'البائع غير موجود']);
    exit;
}
 
try {
    $pdo->beginTransaction();
    file_put_contents('debug.log', "START TRANSACTION\n", FILE_APPEND);
    file_put_contents('debug.log', "USER_ID: $user_id, SELLER_ID: $seller_id, AMOUNT: $amount, PAYMENT_METHOD: $payment_method\n", FILE_APPEND);

    // التحقق من وجود رصيد كافٍ في محفظة المشتري
    $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents('debug.log', "WALLET: " . json_encode($wallet) . "\n", FILE_APPEND);

    if (!$wallet) {
        file_put_contents('debug.log', "NO WALLET FOUND, CREATING NEW WALLET\n", FILE_APPEND);
        $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, 0)');
        $stmt->execute([$user_id]);
        $wallet = ['balance' => 0];
    }

    if ($wallet['balance'] < $amount) {
        file_put_contents('debug.log', "INSUFFICIENT BALANCE\n", FILE_APPEND);
        file_put_contents('debug.log', "ERROR: لا يوجد رصيد كافٍ في المحفظة\n", FILE_APPEND);
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لا يوجد رصيد كافٍ في المحفظة']);
        exit;
    }

    // خصم المبلغ من رصيد المحفظة
    $stmt = $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE user_id = ?');
    $stmt->execute([$amount, $user_id]);
    file_put_contents('debug.log', "BALANCE UPDATED\n", FILE_APPEND);

    $stmt = $pdo->prepare("INSERT INTO escrow_transactions (buyer_id, seller_id, payment_method, amount, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$user_id, $seller_id, $payment_method, $amount]);
    file_put_contents('debug.log', "ESCROW TRANSACTION INSERTED\n", FILE_APPEND);

    $transaction_id = $pdo->lastInsertId();
    file_put_contents('debug.log', "TRANSACTION ID: $transaction_id\n", FILE_APPEND);
    $pdo->commit();

    file_put_contents('debug.log', "TRANSACTION COMMITTED\n", FILE_APPEND);
    echo json_encode(['success' => true, 'transaction_id' => $transaction_id]);
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    file_put_contents('debug.log', '[create_escrow_transaction] PDOException: ' . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents('debug.log', "ERROR: خطأ في الخادم\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    exit;
}
