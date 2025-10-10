<?php
// fund_deal.php — ملف موحد لإدارة جميع عمليات الصفقات (إنشاء، تمويل، تأكيد، نزاع، جلب CSRF)
// يدعم: action=create | fund | confirm | dispute | get_csrf

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ====== دوال مساعدة ======
function json_fail(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
function json_ok(array $payload): void {
    http_response_code(200);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ====== استقبال البيانات ======
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || empty($data)) {
    $data = $_POST ?? [];
}
$action = $data['action'] ?? $_GET['action'] ?? '';
if (!$action) {
    json_fail(400, ['error' => 'action مفقود']);
}

// ====== جلب CSRF (بدون قاعدة بيانات) ======
if ($action === 'get_csrf') {
    require_once __DIR__ . '/security.php'; // المسار الصحيح دائماً
    ensure_session();
    ensure_csrf_token();
    json_ok(['csrf_token' => get_csrf_token()]);
}

// تفعيل الحماية في جميع العمليات الأخرى
require_once __DIR__ . '/security.php';
if ($action !== 'get_csrf') {
    require_login_or_die();
    // تحقق من CSRF فقط إذا كان الطلب POST أو JSON
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $data['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        require_csrf_or_die($csrf_token);
    }
}

// ====== الاتصال بقاعدة البيانات ======
require_once __DIR__ . '/db.php'; // $pdo (PDO) و/أو $conn (mysqli)

// ====== إنشاء صفقة جديدة ======
if ($action === 'create') {
    // استخدم معرف المستخدم الحالي إذا لم يتم تمريره
    $buyer_id = intval($data['buyer_id'] ?? get_current_user_id());
    $seller_id = intval($data['seller_id'] ?? 0);
    $amount = floatval($data['amount'] ?? 0);
    $details = trim($data['details'] ?? '');
    $conversation_id = intval($data['conversation_id'] ?? 0);
    $account_id = intval($data['account_id'] ?? 0);
    
    if ($buyer_id <= 0 || $seller_id <= 0 || $amount < 0 || empty($details)) {
        json_fail(400, ['error' => 'بيانات غير صحيحة']);
    }
    
    // دعم كل من PDO وmysqli
    if (isset($pdo)) {
        $stmt = $pdo->prepare('INSERT INTO deals (buyer_id, seller_id, amount, details, status, conversation_id, account_id, created_at) VALUES (?, ?, ?, ?, "CREATED", ?, ?, NOW())');
        $stmt->execute([$buyer_id, $seller_id, $amount, $details, $conversation_id > 0 ? $conversation_id : null, $account_id > 0 ? $account_id : null]);
        $deal_id = $pdo->lastInsertId();
        json_ok(['success' => true, 'deal_id' => $deal_id, 'conversation_id' => $conversation_id, 'account_id' => $account_id]);
    } else if (isset($conn)) {
        $stmt = $conn->prepare('INSERT INTO deals (buyer_id, seller_id, amount, details, status, conversation_id, account_id, created_at) VALUES (?, ?, ?, ?, "CREATED", ?, ?, NOW())');
        $conversation_id_param = $conversation_id > 0 ? $conversation_id : null;
        $account_id_param = $account_id > 0 ? $account_id : null;
        $stmt->bind_param('iidsii', $buyer_id, $seller_id, $amount, $details, $conversation_id_param, $account_id_param);
        if ($stmt->execute()) {
            $deal_id = $stmt->insert_id;
            json_ok(['success' => true, 'deal_id' => $deal_id, 'conversation_id' => $conversation_id, 'account_id' => $account_id]);
        } else {
            json_fail(500, ['error' => 'فشل في إنشاء الصفقة']);
        }
    } else {
        json_fail(500, ['error' => 'لا يوجد اتصال بقاعدة البيانات']);
    }
}

// ====== تمويل الصفقة ======
if ($action === 'fund') {
    $deal_id  = isset($data['deal_id'])  ? (int)$data['deal_id']  : 0;
    $buyer_id = isset($data['buyer_id']) ? (int)$data['buyer_id'] : 0;
    if ($deal_id <= 0 || $buyer_id <= 0) {
        json_fail(400, ['error' => 'بيانات غير صحيحة', 'hint' => 'deal_id و buyer_id مطلوبان']);
    }
    try {
        $stmt = $pdo->prepare('SELECT id, amount, status, buyer_id FROM deals WHERE id = ? LIMIT 1');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch();
        if (!$deal) {
            json_fail(404, ['error' => 'الصفقة غير موجودة']);
        }
        $amount      = (float)$deal['amount'];
        $status      = (string)$deal['status'];
        $db_buyer_id = (int)$deal['buyer_id'];
        if ($status !== 'CREATED' || $buyer_id !== $db_buyer_id) {
            json_fail(403, ['error' => 'لا يمكن تمويل هذه الصفقة في وضعها الحالي']);
        }
        // التحقق من وجود محفظة للمشتري
        $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? LIMIT 1');
        $stmt->execute([$buyer_id]);
        $balance = $stmt->fetchColumn();
        if ($balance === false) {
            json_fail(404, ['error' => 'المشتري غير موجود أو لا يملك محفظة']);
        }
        if ((float)$balance < $amount) {
            json_fail(402, ['error' => 'رصيد غير كافٍ']);
        }
        $pdo->beginTransaction();
        // سحب المبلغ من الرصيد العادي وإضافته للرصيد المعلق
        $stmt = $pdo->prepare('UPDATE wallets SET balance = balance - :amt, pending_balance = pending_balance + :amt WHERE user_id = :uid AND balance >= :amt');
        $stmt->execute([':amt' => $amount, ':uid' => $buyer_id]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            json_fail(402, ['error' => 'الرصيد لم يعد كافيًا']);
        }
        $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, "ESCROW", ?, ?, 0)');
        $stmt->execute([$deal_id, $amount, $buyer_id]);
        $stmt = $pdo->prepare('UPDATE deals SET status = "ON_HOLD", escrow_amount = :amt, escrow_status = "FUNDED", updated_at = NOW() WHERE id = :did');
        $stmt->execute([':amt' => $amount, ':did' => $deal_id]);
        $pdo->commit();
        json_ok([
            'success' => true,
            'deal_id' => $deal_id,
            'amount'  => $amount,
            'status'  => 'ON_HOLD',
            'escrow_status' => 'FUNDED'
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[fund_deal.php] ' . $e->getMessage());
        json_fail(500, ['error' => 'فشل في تمويل الصفقة', 'details' => $e->getMessage()]);
    }
}

// ====== تأكيد الاستلام أو فتح نزاع ======
if ($action === 'confirm' || $action === 'dispute') {
    $deal_id = intval($data['deal_id'] ?? 0);
    $buyer_id = intval($data['buyer_id'] ?? 0);
    if ($deal_id <= 0 || $buyer_id <= 0) {
        json_fail(400, ['error' => 'بيانات غير صحيحة']);
    }
    $stmt = $pdo->prepare('SELECT status, buyer_id, seller_id, escrow_amount, escrow_status FROM deals WHERE id = ?');
    $stmt->execute([$deal_id]);
    $deal = $stmt->fetch();
    if (!$deal) {
        json_fail(404, ['error' => 'الصفقة غير موجودة']);
    }
    if ($buyer_id !== (int)$deal['buyer_id'] || $deal['status'] !== 'DELIVERED' || $deal['escrow_status'] !== 'FUNDED') {
        json_fail(403, ['error' => 'لا يمكن تنفيذ هذا الإجراء على الصفقة']);
    }
    if ($action === 'confirm') {
        try {
            $pdo->beginTransaction();
            
            // حساب الخصم (10% من المبلغ)
            $fee_percentage = 0.10; // 10%
            $fee_amount = (float)$deal['escrow_amount'] * $fee_percentage;
            $seller_amount = (float)$deal['escrow_amount'] - $fee_amount;
            
            // تحديث رصيد البائع (بعد خصم 10%) وخصم المبلغ من الرصيد المعلق للمشتري
            $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
            $stmt->execute([$seller_amount, (int)$deal['seller_id']]);
            
            // خصم المبلغ من الرصيد المعلق للمشتري
            $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?');
            $stmt->execute([(float)$deal['escrow_amount'], (int)$deal['buyer_id']]);
            
            // تسجيل المعاملة المالية للبائع
            $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) VALUES (?, "RELEASE", ?, NULL, ?, "تحرير أموال الصفقة للبائع بعد خصم رسوم المنصة")');
            $stmt->execute([$deal_id, $seller_amount, (int)$deal['seller_id']]);
            
            // تسجيل رسوم المنصة
            $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) VALUES (?, "FEE", ?, ?, NULL, "رسوم المنصة 10%")');
            $stmt->execute([$deal_id, $fee_amount, (int)$deal['seller_id']]);
            
            $stmt = $pdo->prepare('UPDATE deals SET status = "RELEASED", escrow_status = "RELEASED", released_at = NOW(), updated_at = NOW() WHERE id = ?');
            $stmt->execute([$deal_id]);
            $pdo->commit();
            json_ok(['success' => true, 'deal_id' => $deal_id, 'released' => true, 'seller_amount' => $seller_amount, 'fee_amount' => $fee_amount]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            json_fail(500, ['error' => 'فشل في تحويل المال للبائع']);
        }
    } else if ($action === 'dispute') {
        $stmt = $pdo->prepare('UPDATE deals SET status = "DISPUTED", updated_at = NOW() WHERE id = ?');
        if ($stmt->execute([$deal_id])) {
            json_ok(['success' => true, 'deal_id' => $deal_id, 'disputed' => true]);
        } else {
            json_fail(500, ['error' => 'فشل في فتح النزاع']);
        }
    }
}

json_fail(400, ['error' => 'action غير معروف']);
