<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مسموح بالوصول']);
    exit;
}

// التحقق من وجود معرفات المستخدمين
if (!isset($_GET['buyer_id']) || !isset($_GET['seller_id']) || 
    empty($_GET['buyer_id']) || empty($_GET['seller_id'])) {
    echo json_encode(['success' => false, 'error' => 'معرف المشتري والبائع مطلوبان']);
    exit;
}

$buyer_id = intval($_GET['buyer_id']);
$seller_id = intval($_GET['seller_id']);
$current_user_id = $_SESSION['user_id'];



try {
    // البحث عن المحادثة بين المستخدمين
    $stmt = $pdo->prepare("
        SELECT id as conversation_id, user1_id, user2_id, last_message_at
        FROM conversations 
        WHERE (user1_id = ? AND user2_id = ?) 
           OR (user1_id = ? AND user2_id = ?)
        ORDER BY last_message_at DESC
        LIMIT 1
    ");
    
    $stmt->execute([$buyer_id, $seller_id, $seller_id, $buyer_id]);
    $conversation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($conversation) {
        echo json_encode([
            'success' => true,
            'conversation_id' => $conversation['conversation_id'],
            'user1_id' => $conversation['user1_id'],
            'user2_id' => $conversation['user2_id'],
            'last_message_at' => $conversation['last_message_at']
        ]);
    } else {
        // إذا لم توجد محادثة، يمكن إنشاء واحدة جديدة
        $stmt = $pdo->prepare("
            INSERT INTO conversations (user1_id, user2_id, last_message_at) 
            VALUES (?, ?, NOW())
        ");
        
        $stmt->execute([$buyer_id, $seller_id]);
        $new_conversation_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $new_conversation_id,
            'user1_id' => $buyer_id,
            'user2_id' => $seller_id,
            'created' => true,
            'message' => 'تم إنشاء محادثة جديدة'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get_conversation_by_users.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في الخادم'
    ]);
}
?>