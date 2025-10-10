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
        
        // إرجاع المبلغ من الرصيد المعلق إلى رصيد المشتري - نستخدم مبلغ الصفقة الأصلي فقط
        $amount_to_refund = $deal['amount'];
        
        // تحديث المحفظة - إرجاع المبلغ من الرصيد المعلق إلى الرصيد الأساسي
        $stmt = $pdo->prepare('UPDATE wallets SET pending_balance = pending_balance - ?, balance = balance + ? WHERE user_id = ?');
        $stmt->execute([$amount_to_refund, $amount_to_refund, $deal['buyer_id']]);
        
        // تحديث حالة الصفقة
        $stmt = $pdo->prepare('UPDATE deals SET status = "CANCELLED", escrow_status = "REFUNDED", updated_at = NOW() WHERE id = ?');
        $stmt->execute([$deal_id]);
        
        // الحصول على معرف المستخدم النظام
        $system_user_stmt = $pdo->prepare('SELECT id FROM users WHERE role = "system" LIMIT 1');
        $system_user_stmt->execute();
        $system_user = $system_user_stmt->fetch();
        $system_user_id = $system_user ? $system_user['id'] : null;
        
        // تسجيل المعاملة المالية
        $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, "REFUND", ?, ?, ?)');
        $stmt->execute([$deal_id, $amount_to_refund, $system_user_id, $deal['buyer_id']]);
        
        // تسجيل معاملة في wallet_transactions
        $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, "refund", ?)');
        $stmt->execute([$deal['buyer_id'], $amount_to_refund, "استرداد صفقة رقم {$deal_id} - {$reason}"]);
        
        // إضافة رسالة في المحادثة
        $message = "تم الغاء الصفقة من قبل المستخدم. : . تم إرجاع المبلغ {$amount_to_refund} جنيه إلى المشتري.";
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['buyer_id'], $message, $deal_id]);
        
        $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$system_user_id, $deal['seller_id'], $message, $deal_id]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'تم رفض الصفقة وإرجاع المبلغ']);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('[cancelDeal] PDOException: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
?>