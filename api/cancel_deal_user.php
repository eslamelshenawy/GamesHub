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



  global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'طريقة غير مسموحة']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['deal_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'معرف الصفقة مطلوب']);
        return;
    }
    
    $deal_id = intval($input['deal_id']);
    $reason = $input['reason'] ?? 'لم يتم تحديد السبب';
    
    try {
        $pdo->beginTransaction();
        
        // الحصول على تفاصيل الصفقة
        $stmt = $pdo->prepare('SELECT * FROM deals WHERE id = ?  FOR UPDATE');
        $stmt->execute([$deal_id]);
        $deal = $stmt->fetch();
        
        if (!$deal) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة أو غير قابلة للرفض']);
            return;
        }
        
        // التحقق من وجود محفظة للمشتري وإنشاؤها إذا لم تكن موجودة
        $stmt = $pdo->prepare('SELECT pending_balance FROM wallets WHERE user_id = ?');
        $stmt->execute([$deal['buyer_id']]);
        $wallet = $stmt->fetch();
        
        if (!$wallet) {
            // إنشاء محفظة للمشتري
            $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)');
            $stmt->execute([$deal['buyer_id']]);
            $wallet = ['pending_balance' => 0.00];
        }
        
        // بدلاً من إلغاء الصفقة مباشرة، نحولها للإدارة للمراجعة
        // تحديث حالة الصفقة لـ PENDING_CANCEL
        $stmt = $pdo->prepare('UPDATE deals SET status = "PENDING_CANCEL", cancel_reason = ?, cancel_requested_by = "buyer", cancel_requested_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$reason, $deal_id]);

        // ملحوظة: الأموال تبقى في pending_balance حتى تقرر الإدارة
        
        // الحصول على معرف المستخدم النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        $system_user_id = $system_user ? $system_user['id'] : null;

        // إضافة رسالة في المحادثة
        $message = "⚠️ طلب إلغاء الصفقة من قبل المشتري. السبب: {$reason}\n\nالصفقة الآن في انتظار مراجعة الإدارة.";
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['buyer_id'], $message, $deal_id]);

        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['seller_id'], $message, $deal_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'تم إرسال طلب الإلغاء للإدارة للمراجعة']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[cancelDeal] PDOException: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
?>