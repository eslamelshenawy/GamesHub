<?php
// API لإرسال رسالة من المستخدم العادي للإدارة
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مدعومة'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$chat_id = $input['chat_id'] ?? null;
$message = trim($input['message'] ?? '');

if (!$chat_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'معرف المحادثة مطلوب'
    ]);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'نص الرسالة مطلوب'
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
    
    // إدراج الرسالة
    $stmt = $pdo->prepare("
        INSERT INTO admin_chat_messages (chat_id, sender_type, message_text, created_at) 
        VALUES (?, 'user', ?, NOW())
    ");
    $stmt->execute([$chat_id, $message]);
    
    $message_id = $pdo->lastInsertId();
    
    // تحديث وقت آخر رسالة في المحادثة
    $updateChat = $pdo->prepare("UPDATE admin_chats SET last_message_at = NOW() WHERE id = ?");
    $updateChat->execute([$chat_id]);
    
    // جلب بيانات الرسالة المرسلة
    $getMessage = $pdo->prepare("
        SELECT 
            acm.id,
            acm.message_text,
            acm.sender_type,
            acm.created_at,
            u.name as sender_name,
            u.image as sender_image
        FROM admin_chat_messages acm
        LEFT JOIN admin_chats ac ON acm.chat_id = ac.id
        LEFT JOIN users u ON ac.user_id = u.id
        WHERE acm.id = ?
    ");
    $getMessage->execute([$message_id]);
    $messageData = $getMessage->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال الرسالة بنجاح',
        'data' => [
            'id' => $messageData['id'],
            'message_text' => $messageData['message_text'],
            'sender_type' => $messageData['sender_type'],
            'is_admin' => false,
            'sender_name' => $messageData['sender_name'] ?: 'أنت',
            'sender_image' => $messageData['sender_image'] ?: 'uploads/default-avatar.svg',
            'created_at' => $messageData['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Error in send_user_admin_message.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ أثناء إرسال الرسالة'
    ]);
}
?>