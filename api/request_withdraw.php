<?php
// api/request_withdraw.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once 'db.php';
// Session is now managed in db.php
require_once 'security.php';
ensure_session();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة']);
    exit;
}

$user_id = $_SESSION['user_id'];
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$method = isset($_POST['method']) ? trim($_POST['method']) : 'vodafone_cash';

if ($amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال مبلغ صحيح']);
    exit;
}
if (empty($phone)) {
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال رقم الهاتف']);
    exit;
}


// تحقق من الرصيد
$stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ?');
$stmt->execute([$user_id]);
$wallet = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$wallet || $wallet['balance'] < $amount) {
    echo json_encode(['success' => false, 'error' => 'الرصيد غير كافٍ']);
    exit;
}

// تحقق من وجود طلب سحب قيد الانتظار
$stmt = $pdo->prepare('SELECT COUNT(*) FROM withdraw_requests WHERE user_id = ? AND status = "pending"');
$stmt->execute([$user_id]);
$pending_count = $stmt->fetchColumn();
if ($pending_count > 0) {
    echo json_encode(['success' => false, 'error' => 'لديك طلب سحب قيد المراجعة بالفعل. انتظر حتى يتم معالجة الطلب قبل إرسال طلب جديد.']);
    exit;
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('INSERT INTO withdraw_requests (user_id, amount, phone, method, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $stmt->execute([$user_id, $amount, $phone, $method, 'pending']);
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'تم إرسال طلب السحب بنجاح! سيتم مراجعته من الإدارة.']);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء معالجة الطلب: ' . $e->getMessage()]);
}
