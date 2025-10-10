<?php
// API لإدارة البلاغات والمحادثات الإدارية
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'db.php';
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'يجب تسجيل الدخول أولاً'
    ]);
    exit;
}

// التحقق من صلاحيات الإدارة
$user_id = $_SESSION['user_id'];
$checkAdmin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$checkAdmin->execute([$user_id]);
$user = $checkAdmin->fetch(PDO::FETCH_ASSOC);

// إذا لم يوجد عمود role، نتحقق من المعرف 38 كإداري افتراضي
if (!$user || ($user['role'] !== 'admin' && $user_id != 38)) {
    if ($user_id != 38) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'ليس لديك صلاحية للوصول لهذه الصفحة'
        ]);
        exit;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // قراءة البيانات المرسلة
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'update_status':
                updateReportStatus($pdo, $input, $user_id);
                break;
                
            case 'add_notes':
                addAdminNotes($pdo, $input, $user_id);
                break;
                
            case 'get_conversation_messages':
                getConversationMessages($pdo, $input);
                break;
                
            case 'get_or_create_admin_chat':
                getOrCreateAdminChat($pdo, $input, $user_id);
                break;
                
            case 'send_admin_message':
                sendAdminMessage($pdo, $input, $user_id);
                break;
                
            case 'get_conversation_messages':
                getConversationMessages($pdo, $input);
                break;
                
            default:
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'إجراء غير صحيح'
                ]);
        }
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'طريقة الطلب غير مسموحة'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'حدث خطأ: ' . $e->getMessage()
    ]);
}

