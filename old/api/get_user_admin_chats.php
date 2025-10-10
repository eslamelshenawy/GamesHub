<?php
// API لجلب المحادثات الإدارية للمستخدم العادي
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

try {
    // جلب المحادثات الإدارية للمستخدم
    $stmt = $pdo->prepare("
        SELECT 
            ac.id as chat_id,
            ac.admin_id,
            ac.created_at,
            ac.last_message_at,
            u.name as admin_name,
            u.image as admin_image,
            (
                SELECT message_text 
                FROM admin_chat_messages acm 
                WHERE acm.chat_id = ac.id 
                ORDER BY acm.created_at DESC 
                LIMIT 1
            ) as last_message,
            (
                SELECT COUNT(*) 
                FROM admin_chat_messages acm 
                WHERE acm.chat_id = ac.id 
                AND acm.sender_type = 'admin'
                AND acm.created_at > COALESCE(
                    (SELECT last_read_at FROM admin_chat_reads WHERE chat_id = ac.id AND user_id = ?),
                    '1970-01-01 00:00:00'
                )
            ) as unread_count
        FROM admin_chats ac
        LEFT JOIN users u ON ac.admin_id = u.id
        WHERE ac.user_id = ?
        ORDER BY ac.last_message_at DESC, ac.created_at DESC
    ");
    
    $stmt->execute([$user_id, $user_id]);
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق البيانات
    $formatted_chats = [];
    foreach ($chats as $chat) {
        $formatted_chats[] = [
            'id' => $chat['chat_id'],
            'admin_id' => $chat['admin_id'],
            'admin_name' => $chat['admin_name'] ?: 'الإدارة',
            'admin_image' => $chat['admin_image'] ?: 'uploads/default-avatar.svg',
            'last_message' => $chat['last_message'] ?: 'لا توجد رسائل',
            'last_message_at' => $chat['last_message_at'],
            'created_at' => $chat['created_at'],
            'unread_count' => (int)$chat['unread_count'],
            'type' => 'admin_chat'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'chats' => $formatted_chats,
        'total_count' => count($formatted_chats)
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_user_admin_chats.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب المحادثات الإدارية'
    ]);
}
?>