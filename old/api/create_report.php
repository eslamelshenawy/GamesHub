<?php
// API لإنشاء بلاغ جديد
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// التعامل مع طلبات OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once 'db.php';
session_start();

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'طريقة الطلب غير مسموحة'
    ]);
    exit;
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit;
}

// قراءة البيانات المرسلة
$input = json_decode(file_get_contents('php://input'), true);

// التحقق من صحة البيانات
if (!isset($input['reported_user_id']) || !isset($input['reason']) || !isset($input['conversation_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'بيانات غير مكتملة'
    ]);
    exit;
}

$reporter_id = $_SESSION['user_id'];
$reported_user_id = (int)$input['reported_user_id'];
$conversation_id = (int)$input['conversation_id'];
$reason = trim($input['reason']);

// التحقق من صحة البيانات
if (empty($reason)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'يجب كتابة سبب البلاغ'
    ]);
    exit;
}

if ($reporter_id === $reported_user_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'لا يمكن الإبلاغ عن نفسك'
    ]);
    exit;
}

try {
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // التحقق من وجود المستخدم المبلغ عنه
    $checkUser = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $checkUser->execute([$reported_user_id]);
    $reportedUser = $checkUser->fetch(PDO::FETCH_ASSOC);
    
    if (!$reportedUser) {
        throw new Exception('المستخدم المبلغ عنه غير موجود');
    }
    
    // التحقق من وجود المحادثة والتأكد من أن المبلغ جزء منها
    $checkConversation = $pdo->prepare("
        SELECT id, user1_id, user2_id 
        FROM conversations 
        WHERE id = ? AND (user1_id = ? OR user2_id = ?)
    ");
    $checkConversation->execute([$conversation_id, $reporter_id, $reporter_id]);
    $conversation = $checkConversation->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        throw new Exception('المحادثة غير موجودة أو ليس لديك صلاحية للإبلاغ عنها');
    }
    
    // تم إزالة التحقق من البلاغات المطابقة للسماح بإنشاء بلاغات متعددة
    
    // الحصول على معرف الإدارة (أول مستخدم إداري)
    $getAdmin = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $getAdmin->execute();
    $admin = $getAdmin->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        // إذا لم يوجد عمود role، نستخدم المستخدم رقم 38 كإداري افتراضي
        $admin = ['id' => 38];
    }
    
    $admin_id = $admin['id'];
    
    // البحث عن محادثة إدارية موجودة أو إنشاء واحدة جديدة
    $checkAdminConversation = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)) 
        AND conversation_type = 'admin_support'
    ");
    $checkAdminConversation->execute([$reporter_id, $admin_id, $admin_id, $reporter_id]);
    $existingAdminConversation = $checkAdminConversation->fetch(PDO::FETCH_ASSOC);
    
    if ($existingAdminConversation) {
        // استخدام المحادثة الإدارية الموجودة
        $admin_conversation_id = $existingAdminConversation['id'];
    } else {
        // إنشاء محادثة إدارية جديدة
        $createAdminConversation = $pdo->prepare("
            INSERT INTO conversations (user1_id, user2_id, conversation_type, last_message_at) 
            VALUES (?, ?, 'admin_support', NOW())
        ");
        $createAdminConversation->execute([$reporter_id, $admin_id]);
        $admin_conversation_id = $pdo->lastInsertId();
    }
    
    // إنشاء البلاغ
    $createReport = $pdo->prepare("
        INSERT INTO reports (reporter_id, reported_user_id, conversation_id, reason, admin_conversation_id, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $createReport->execute([$reporter_id, $reported_user_id, $conversation_id, $reason, $admin_conversation_id]);
    $report_id = $pdo->lastInsertId();
    
    // ربط المحادثة الإدارية بالبلاغ
    $updateAdminConversation = $pdo->prepare("
        UPDATE conversations SET related_report_id = ? WHERE id = ?
    ");
    $updateAdminConversation->execute([$report_id, $admin_conversation_id]);
    
    // إرسال رسالة ترحيبية في المحادثة الإدارية
    $welcomeMessage = "مرحباً، تم استلام بلاغك بنجاح.\n\n";
    $welcomeMessage .= "رقم البلاغ: #{$report_id}\n";
    $welcomeMessage .= "المبلغ عنه: {$reportedUser['name']}\n";
    $welcomeMessage .= "سبب البلاغ: {$reason}\n\n";
    $welcomeMessage .= "سيتم مراجعة بلاغك من قبل فريق الإدارة وسنتواصل معك قريباً.";
    
    $insertWelcomeMessage = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_text, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $insertWelcomeMessage->execute([$admin_id, $reporter_id, $welcomeMessage]);
    
    // تحديث وقت آخر رسالة في المحادثة الإدارية
    $updateLastMessage = $pdo->prepare("
        UPDATE conversations SET last_message_at = NOW() WHERE id = ?
    ");
    $updateLastMessage->execute([$admin_conversation_id]);
    
    // الحصول على معلومات المبلغ
    $getReporter = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $getReporter->execute([$reporter_id]);
    $reporter = $getReporter->fetch(PDO::FETCH_ASSOC);
    
    // تأكيد المعاملة
    $pdo->commit();
    
    // إرسال الاستجابة
    echo json_encode([
        'success' => true,
        'message' => 'تم إرسال البلاغ بنجاح',
        'data' => [
            'report_id' => $report_id,
            'admin_conversation_id' => $admin_conversation_id,
            'status' => 'pending',
            'reporter_name' => $reporter['name'],
            'reported_user_name' => $reportedUser['name']
        ]
    ]);
    
} catch (Exception $e) {
    // التراجع عن المعاملة في حالة الخطأ
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>