// دالة تحديث حالة البلاغ
function updateReportStatus($pdo, $input, $admin_id) {
    if (!isset($input['report_id']) || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'بيانات غير مكتملة'
        ]);
        return;
    }
    
    $report_id = (int)$input['report_id'];
    $status = $input['status'];
    $admin_notes = $input['admin_notes'] ?? '';
    
    // التحقق من صحة الحالة
    $validStatuses = ['pending', 'under_review', 'resolved', 'dismissed'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'حالة غير صحيحة'
        ]);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // تحديث البلاغ
        $updateReport = $pdo->prepare("
            UPDATE reports 
            SET status = ?, admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
            WHERE id = ?
        ");
        $updateReport->execute([$status, $admin_notes, $admin_id, $report_id]);
        
        if ($updateReport->rowCount() === 0) {
            throw new Exception('البلاغ غير موجود');
        }
        
        // الحصول على معلومات البلاغ
        $getReport = $pdo->prepare("
            SELECT r.*, reporter.name as reporter_name, reported.name as reported_user_name
            FROM reports r
            LEFT JOIN users reporter ON r.reporter_id = reporter.id
            LEFT JOIN users reported ON r.reported_user_id = reported.id
            WHERE r.id = ?
        ");
        $getReport->execute([$report_id]);
        $report = $getReport->fetch(PDO::FETCH_ASSOC);
        
        // إرسال رسالة تحديث للمبلغ في المحادثة الإدارية
        $statusMessages = [
            'under_review' => 'تم بدء مراجعة بلاغك من قبل فريق الإدارة.',
            'resolved' => 'تم حل بلاغك بنجاح. شكراً لك على التبليغ.',
            'dismissed' => 'تم رفض بلاغك بعد المراجعة.'
        ];
        
        if (isset($statusMessages[$status])) {
            $message = $statusMessages[$status];
            if (!empty($admin_notes)) {
                $message .= "\n\nملاحظات الإدارة: " . $admin_notes;
            }
            
            $insertMessage = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, message_text, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $insertMessage->execute([$admin_id, $report['reporter_id'], $message]);
            
            // تحديث وقت آخر رسالة في المحادثة الإدارية
            $updateConversation = $pdo->prepare("
                UPDATE conversations SET last_message_at = NOW() WHERE id = ?
            ");
            $updateConversation->execute([$report['admin_conversation_id']]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم تحديث حالة البلاغ بنجاح',
            'data' => [
                'report_id' => $report_id,
                'status' => $status,
                'admin_notes' => $admin_notes
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

// دالة إضافة ملاحظات إدارية
function addAdminNotes($pdo, $input, $admin_id) {
    if (!isset($input['report_id']) || !isset($input['notes'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'بيانات غير مكتملة'
        ]);
        return;
    }
    
    $report_id = (int)$input['report_id'];
    $notes = trim($input['notes']);
    
    if (empty($notes)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'الملاحظات لا يمكن أن تكون فارغة'
        ]);
        return;
    }
    
    // تحديث الملاحظات
    $updateNotes = $pdo->prepare("
        UPDATE reports 
        SET admin_notes = ?, reviewed_by = ?, reviewed_at = NOW() 
        WHERE id = ?
    ");
    $updateNotes->execute([$notes, $admin_id, $report_id]);
    
    if ($updateNotes->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'البلاغ غير موجود'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'تم حفظ الملاحظات بنجاح'
    ]);
}

// دالة جلب رسائل المحادثة المبلغ عنها
function getConversationMessages($pdo, $input) {
    if (!isset($input['conversation_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'معرف المحادثة مطلوب'
        ]);
        return;
    }
    
    $conversation_id = (int)$input['conversation_id'];
    $page = isset($input['page']) ? max(1, (int)$input['page']) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    // جلب معلومات المحادثة
    $getConversation = $pdo->prepare("
        SELECT c.*, u1.name as user1_name, u2.name as user2_name
        FROM conversations c
        LEFT JOIN users u1 ON c.user1_id = u1.id
        LEFT JOIN users u2 ON c.user2_id = u2.id
        WHERE c.id = ?
    ");
    $getConversation->execute([$conversation_id]);
    $conversation = $getConversation->fetch(PDO::FETCH_ASSOC);
    
    if (!$conversation) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'المحادثة غير موجودة'
        ]);
        return;
    }
    
    // جلب الرسائل
    $getMessages = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.image as sender_image
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ");
    $getMessages->execute([
        $conversation['user1_id'], $conversation['user2_id'],
        $conversation['user2_id'], $conversation['user1_id']
    ]);
    $messages = $getMessages->fetchAll(PDO::FETCH_ASSOC);
    
    // تنسيق الرسائل
    $formattedMessages = [];
    foreach (array_reverse($messages) as $message) {
        $formattedMessages[] = [
            'id' => (int)$message['id'],
            'sender_id' => (int)$message['sender_id'],
            'sender_name' => $message['sender_name'],
            'sender_image' => $message['sender_image'],
            'receiver_id' => (int)$message['receiver_id'],
            'message_text' => $message['message_text'],
            'created_at' => $message['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'conversation' => [
                'id' => (int)$conversation['id'],
                'user1' => [
                    'id' => (int)$conversation['user1_id'],
                    'name' => $conversation['user1_name']
                ],
                'user2' => [
                    'id' => (int)$conversation['user2_id'],
                    'name' => $conversation['user2_name']
                ]
            ],
            'messages' => $formattedMessages
        ]
    ]);
}

// دالة للحصول على أو إنشاء محادثة إدارية
function getOrCreateAdminChat($pdo, $input, $admin_id) {
    try {
        // التحقق من صحة البيانات
        if (!isset($input['user_id']) || empty($input['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'معرف المستخدم مطلوب']);
            return;
        }

        $user_id = intval($input['user_id']);
        $user_type = $input['user_type'] ?? 'user';

        // التحقق من وجود المستخدم
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
            return;
        }

        // البحث عن محادثة إدارية موجودة
        $stmt = $pdo->prepare("
            SELECT id FROM admin_chats 
            WHERE user_id = ? AND admin_id = ?
        ");
        $stmt->execute([$user_id, $admin_id]);
        $chat = $stmt->fetch(PDO::FETCH_ASSOC);

        $chat_id = null;
        if (!$chat) {
            // إنشاء محادثة إدارية جديدة
            $stmt = $pdo->prepare("
                INSERT INTO admin_chats (user_id, admin_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user_id, $admin_id]);
            $chat_id = $pdo->lastInsertId();
        } else {
            $chat_id = $chat['id'];
        }

        // جلب الرسائل
        $stmt = $pdo->prepare("
            SELECT 
                acm.*,
                CASE 
                    WHEN acm.sender_type = 'admin' THEN 1
                    ELSE 0
                END as is_admin
            FROM admin_chat_messages acm
            WHERE acm.chat_id = ?
            ORDER BY acm.created_at ASC
        ");
        $stmt->execute([$chat_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'chat_id' => $chat_id,
                'user_id' => $user_id,
                'user_name' => $user['name'],
                'messages' => $messages
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ أثناء جلب المحادثة الإدارية: ' . $e->getMessage()
        ]);
    }
}

// دالة لإرسال رسالة إدارية
function sendAdminMessage($pdo, $input, $admin_id) {
    try {
        // التحقق من صحة البيانات
        if (!isset($input['user_id']) || empty($input['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'معرف المستخدم مطلوب']);
            return;
        }

        if (!isset($input['message']) || empty(trim($input['message']))) {
            echo json_encode(['success' => false, 'message' => 'نص الرسالة مطلوب']);
            return;
        }

        $user_id = intval($input['user_id']);
        $message = trim($input['message']);

        // البحث عن المحادثة الإدارية
        $stmt = $pdo->prepare("
            SELECT id FROM admin_chats 
            WHERE user_id = ? AND admin_id = ?
        ");
        $stmt->execute([$user_id, $admin_id]);
        $chat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$chat) {
            // إنشاء محادثة جديدة إذا لم تكن موجودة
            $stmt = $pdo->prepare("
                INSERT INTO admin_chats (user_id, admin_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$user_id, $admin_id]);
            $chat_id = $pdo->lastInsertId();
        } else {
            $chat_id = $chat['id'];
        }

        // إدراج الرسالة
        $stmt = $pdo->prepare("
            INSERT INTO admin_chat_messages (chat_id, sender_type, message_text, created_at) 
            VALUES (?, 'admin', ?, NOW())
        ");
        $stmt->execute([$chat_id, $message]);

        // تحديث وقت آخر رسالة في المحادثة
        $stmt = $pdo->prepare("
            UPDATE admin_chats 
            SET last_message_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$chat_id]);

        echo json_encode([
            'success' => true,
            'message' => 'تم إرسال الرسالة بنجاح'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'حدث خطأ أثناء إرسال الرسالة: ' . $e->getMessage()
        ]);
    }
}
?>