<?php
// API للأدمن لجلب جميع المحادثات الإدارية
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';
require_once 'security.php';
ensure_session();

// التحقق من أن المستخدم أدمن
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit;
}

try {
    // التحقق من صلاحيات الأدمن
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['is_admin']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'غير مصرح لك بالوصول'
        ]);
        exit;
    }

    // جلب جميع المحادثات الإدارية
    $stmt = $pdo->prepare("
        SELECT
            ac.id as chat_id,
            ac.user_id,
            ac.admin_id,
            ac.created_at,
            ac.last_message_at,
            u.name as user_name,
            u.image as user_image,
            u.email as user_email,
            admin.name as admin_name,
            (
                SELECT message_text
                FROM admin_chat_messages acm
                WHERE acm.chat_id = ac.id
                ORDER BY acm.created_at DESC
                LIMIT 1
            ) as last_message,
            (
                SELECT sender_type
                FROM admin_chat_messages acm
                WHERE acm.chat_id = ac.id
                ORDER BY acm.created_at DESC
                LIMIT 1
            ) as last_message_sender,
            (
                SELECT COUNT(*)
                FROM admin_chat_messages acm
                WHERE acm.chat_id = ac.id
                AND acm.sender_type = 'user'
                AND acm.created_at > COALESCE(ac.admin_last_read_at, '1970-01-01 00:00:00')
            ) as unread_count
        FROM admin_chats ac
        LEFT JOIN users u ON ac.user_id = u.id
        LEFT JOIN users admin ON ac.admin_id = admin.id
        ORDER BY ac.last_message_at DESC, ac.created_at DESC
    ");

    $stmt->execute();
    $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // تنسيق البيانات
    $formatted_chats = [];
    foreach ($chats as $chat) {
        $formatted_chats[] = [
            'id' => $chat['chat_id'],
            'user_id' => $chat['user_id'],
            'admin_id' => $chat['admin_id'],
            'user_name' => $chat['user_name'] ?: 'مستخدم محذوف',
            'user_image' => $chat['user_image'] ?: 'uploads/default-avatar.svg',
            'user_email' => $chat['user_email'],
            'admin_name' => $chat['admin_name'] ?: 'الإدارة',
            'last_message' => $chat['last_message'] ?: 'لا توجد رسائل',
            'last_message_sender' => $chat['last_message_sender'],
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
    error_log('Error in get_admin_chats.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء جلب المحادثات الإدارية'
    ]);
}
?>
