<?php
require_once 'db.php';
require_once 'security.php';

// بدء الجلسة
ensure_session();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['deal_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
    exit;
}

$deal_id = intval($input['deal_id']);
$user_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();
    
    // الحصول على تفاصيل الصفقة والتحقق من صلاحية المستخدم
    $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ? FOR UPDATE');
    $stmt->execute([$deal_id]);
    $deal = $stmt->fetch();
    
    if (!$deal) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة']);
        exit;
    }
    
    // التحقق من أن المستخدم هو المشتري أو البائع في هذه الصفقة
    if ($deal['buyer_id'] != $user_id && $deal['seller_id'] != $user_id) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'غير مسموح لك بتمويل هذه الصفقة']);
        exit;
    }
    
    // التحقق من حالة الصفقة - يجب أن تكون في حالة مناسبة للتمويل
    $allowed_statuses = ['PENDING', 'APPROVED', 'IN_PROGRESS' , 'CREATED'];
    if (!in_array($deal['status'], $allowed_statuses)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لا يمكن تمويل الصفقة في الحالة الحالية: ' . $deal['status']]);
        exit;
    }
    
    // تحديث حالة الصفقة إلى FUNDED
    $stmt = $pdo->prepare('UPDATE deals SET status = "FUNDED", updated_at = NOW() WHERE id = ?');
    $stmt->execute([$deal_id]);
    
    // الحصول على اسم المستخدم الحالي لإضافة رسالة النظام
    $stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $current_user['name'];
    
    // إضافة رسالة في المحادثة
    $message = "تم تمويل الصفقة من قبل {$user_name}. الصفقة الآن في حالة FUNDED.";
    
    // إرسال الرسالة للمشتري
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmt->execute([$user_id, $deal['buyer_id'], $message, $deal_id]);
    
    // إرسال الرسالة للبائع (إذا كان مختلفاً عن المرسل)
    if ($deal['seller_id'] != $user_id) {
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id, created_at) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$user_id, $deal['seller_id'], $message, $deal_id]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم تمويل الصفقة بنجاح',
        'deal_status' => 'FUNDED'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[fund_deal_user] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>