<?php
require_once 'db.php';
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
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // التحقق من وجود الصفقة وأن المستخدم هو المشتري وأن الصفقة لم يتم تمويلها بعد
    $stmt = $pdo->prepare("SELECT * FROM deals WHERE id = ? AND buyer_id = ? AND status = 'CREATED'");
    $stmt->execute([$deal_id, $user_id]);
    $deal = $stmt->fetch();
    
    if (!$deal) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو غير مسموح لك بإلغائها']);
        exit;
    }
    
    // التحقق من أن الصفقة لم يتم تمويلها (لا يمكن إلغاء الصفقات الممولة)
    if ($deal['status'] == 'FUNDED') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'لا يمكن إلغاء الصفقة بعد تمويلها']);
        exit;
    }
    
    // إرجاع الأموال المعلقة إلى محفظة المشتري
    $stmt = $pdo->prepare("UPDATE users SET pending_balance = pending_balance - ?, balance = balance + ? WHERE id = ?");
    $stmt->execute([$deal['amount'], $deal['amount'], $user_id]);
    
    // تحديث حالة الصفقة إلى CANCELLED وتحديث escrow_status
    $stmt = $pdo->prepare("UPDATE deals SET status = 'CANCELLED', escrow_status = 'CANCELLED', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$deal_id]);
    
    // إضافة رسالة في المحادثة
    $message = "تم إلغاء الصفقة وإرجاع الأموال إلى محفظتك.";
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, message, message_type, created_at) VALUES (?, ?, ?, 'system', NOW())");
    $stmt->execute([$deal['conversation_id'], $user_id, $message]);
    
    // تسجيل العملية في السجل المالي
    $stmt = $pdo->prepare("INSERT INTO financial_logs (deal_id, action, amount, description, created_at) VALUES (?, 'DEAL_CANCELLED', ?, 'إلغاء الصفقة وإرجاع الأموال', NOW())");
    $stmt->execute([$deal_id, $deal['amount']]);
    
    // تأكيد المعاملة
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إلغاء الصفقة وإرجاع الأموال بنجاح'
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in cancel_deal.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ في الخادم']);
}
?>