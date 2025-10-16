<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير صحيحة']);
    exit;
}

$type = $_POST['type'] ?? '';

try {
    switch ($type) {
        case 'user':
            addUser($pdo, $_POST);
            break;
        case 'account':
            addAccount($pdo, $_POST);
            break;
        case 'deal':
            addDeal($pdo, $_POST);
            break;
        case 'report':
            addReport($pdo, $_POST);
            break;
        case 'topup':
            addTopup($pdo, $_POST);
            break;
        case 'withdrawal':
            addWithdrawal($pdo, $_POST);
            break;
        default:
            throw new Exception('نوع البيانات غير صحيح');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

function addUser($pdo, $data) {
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';
    $phone = $data['phone'] ?? '';
    $password = $data['password'] ?? '';
    $age = $data['age'] ?? 0;
    $gender = $data['gender'] ?? 'male';
    $role = $data['role'] ?? 'buyer';
    $balance = $data['balance'] ?? 0;

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        throw new Exception('الرجاء ملء جميع الحقول المطلوبة');
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, age, gender, role, balance, wallet_balance)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $hashedPassword, $age, $gender, $role, $balance, $balance]);

    $userId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO wallets (user_id, balance) VALUES (?, ?)");
    $stmt->execute([$userId, $balance]);

    echo json_encode(['success' => true, 'message' => 'تم إضافة المستخدم بنجاح', 'user_id' => $userId]);
}

function addAccount($pdo, $data) {
    $user_id = $data['user_id'] ?? 0;
    $game_name = $data['game_name'] ?? '';
    $description = $data['description'] ?? '';
    $price = $data['price'] ?? 0;

    if (empty($user_id) || empty($game_name) || empty($description) || empty($price)) {
        throw new Exception('الرجاء ملء جميع الحقول المطلوبة');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('المستخدم غير موجود');
    }

    $stmt = $pdo->prepare("INSERT INTO accounts (user_id, game_name, description, price, created_at)
                          VALUES (?, ?, ?, ?, CURDATE())");
    $stmt->execute([$user_id, $game_name, $description, $price]);

    $accountId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'message' => 'تم إضافة الحساب بنجاح', 'account_id' => $accountId]);
}

function addDeal($pdo, $data) {
    $buyer_id = $data['buyer_id'] ?? 0;
    $seller_id = $data['seller_id'] ?? 0;
    $amount = $data['amount'] ?? 0;
    $details = $data['details'] ?? '';
    $status = $data['status'] ?? 'CREATED';
    $account_id = $data['account_id'] ?? null;
    $fee_percentage = $data['fee_percentage'] ?? 10;

    if (empty($buyer_id) || empty($seller_id) || empty($amount) || empty($details)) {
        throw new Exception('الرجاء ملء جميع الحقول المطلوبة');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$buyer_id]);
    if (!$stmt->fetch()) {
        throw new Exception('المشتري غير موجود');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$seller_id]);
    if (!$stmt->fetch()) {
        throw new Exception('البائع غير موجود');
    }

    if ($account_id) {
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE id = ?");
        $stmt->execute([$account_id]);
        if (!$stmt->fetch()) {
            throw new Exception('الحساب غير موجود');
        }
    }

    $platform_fee = ($amount * $fee_percentage) / 100;
    $seller_amount = $amount - $platform_fee;

    $stmt = $pdo->prepare("INSERT INTO deals (buyer_id, seller_id, amount, details, status, account_id,
                          platform_fee, seller_amount, fee_percentage)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$buyer_id, $seller_id, $amount, $details, $status, $account_id,
                    $platform_fee, $seller_amount, $fee_percentage]);

    $dealId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'message' => 'تم إضافة الصفقة بنجاح', 'deal_id' => $dealId]);
}

function addReport($pdo, $data) {
    $reporter_id = $data['reporter_id'] ?? 0;
    $reported_user_id = $data['reported_user_id'] ?? 0;
    $conversation_id = $data['conversation_id'] ?? null;
    $reason = $data['reason'] ?? '';
    $status = $data['status'] ?? 'pending';

    if (empty($reporter_id) || empty($reported_user_id) || empty($reason)) {
        throw new Exception('الرجاء ملء جميع الحقول المطلوبة');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$reporter_id]);
    if (!$stmt->fetch()) {
        throw new Exception('المبلغ غير موجود');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$reported_user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('المبلغ عنه غير موجود');
    }

    if ($conversation_id) {
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ?");
        $stmt->execute([$conversation_id]);
        if (!$stmt->fetch()) {
            throw new Exception('المحادثة غير موجودة');
        }
    }

    $stmt = $pdo->prepare("INSERT INTO reports (reporter_id, reported_user_id, conversation_id, reason, status)
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$reporter_id, $reported_user_id, $conversation_id, $reason, $status]);

    $reportId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'message' => 'تم إضافة البلاغ بنجاح', 'report_id' => $reportId]);
}

function addTopup($pdo, $data) {
    $user_id = $data['user_id'] ?? 0;
    $amount = $data['amount'] ?? 0;
    $method = $data['method'] ?? 'vodafone_cash';
    $phone = $data['phone'] ?? '';
    $status = $data['status'] ?? 'pending';

    if (empty($user_id) || empty($amount)) {
        throw new Exception('الرجاء ملء جميع الحقول المطلوبة');
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    if (!$stmt->fetch()) {
        throw new Exception('المستخدم غير موجود');
    }

    $stmt = $pdo->prepare("INSERT INTO wallet_topups (user_id, method, amount, phone, status, created_at)
                          VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $method, $amount, $phone, $status]);

    $topupId = $pdo->lastInsertId();

    if ($status === 'approved') {
        $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$amount, $user_id]);

        $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at)
                              VALUES (?, ?, 'deposit', 'شحن المحفظة', NOW())");
        $stmt->execute([$user_id, $amount]);
    }

    echo json_encode(['success' => true, 'message' => 'تم إضافة طلب الشحن بنجاح', 'topup_id' => $topupId]);
}

function addWithdrawal($pdo, $data) {
    $user_id = $data['user_id'] ?? 0;
    $amount = $data['amount'] ?? 0;
    $phone = $data['phone'] ?? '';
    $method = $data['method'] ?? 'vodafone_cash';
    $status = $data['status'] ?? 'pending';

    if (empty($user_id) || empty($amount) || empty($phone)) {
        throw new Exception('الرجاء ملء جميع الحقول المطلوبة');
    }

    $stmt = $pdo->prepare("SELECT id, wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception('المستخدم غير موجود');
    }

    if ($status === 'approved' && $user['wallet_balance'] < $amount) {
        throw new Exception('رصيد المستخدم غير كافٍ');
    }

    $stmt = $pdo->prepare("INSERT INTO withdraw_requests (user_id, amount, phone, method, status, created_at)
                          VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $amount, $phone, $method, $status]);

    $withdrawalId = $pdo->lastInsertId();

    if ($status === 'approved') {
        $stmt = $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        $stmt->execute([$amount, $user_id]);

        $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at)
                              VALUES (?, ?, 'withdraw', 'سحب من المحفظة', NOW())");
        $stmt->execute([$user_id, $amount]);
    }

    echo json_encode(['success' => true, 'message' => 'تم إضافة طلب السحب بنجاح', 'withdrawal_id' => $withdrawalId]);
}
?>
