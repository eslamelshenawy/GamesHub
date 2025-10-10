<?php
// API لجلب رسائل المحادثة الإدارية للمستخدم العادي
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';
// Session is now managed in db.php
require_once 'security.php';
ensure_session();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$chat_id = $_GET['chat_id'] ?? null;

if (!$chat_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'معرف المحادثة مطلوب'
    ]);
    exit;
}

try {
    // التحقق من أن المحادثة تخص المستخدم
    $checkChat = $pdo->prepare("SELECT id, admin_id FROM admin_chats WHERE id = ? AND user_id = ?");
    $checkChat->execute([$chat_id, $user_id]);
    $chat = $checkChat->fetch(PDO::FETCH_ASSOC);
    
    if (!$chat) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'المحادثة غير موجودة أو ليس لديك صلاحية للوصول إليها'
        ]);
        exit;
    }
    
    // جلب الرسائل
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT 
            acm.id,
            acm.message_text,
            acm.sender_type,
            acm.created_at,
            CASE 
                WHEN acm.sender_type = 'admin' THEN u.name
                ELSE user_sender.name
            END as sender_name,
            CASE 
                WHEN acm.sender_type = 'admin' THEN u.image
                ELSE user_sender.image
            END as sender_image
        FROM admin_chat_messages acm
        LEFT JOIN admin_chats ac ON acm.chat_id = ac.id
        LEFT JOIN users u ON ac.admin_id = u.id AND acm.sender_type = 'admin'
        LEFT JOIN users user_sender ON ac.user_id = user_sender.id AND acm.sender_type = 'user'
        WHERE acm.chat_id = ?
        ORDER BY acm.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$chat_id, $limit, $offset]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // عكس ترتيب الرسائل لتظهر من الأقدم للأحدث
    $messages = array_reverse($messages);
    
    // تنسيق البيانات
    $formatted_messages = [];
    foreach ($messages as $message) {
        $formatted_messages[] = [
            'id' => $message['id'],
            'message_text' => $message['message_text'],
            'sender_type' => $message['sender_type'],
            'is_admin' => $message['sender_type'] === 'admin',
            'sender_name' => $message['sender_name'] ?: ($message['sender_type'] === 'admin' ? 'الإدارة' : 'أنت'),
            'sender_image' => $message['sender_image'] ?: 'uploads/default-avatar.svg',
            'created_at' => $message['created_at']
        ];
    }
    
    // تحديث وقت آخر قراءة
    $update_read_stmt = $pdo->prepare("
        INSERT INTO admin_chat_reads (chat_id, user_id, last_read_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ");
    $update_read_stmt->execute([$chat_id, $user_id]);
    
    // تحديث وقت آخر قراءة في جدول admin_chats أيضاً
    $update_chat_stmt = $pdo->prepare("
        UPDATE admin_chats 
        SET last_read_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    $update_chat_stmt->execute([$chat_id, $user_id]);
    
    echo json_encode([
        'success' => true,
        'messages' => $formatted_messages,
        'chat_id' => $chat_id,
        'admin_id' => $chat['admin_id'],
        'page' => $page,
        'has_more' => count($messages) === $limit
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_user_admin_messages.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب الرسائل'
    ]);
}
?>