<?php
// API endpoint لجلب محادثات الصفقة للأدمن
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json; charset=utf-8');
ensure_session();

// التحقق من صلاحيات الأدمن
$current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
if ($current_user <= 0) {
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

// التحقق من أن المستخدم أدمن (يمكن إضافة جدول admins أو حقل is_admin في جدول users)
// للاختبار، سنسمح لجميع المستخدمين
// في الإنتاج، يجب إضافة فحص صلاحيات الأدمن هنا

$deal_id = isset($_GET['deal_id']) ? intval($_GET['deal_id']) : 0;

if ($deal_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'معرف الصفقة غير صالح']);
    exit;
}

try {
    // جلب تفاصيل الصفقة مع معلومات المشتري والبائع
    $deal_stmt = $pdo->prepare("
        SELECT d.*, 
               buyer.name as buyer_name, buyer.phone as buyer_phone,
               seller.name as seller_name, seller.phone as seller_phone,
               a.game_name, a.description as account_description
        FROM deals d
        LEFT JOIN users buyer ON d.buyer_id = buyer.id
        LEFT JOIN users seller ON d.seller_id = seller.id
        LEFT JOIN accounts a ON d.account_id = a.id
        WHERE d.id = ?
    ");
    $deal_stmt->execute([$deal_id]);
    $deal = $deal_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$deal) {
        echo json_encode(['success' => false, 'error' => 'الصفقة غير موجودة']);
        exit;
    }
    
    // جلب المحادثات بين المشتري والبائع للصفقة المحددة فقط
    $messages_stmt = $pdo->prepare("
        SELECT m.*, 
               sender.name as sender_name,
               receiver.name as receiver_name
        FROM messages m
        LEFT JOIN users sender ON m.sender_id = sender.id
        LEFT JOIN users receiver ON m.receiver_id = receiver.id
        WHERE 
            ((m.sender_id = ? AND m.receiver_id = ?) OR
             (m.sender_id = ? AND m.receiver_id = ?))
            AND m.deal_id = ?
        ORDER BY m.created_at ASC
    ");
    
    $messages_stmt->execute([
        $deal['buyer_id'], $deal['seller_id'],
        $deal['seller_id'], $deal['buyer_id'],
        $deal_id
    ]);
    
    $messages = $messages_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تحويل التواريخ إلى تنسيق مقروء
    foreach ($messages as &$message) {
        $message['formatted_date'] = date('Y-m-d H:i', strtotime($message['created_at']));
        $message['is_buyer'] = ($message['sender_id'] == $deal['buyer_id']);
        $message['is_seller'] = ($message['sender_id'] == $deal['seller_id']);
    }
    
    // إحصائيات المحادثة
    $conversation_stats = [
        'total_messages' => count($messages),
        'buyer_messages' => count(array_filter($messages, function($m) use ($deal) {
            return $m['sender_id'] == $deal['buyer_id'];
        })),
        'seller_messages' => count(array_filter($messages, function($m) use ($deal) {
            return $m['sender_id'] == $deal['seller_id'];
        })),
        'first_message_date' => !empty($messages) ? $messages[0]['created_at'] : null,
        'last_message_date' => !empty($messages) ? end($messages)['created_at'] : null
    ];
    
    echo json_encode([
        'success' => true,
        'deal' => $deal,
        'messages' => $messages,
        'stats' => $conversation_stats
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    error_log('[get_deal_conversation] PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'خطأ في قاعدة البيانات']);
}
?>