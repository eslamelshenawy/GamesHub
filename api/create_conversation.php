<?php
// create_conversation.php - إنشاء محادثة جديدة مع ربطها بحساب معين
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
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة']);
    exit;
}

$current_user = $_SESSION['user_id'];
$other_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

// التحقق من CSRF token
if (!validate_csrf_token($csrf_token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'رمز الأمان غير صالح']);
    exit;
}

// التحقق من صحة البيانات
if ($other_user_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'معرف المستخدم مطلوب']);
    exit;
}

if ($current_user == $other_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'لا يمكن إنشاء محادثة مع نفسك']);
    exit;
}

try {
    // التحقق من وجود المستخدم الآخر
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE id = ?');
    $stmt->execute([$other_user_id]);
    $other_user = $stmt->fetch();
    
    if (!$other_user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
        exit;
    }
    
    // التحقق من وجود الحساب إذا تم تمرير account_id
    $account = null;
    if ($account_id > 0) {
        $stmt = $pdo->prepare('SELECT id, game_name, user_id FROM accounts WHERE id = ?');
        $stmt->execute([$account_id]);
        $account = $stmt->fetch();
        
        if (!$account) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'الحساب غير موجود']);
            exit;
        }
    }
    
    // البحث عن محادثة موجودة بين المستخدمين
    $stmt = $pdo->prepare('
        SELECT id FROM conversations 
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
        LIMIT 1
    ');
    $stmt->execute([$current_user, $other_user_id, $other_user_id, $current_user]);
    $existing_conversation = $stmt->fetch();
    
    if ($existing_conversation) {
        // إذا كانت المحادثة موجودة، إرجاع معرفها
        echo json_encode([
            'success' => true,
            'conversation_id' => $existing_conversation['id'],
            'message' => 'المحادثة موجودة بالفعل',
            'account' => $account
        ]);
    } else {
        // إنشاء محادثة جديدة
        $stmt = $pdo->prepare('
            INSERT INTO conversations (user1_id, user2_id, last_message_at) 
            VALUES (?, ?, NOW())
        ');
        $stmt->execute([$current_user, $other_user_id]);
        $conversation_id = $pdo->lastInsertId();
        
        // إرسال رسالة ترحيبية إذا كان هناك حساب مرتبط
        if ($account && $account_id > 0) {
            $welcome_message = "مرحباً! أنا مهتم بحساب {$account['game_name']}";
            $stmt = $pdo->prepare('
                INSERT INTO messages (sender_id, receiver_id, message_text, created_at) 
                VALUES (?, ?, ?, NOW())
            ');
            $stmt->execute([$current_user, $other_user_id, $welcome_message]);
        }
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $conversation_id,
            'message' => 'تم إنشاء المحادثة بنجاح',
            'account' => $account
        ]);
    }
    
} catch (Exception $e) {
    error_log('[create_conversation] error=' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في إنشاء المحادثة']);
}
?>