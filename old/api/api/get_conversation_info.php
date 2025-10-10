<?php
require_once 'db.php';
require_once 'security.php';
header('Content-Type: application/json; charset=utf-8');
ensure_session();

// التحقق من تسجيل الدخول
require_login_or_die();

$user_id = $_SESSION['user_id'];
$conversation_id = $_GET['conversation_id'] ?? null;
$other_user_id = $_GET['user_id'] ?? null;

// إذا لم يكن هناك conversation_id ولكن هناك user_id، ابحث عن المحادثة
if (!$conversation_id && $other_user_id) {
    $stmt = $pdo->prepare("
        SELECT id 
        FROM conversations 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
        ORDER BY last_message_at DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
    $conversation = $stmt->fetch();
    
    if ($conversation) {
        $conversation_id = $conversation['id'];
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'لا توجد محادثة بين هذين المستخدمين']);
        exit;
    }
}

if (!$conversation_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'معرف المحادثة أو معرف المستخدم مطلوب']);
    exit;
}

try {
    // التحقق من أن المستخدم جزء من هذه المحادثة
    $stmt = $pdo->prepare("
        SELECT user1_id, user2_id 
        FROM conversations 
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $stmt->execute([$conversation_id, $user_id, $user_id]);
    $conversation = $stmt->fetch();
    
    if (!$conversation) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'غير مسموح بالوصول لهذه المحادثة']);
        exit;
    }
    
    // تحديد المستخدم الآخر
    $other_user_id = ($conversation['user1_id'] == $user_id) ? $conversation['user2_id'] : $conversation['user1_id'];
    
    // جلب معلومات المستخدم الآخر
    $stmt = $pdo->prepare("
        SELECT id, name, phone, image,
               CASE 
                   WHEN image IS NOT NULL AND image != '' THEN image
                   ELSE 'uploads/default-avatar.svg'
               END as avatar
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$other_user_id]);
    $other_user = $stmt->fetch();
    
    // إضافة full_name باستخدام name
    $other_user['full_name'] = $other_user['name'];
    
    // جلب معلومات الحساب المرتبط بالمحادثة (إن وجد)
    $account = null;
    $stmt = $pdo->prepare("
        SELECT a.id, a.game_name as title, a.price, ai.image_path as image_url, a.description, a.user_id as account_owner_id
        FROM deals d
        JOIN accounts a ON d.account_id = a.id
        LEFT JOIN account_images ai ON a.id = ai.account_id
        WHERE d.conversation_id = ?
        ORDER BY d.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$conversation_id]);
    $account = $stmt->fetch();
    
    // إذا لم يكن هناك صفقة، جرب البحث في الرسائل الأولى
    if (!$account) {
        $stmt = $pdo->prepare("
            SELECT a.id, a.game_name as title, a.price, ai.image_path as image_url, a.description, a.user_id as account_owner_id
            FROM messages m
            JOIN accounts a ON m.message_text LIKE CONCAT('%account_id:', a.id, '%')
            LEFT JOIN account_images ai ON a.id = ai.account_id
            WHERE m.sender_id = ? AND m.receiver_id = ?
            ORDER BY m.created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$user_id, $other_user_id]);
        $account = $stmt->fetch();
    }
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversation_id,
        'other_user' => $other_user,
        'account' => $account
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_conversation_info.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
