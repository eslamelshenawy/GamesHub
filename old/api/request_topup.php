<?php
// api/request_topup.php
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة']);
    exit;
}

$method = isset($_POST['method']) ? trim($_POST['method']) : '';
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$receipt = '';

// رفع صورة الإيصال إذا وجدت
if (!empty($_FILES['receipt']['name'])) {
    $upload_dir = '../uploads/';
    $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
    $file_name = uniqid('receipt_') . '.' . $ext;
    $target = $upload_dir . $file_name;
    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $target)) {
        $receipt = $file_name; // حفظ اسم الملف فقط
    }
}

if ($method === '' || $amount <= 0 || $phone === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال جميع البيانات المطلوبة']);
    exit;
}


// تحقق إذا كان لدى المستخدم طلب معلق بالفعل
$check = $pdo->prepare('SELECT COUNT(*) FROM wallet_topups WHERE user_id = ? AND status = "pending"');
$check->execute([$user_id]);
$pending_count = $check->fetchColumn();
if ($pending_count > 0) {
    echo json_encode(['success' => false, 'error' => 'لديك طلب شحن قيد المراجعة بالفعل. انتظر حتى يتم معالجة الطلب قبل إرسال طلب جديد.']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO wallet_topups (user_id, method, amount, phone, receipt, status, created_at) VALUES (?, ?, ?, ?, ?, "pending", NOW())');
    $stmt->execute([$user_id, $method, $amount, $phone, $receipt]);
    echo json_encode(['success' => true, 'message' => 'تم إرسال طلب الشحن بنجاح. سيتم مراجعته من الإدارة.']);
    exit;
} catch (PDOException $e) {
    error_log('[request_topup] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    exit;
}
