<?php
// api/start_deal.php
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

$buyer_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['seller_id']) || !isset($input['amount']) || !isset($input['account_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'بيانات غير مكتملة']);
    exit;
}

$seller_id = intval($input['seller_id']);
$amount = floatval($input['amount']);
$account_id = intval($input['account_id']);
$details = $input['details'] ?? 'صفقة شراء حساب';

if ($buyer_id === $seller_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'لا يمكن بدء صفقة مع نفسك']);
    exit;
}

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'المبلغ يجب أن يكون أكبر من صفر']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // التحقق من رصيد المشتري
    $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
    $stmt->execute([$buyer_id]);
    $buyer_wallet = $stmt->fetch();
    
    if (!$buyer_wallet || $buyer_wallet['balance'] < $amount) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'رصيد غير كافي']);
        exit;
    }
    
    // التحقق من وجود الحساب والبائع
    $stmt = $pdo->prepare('SELECT user_id, price FROM accounts WHERE id = ?');
    $stmt->execute([$account_id]);
    $account = $stmt->fetch();
    
    if (!$account || $account['user_id'] != $seller_id) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'الحساب غير موجود أو غير مملوك للبائع']);
        exit;
    }
    
    if ($account['price'] != $amount) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'المبلغ لا يطابق سعر الحساب']);
        exit;
    }
    
    // البحث عن محادثة موجودة أو إنشاء جديدة
    $stmt = $pdo->prepare('SELECT id FROM conversations WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)');
    $stmt->execute([$buyer_id, $seller_id, $seller_id, $buyer_id]);
    $conversation = $stmt->fetch();
    
    $conversation_id = null;
    if ($conversation) {
        $conversation_id = $conversation['id'];
    } else {
        // إنشاء محادثة جديدة
        $stmt = $pdo->prepare('INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)');
        $stmt->execute([$buyer_id, $seller_id]);
        $conversation_id = $pdo->lastInsertId();
    }
    
    // إنشاء الصفقة مع تسجيل من بدأ الصفقة
    $stmt = $pdo->prepare('INSERT INTO deals (buyer_id, seller_id, account_id, conversation_id, amount, details, status, escrow_amount, escrow_status, deal_initiator_id) VALUES (?, ?, ?, ?, ?, ?, "CREATED", ?, "FUNDED", ?)');
    $stmt->execute([$buyer_id, $seller_id, $account_id, $conversation_id, $amount, $details, $amount, $buyer_id]);
    $deal_id = $pdo->lastInsertId();
    
    // سحب المبلغ من رصيد المشتري وإضافته للرصيد المعلق
    $stmt = $pdo->prepare('UPDATE wallets SET balance = balance - ?, pending_balance = pending_balance + ? WHERE user_id = ?');
    $stmt->execute([$amount, $amount, $buyer_id]);
    
    // تسجيل المعاملة المالية
    $stmt = $pdo->prepare('INSERT INTO financial_logs (deal_id, type, amount, from_user, to_user) VALUES (?, "ESCROW", ?, ?, ?)');
    $stmt->execute([$deal_id, $amount, $buyer_id, 0]); // 0 يعني النظام
    
    // إضافة رسالة في المحادثة
    $message = "تم بدء صفقة جديدة بمبلغ {$amount} جنيه لشراء الحساب. المبلغ محجوز في الضمان.";
    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, message_text, deal_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$buyer_id, $seller_id, $message, $deal_id]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'تم بدء الصفقة بنجاح. المبلغ محجوز في الضمان.',
        'deal_id' => $deal_id,
        'conversation_id' => $conversation_id
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[start_deal] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
}
?>