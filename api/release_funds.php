<?php
// api/release_funds.php
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
$deal_id = $_POST['deal_id'] ?? null;

if (!$deal_id || !is_numeric($deal_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // جلب بيانات الصفقة والتحقق من الصلاحيات
    $stmt = $pdo->prepare('
        SELECT d.*, w.balance as seller_balance 
        FROM deals d
        LEFT JOIN wallets w ON d.seller_id = w.user_id
        WHERE d.id = ? AND d.deal_initiator_id = ?
    ');
    $stmt->execute([$deal_id, $user_id]);
    $deal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // تحديث حالة المعالجة في قاعدة البيانات
    $stmt = $pdo->prepare('
        UPDATE deals 
        SET release_funds_processing = TRUE,
            release_funds_requested_at = NOW(),
            release_funds_requested_by = ?
        WHERE id = ?
    ');
    $stmt->execute([$user_id, $deal_id]);
    
    if (!$deal) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'غير مسموح لك بتحرير أموال هذه الصفقة']);
        exit;
    }
    
    // التحقق من حالة الصفقة
    if ($deal['status'] !== 'DELIVERED') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لا يمكن تحرير الأموال إلا بعد تسليم الحساب']);
        exit;
    }
    
    if ($deal['escrow_status'] !== 'FUNDED') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'الأموال غير متاحة للتحرير']);
        exit;
    }
    
    // تحديث حالة الصفقة إلى مكتملة وإزالة حالة المعالجة مع حفظ تفاصيل الرسوم
    $stmt = $pdo->prepare('
        UPDATE deals 
        SET status = "COMPLETED", 
            escrow_status = "RELEASED",
            delivery_confirmed = TRUE,
            delivery_confirmed_at = NOW(),
            delivery_confirmed_by = ?,
            release_funds_processing = FALSE,
            platform_fee = ?,
            seller_amount = ?,
            fee_percentage = ?,
            updated_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute([$user_id, $fee_amount, $seller_amount, 10.00, $deal_id]);
    
    // حساب الخصم (10% من المبلغ)
    $fee_percentage = 0.10; // 10%
    $fee_amount = $deal['escrow_amount'] * $fee_percentage;
    $seller_amount = $deal['escrow_amount'] - $fee_amount;
    
    // تحديث رصيد البائع (المبلغ بعد خصم 10%)
    $stmt = $pdo->prepare('
        UPDATE wallets 
        SET balance = balance + ? 
        WHERE user_id = ?
    ');
    $stmt->execute([$seller_amount, $deal['seller_id']]);
    
    // إضافة الرسوم إلى رصيد النظام (إذا كان هناك مستخدم نظام)
    $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" OR role = "admin" LIMIT 1');
    $system_user_stmt->execute();
    $system_user = $system_user_stmt->fetch();
    
    if ($system_user) {
        $stmt = $pdo->prepare('
            UPDATE wallets 
            SET balance = balance + ? 
            WHERE user_id = ?
        ');
        $stmt->execute([$fee_amount, $system_user['id']]);
    }
    
    // تحديث رصيد المشتري المعلق (إزالة المبلغ من الرصيد المعلق)
    $stmt = $pdo->prepare('
        UPDATE wallets 
        SET pending_balance = pending_balance - ? 
        WHERE user_id = ?
    ');
    $stmt->execute([$deal['escrow_amount'], $deal['buyer_id']]);
    
    // تسجيل المعاملة المالية (المبلغ للبائع)
    $stmt = $pdo->prepare('
        INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) 
        VALUES (?, "RELEASE", ?, ?, ?, "تحرير أموال الصفقة للبائع بعد خصم رسوم المنصة")
    ');
    $stmt->execute([$deal_id, $seller_amount, 0, $deal['seller_id']]);
    
    // تسجيل رسوم المنصة
    if ($system_user) {
        $stmt = $pdo->prepare('
            INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, description) 
            VALUES (?, "FEE", ?, ?, ?, "رسوم المنصة 10%")
        ');
        $stmt->execute([$deal_id, $fee_amount, $deal['seller_id'], $system_user['id']]);
    }
    
    // إضافة رسالة في المحادثة
    $stmt = $pdo->prepare('
        INSERT INTO messages (sender_id, receiver_id, message_text, deal_id, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ');
    $message = "تم تحرير أموال الصفقة بمبلغ {$seller_amount} جنيه (بعد خصم رسوم المنصة 10% = {$fee_amount} جنيه). الصفقة مكتملة.";
    $stmt->execute([0, $deal['seller_id'], $message, $deal_id]); // 0 = النظام
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم تحرير الأموال بنجاح وإرسالها للبائع'
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    
    // إزالة حالة المعالجة في حالة الخطأ
    try {
        $stmt = $pdo->prepare('
            UPDATE deals 
            SET release_funds_processing = FALSE
            WHERE id = ?
        ');
        $stmt->execute([$deal_id]);
    } catch (Exception $ex) {
        error_log('[release_funds] Failed to reset processing state: ' . $ex->getMessage());
    }
    
    error_log('[release_funds] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
}
?>