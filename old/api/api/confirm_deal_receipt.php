<?php
require_once '../config/database.php';
// Session is now managed in db.php
require_once 'security.php';
ensure_session();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'غير مسموح - يجب تسجيل الدخول']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']);
    exit;
}

if (!isset($_POST['deal_id'])) {
    echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
    exit;
}

$deal_id = intval($_POST['deal_id']);
$user_id = $_SESSION['user_id'];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // التحقق من وجود الصفقة وأن المستخدم هو المشتري
    $stmt = $pdo->prepare("SELECT * FROM deals WHERE id = ? AND buyer_id = ? AND status = 'CREATED'");
    $stmt->execute([$deal_id, $user_id]);
    $deal = $stmt->fetch();
    
    if (!$deal) {
        echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو غير مسموح لك بالوصول إليها']);
        exit;
    }
    
    // تحديث حالة الصفقة إلى FUNDED وتحديث escrow_status
    $stmt = $pdo->prepare("UPDATE deals SET status = 'FUNDED', escrow_status = 'FUNDED', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$deal_id]);
    
    // إضافة رسالة في المحادثة
    $message = "تم تأكيد استلام بيانات الحساب. الصفقة الآن في انتظار موافقة الإدارة.";
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, message_type, created_at) VALUES (?, ?, ?, 'system', NOW())");
    $stmt->execute([$deal['conversation_id'], $user_id, $message]);
    
    // تسجيل العملية في السجل المالي
    $stmt = $pdo->prepare("INSERT INTO financial_logs (deal_id, action, amount, description, created_at) VALUES (?, 'RECEIPT_CONFIRMED', ?, 'تأكيد استلام بيانات الحساب', NOW())");
    $stmt->execute([$deal_id, $deal['amount']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تأكيد استلام البيانات بنجاح'
    ]);
    
} catch (Exception $e) {
    error_log("Error in confirm_deal_receipt.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في الخادم']);
}
?>