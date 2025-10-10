<?php
// api/confirm_delivery.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['deal_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
    exit;
}

$deal_id = intval($input['deal_id']);

try {
    $pdo->beginTransaction();
    
    // التحقق من الصفقة والتأكد أن المستخدم هو المشتري
    $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ? AND buyer_id = ? FOR UPDATE');
    $stmt->execute([$deal_id, $user_id]);
    $deal = $stmt->fetch();
    
    if (!$deal) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو غير مخولة']);
        exit;
    }
    
    if ($deal['status'] !== 'CREATED') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لا يمكن تأكيد استلام هذه الصفقة في حالتها الحالية']);
        exit;
    }
    
    if ($deal['escrow_status'] !== 'FUNDED') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'الضمان غير مُمول']);
        exit;
    }
    
    // تحديث حالة الصفقة إلى "تم التسليم" - في انتظار مراجعة الإدارة
    $stmt = $pdo->prepare('UPDATE deals SET status = "DELIVERED", updated_at = NOW() WHERE id = ?');
    $stmt->execute([$deal_id]);
    
    // إضافة رسالة في المحادثة
    $message = "تم تأكيد استلام الحساب من قبل المشتري. الصفقة الآن في انتظار مراجعة الإدارة لإتمام التحويل.";
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$user_id, $deal['seller_id'], $message, $deal_id]);
    
    // إشعار للإدارة
    $admin_message = "صفقة جديدة تحتاج مراجعة - رقم الصفقة: {$deal_id}";
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message) SELECT id, ? FROM users WHERE role = "admin"');
    $stmt->execute([$admin_message]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم تأكيد الاستلام بنجاح. الصفقة الآن في انتظار مراجعة الإدارة.'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[confirm_delivery] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
}
?>