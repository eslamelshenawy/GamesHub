<?php
session_start();
require_once 'api/db.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مسموح بالوصول']);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة طلب غير صحيحة']);
    exit;
}

// قراءة البيانات المرسلة
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'بيانات غير صحيحة']);
    exit;
}

$buyer_id = $_SESSION['user_id'];
$seller_id = intval($input['seller_id'] ?? 0);
$account_id = intval($input['account_id'] ?? 0);
$amount = floatval($input['amount'] ?? 0);
$conversation_id = intval($input['conversation_id'] ?? 0);

// التحقق من صحة البيانات
if ($seller_id <= 0 || $account_id <= 0 || $amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'بيانات غير مكتملة']);
    exit;
}

// التحقق من أن المشتري لا يشتري من نفسه
if ($buyer_id == $seller_id) {
    echo json_encode(['success' => false, 'error' => 'لا يمكنك شراء حساب من نفسك']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // التحقق من وجود الحساب وملكيته وسعره
    $stmt = $pdo->prepare("
        SELECT a.*, u.name as seller_name 
        FROM accounts a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = ? AND a.user_id = ?
    ");
    $stmt->execute([$account_id, $seller_id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$account) {
        throw new Exception('الحساب غير موجود أو غير متاح للبيع');
    }
    
    if ($account['price'] != $amount) {
        throw new Exception('السعر غير صحيح');
    }
    
    // التحقق من رصيد المشتري وإنشاء محفظة إذا لم تكن موجودة
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$buyer_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$wallet) {
        // إنشاء محفظة للمشتري
        $stmt = $pdo->prepare("INSERT INTO wallets (user_id, balance, pending_balance) VALUES (?, 0.00, 0.00)");
        $stmt->execute([$buyer_id]);
        $wallet = ['balance' => 0.00];
    }
    
    if ($wallet['balance'] < $amount) {
        throw new Exception('رصيدك غير كافي لإتمام هذه الصفقة');
    }
    
    // استخدام conversation_id المرسل أو البحث عن محادثة موجودة
    if ($conversation_id > 0) {
        // التحقق من صحة المحادثة
        $stmt = $pdo->prepare("
            SELECT id FROM conversations 
            WHERE id = ? AND ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
            LIMIT 1
        ");
        $stmt->execute([$conversation_id, $buyer_id, $seller_id, $seller_id, $buyer_id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            throw new Exception('المحادثة غير صحيحة أو غير موجودة');
        }
    } else {
        // البحث عن محادثة موجودة أو إنشاء محادثة جديدة
        $stmt = $pdo->prepare("
            SELECT id FROM conversations 
            WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$buyer_id, $seller_id, $seller_id, $buyer_id]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$conversation) {
            // إنشاء محادثة جديدة
            $stmt = $pdo->prepare("
                INSERT INTO conversations (user1_id, user2_id, last_message_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$buyer_id, $seller_id]);
            $conversation_id = $pdo->lastInsertId();
        } else {
            $conversation_id = $conversation['id'];
        }
    }
    
    // التحقق من عدم وجود صفقة نشطة للحساب
    $stmt = $pdo->prepare("
        SELECT id FROM deals 
        WHERE account_id = ? AND status IN ('CREATED', 'DELIVERED', 'FUNDED') 
        LIMIT 1
    ");
    $stmt->execute([$account_id]);
    $existing_deal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_deal) {
        throw new Exception('يوجد صفقة نشطة لهذا الحساب بالفعل');
    }
    
    // إنشاء الصفقة
    $deal_details = "صفقة شراء حساب: " . $account['game_name'] . " - " . $account['description'];
    $stmt = $pdo->prepare("
        INSERT INTO deals (buyer_id, seller_id, account_id, conversation_id, amount, details, status, escrow_status, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, 'CREATED', 'PENDING', NOW())
    ");
    $stmt->execute([$buyer_id, $seller_id, $account_id, $conversation_id, $amount, $deal_details]);
    $deal_id = $pdo->lastInsertId();
    
    // تحديث رصيد المشتري (نقل المبلغ من الرصيد العادي إلى الرصيد المعلق)
    $stmt = $pdo->prepare("
        UPDATE wallets 
        SET balance = balance - ?, pending_balance = pending_balance + ?, updated_at = NOW() 
        WHERE user_id = ? AND balance >= ?
    ");
    $stmt->execute([$amount, $amount, $buyer_id, $amount]);
    
    // التحقق من نجاح عملية تحويل الأموال
    if ($stmt->rowCount() === 0) {
        throw new Exception('فشل في تحويل الأموال - رصيد غير كافي أو خطأ في النظام');
    }
    
    // تسجيل المعاملة المالية
    $stmt = $pdo->prepare("
        INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user, created_at) 
        VALUES (?, 'ESCROW', ?, ?, ?, NOW())
    ");
    $stmt->execute([$deal_id, $amount, $buyer_id, $seller_id]);
    
    // إرسال رسالة في المحادثة
    $message_content = "🔒 تم بدء صفقة آمنة جديدة\n";
    $message_content .= "💰 المبلغ: {$amount} ج.م\n";
    $message_content .= "🎮 الحساب: {$account['game_name']}\n";
    $message_content .= "📋 رقم الصفقة: #{$deal_id}\n";
    $message_content .= "⏳ في انتظار تسليم الحساب من البائع";
    
    $stmt = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_text, deal_id, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$buyer_id, $seller_id, $message_content, $deal_id]);
    
    // ملاحظة: تم إزالة تحديث حالة الحساب لأن جدول accounts لا يحتوي على عمود status
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'تم بدء الصفقة الآمنة بنجاح',
        'deal' => [
            'id' => $deal_id,
            'status' => 'CREATED',
            'amount' => $amount,
            'account_title' => $account['game_name'],
            'seller_name' => $account['seller_name'],
            'conversation_id' => $conversation_id
        ]
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in start_secure_deal.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>