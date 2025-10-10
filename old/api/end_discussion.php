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
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // التحقق من وجود الصفقة وأن المستخدم مشارك فيها
    $stmt = $pdo->prepare("SELECT * FROM deals WHERE id = ? AND (buyer_id = ? OR seller_id = ?)");
    $stmt->execute([$deal_id, $user_id, $user_id]);
    $deal = $stmt->fetch();
    
    if (!$deal) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو غير مسموح لك بإنهاء المناقشة']);
        exit;
    }
    
    // التحقق من أن الصفقة لم تنته بعد
    if (in_array($deal['status'], ['RELEASED', 'REFUNDED', 'CANCELLED'])) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'الصفقة منتهية بالفعل']);
        exit;
    }
    
    // تحديث حالة الصفقة لإنهاء المناقشة
    $stmt = $pdo->prepare("UPDATE deals SET discussion_ended = 1, discussion_ended_by = ?, discussion_ended_at = NOW() WHERE id = ?");
    $stmt->execute([$user_id, $deal_id]);
    
    // إضافة رسالة نظام لإعلام الطرف الآخر
    $other_user_id = ($deal['buyer_id'] == $user_id) ? $deal['seller_id'] : $deal['buyer_id'];
    
    // الحصول على اسم المستخدم الذي أنهى المناقشة
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$username = $stmt->fetchColumn();

// إضافة رسالة نظام
$system_message = "تم إنهاء المناقشة من قبل {$username}. لا يمكن إرسال رسائل جديدة.";
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_id, message, is_system_message, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->execute([$user_id, $other_user_id, $system_message]);
    
    // تأكيد المعاملة
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'تم إنهاء المناقشة بنجاح']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error ending discussion: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'حدث خطأ أثناء إنهاء المناقشة']);
}
?>