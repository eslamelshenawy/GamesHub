<?php
// API للأدمن لإرجاع الأموال من الرصيد المعلق للإدارة إلى المستخدم
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';
require_once 'security.php';
ensure_session();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit;
}

// التحقق من صلاحيات الأدمن
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array($user['role'], ['admin', 'system'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'غير مصرح لك بالوصول'
        ]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء التحقق من الصلاحيات'
    ]);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموحة'
    ]);
    exit;
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
$amount = isset($input['amount']) ? floatval($input['amount']) : 0;
$deal_id = isset($input['deal_id']) ? intval($input['deal_id']) : null;
$reason = isset($input['reason']) ? trim($input['reason']) : 'استرداد من الإدارة';

// التحقق من البيانات
if ($user_id <= 0 || $amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'البيانات المدخلة غير صحيحة'
    ]);
    exit;
}

try {
    $pdo->beginTransaction();

    // الحصول على حساب الإدارة
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'system' OR role = 'admin' ORDER BY role DESC LIMIT 1");
    $stmt->execute();
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_user) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'لم يتم العثور على حساب الإدارة'
        ]);
        exit;
    }

    // التحقق من وجود رصيد معلق كافٍ لدى الإدارة
    $stmt = $pdo->prepare("SELECT pending_balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$admin_user['id']]);
    $admin_wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin_wallet || $admin_wallet['pending_balance'] < $amount) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'الرصيد المعلق للإدارة غير كافٍ لإتمام العملية',
            'available' => $admin_wallet ? $admin_wallet['pending_balance'] : 0,
            'required' => $amount
        ]);
        exit;
    }

    // التحقق من وجود محفظة للمستخدم
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user_wallet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_wallet) {
        // إنشاء محفظة للمستخدم
        $stmt = $pdo->prepare("INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)");
        $stmt->execute([$user_id]);
    }

    // خصم المبلغ من الرصيد المعلق للإدارة
    $stmt = $pdo->prepare("UPDATE wallets SET pending_balance = pending_balance - ? WHERE user_id = ?");
    $stmt->execute([$amount, $admin_user['id']]);

    // إضافة المبلغ للرصيد الأساسي للمستخدم
    $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
    $stmt->execute([$amount, $user_id]);

    // تسجيل المعاملة المالية
    $stmt = $pdo->prepare("INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description, created_at) VALUES (?, 'ADMIN_REFUND', ?, ?, ?, ?, NOW())");
    $stmt->execute([$deal_id, $amount, $admin_user['id'], $user_id, $reason]);

    // تسجيل المعاملة في wallet_transactions
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'refund', ?, NOW())");
    $stmt->execute([$user_id, $amount, "استرداد من الإدارة: {$reason}"]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'تم إرجاع المبلغ للمستخدم بنجاح',
        'amount' => $amount,
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error in admin_refund_to_user.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء إرجاع المبلغ: ' . $e->getMessage()
    ]);
}
?>
