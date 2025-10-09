<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'api/db.php';
require_once 'api/security.php';
ensure_session();

// تحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة']);
    exit;
}
// قراءة البيانات المرسلة

file_put_contents('debug.log', '[debug] Start process_payment.php' . PHP_EOL, FILE_APPEND);
$input = json_decode(file_get_contents('php://input'), true);
file_put_contents('debug.log', '[debug] Decoded input: ' . json_encode($input) . PHP_EOL, FILE_APPEND);


$payment_method = $input['payment_method'] ?? null;
$amount = $input['amount'] ?? null;
$from_id = $_SESSION['user_id'] ?? null; // المرسل
$to_id = $input['to_id'] ?? null;        // المستقبل
file_put_contents('debug.log', "[debug] payment_method=$payment_method, amount=$amount, from_id=$from_id, to_id=$to_id" . PHP_EOL, FILE_APPEND);

// تسجيل البيانات الواردة في ملف للتصحيح (تمت أعلاه)

// تحقق من صحة البيانات
if (!$payment_method || !$amount || $amount <= 0 || !$from_id || !$to_id) {
    file_put_contents('debug.log', '[debug] Invalid data check failed' . PHP_EOL, FILE_APPEND);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات غير صالحة']);
    exit;
}

try {
    file_put_contents('debug.log', '[debug] Starting transaction' . PHP_EOL, FILE_APPEND);
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        file_put_contents('debug.log', '[debug] Transaction started' . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents('debug.log', '[debug] Transaction already active' . PHP_EOL, FILE_APPEND);
    }

    // تحقق من رصيد المرسل
    $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$from_id]);
    $from_wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents('debug.log', '[debug] from_wallet: ' . json_encode($from_wallet) . PHP_EOL, FILE_APPEND);
    if (!$from_wallet || $from_wallet['balance'] < $amount) {
        file_put_contents('debug.log', '[debug] Not enough balance or wallet not found' . PHP_EOL, FILE_APPEND);
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لا يوجد رصيد كافٍ أو لا توجد محفظة للمرسل']);
        exit;
    }

    // خصم من المرسل
    $stmt = $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE user_id = ?');
    $stmt->execute([$amount, $from_id]);
    file_put_contents('debug.log', '[debug] Deducted from sender wallet' . PHP_EOL, FILE_APPEND);

    // إضافة للمستقبل (إن لم توجد محفظة، أنشئ واحدة)
    $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$to_id]);
    $to_wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents('debug.log', '[debug] to_wallet: ' . json_encode($to_wallet) . PHP_EOL, FILE_APPEND);
    if (!$to_wallet) {
        $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, ?)');
        $stmt->execute([$to_id, $amount]);
        $new_balance = $amount;
        file_put_contents('debug.log', '[debug] Created new wallet for receiver' . PHP_EOL, FILE_APPEND);
    } else {
        $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
        $stmt->execute([$amount, $to_id]);
        $new_balance = $to_wallet['balance'] + $amount;
        file_put_contents('debug.log', '[debug] Added to receiver wallet' . PHP_EOL, FILE_APPEND);
    }

    // حفظ سجل التحويل في جدول payments (إن لم يكن موجوداً، أنشئه)
    $pdo->exec('CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        from_id INT NOT NULL,
        to_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL
    )');
    file_put_contents('debug.log', '[debug] payments table checked/created' . PHP_EOL, FILE_APPEND);
    $stmt = $pdo->prepare('INSERT INTO payments (from_id, to_id, amount, payment_method, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$from_id, $to_id, $amount, $payment_method]);
    file_put_contents('debug.log', '[debug] Payment record inserted' . PHP_EOL, FILE_APPEND);

    if ($pdo->inTransaction()) {
        $pdo->commit();
        file_put_contents('debug.log', '[debug] Transaction committed successfully' . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents('debug.log', '[debug] No active transaction to commit' . PHP_EOL, FILE_APPEND);
    }
    
    echo json_encode(['success' => true, 'message' => 'تم التحويل بنجاح', 'new_balance' => $new_balance]);
    file_put_contents('debug.log', '[debug] Response sent successfully' . PHP_EOL, FILE_APPEND);
    exit;
} catch (PDOException $e) {
    file_put_contents('debug.log', '[debug] Exception caught: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
        file_put_contents('debug.log', '[debug] Transaction rolled back' . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents('debug.log', '[debug] No active transaction to roll back' . PHP_EOL, FILE_APPEND);
    }
    file_put_contents('debug.log', '[process_payment] PDOException: ' . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    file_put_contents('debug.log', '[debug] Error response sent' . PHP_EOL, FILE_APPEND);
    exit;
}
