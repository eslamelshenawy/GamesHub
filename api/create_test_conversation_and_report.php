<?php
// إنشاء محادثة تجريبية ورسائل وبلاغ عنها
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo->beginTransaction();

    // الخطوة 1: التحقق من وجود مستخدمين (سنستخدم المستخدمين الموجودين)
    $user1_id = 43; // mmm
    $user2_id = 44; // mmmm

    // التحقق من وجود المستخدمين
    $checkUser1 = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $checkUser1->execute([$user1_id]);
    $user1 = $checkUser1->fetch(PDO::FETCH_ASSOC);

    $checkUser2 = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $checkUser2->execute([$user2_id]);
    $user2 = $checkUser2->fetch(PDO::FETCH_ASSOC);

    if (!$user1 || !$user2) {
        throw new Exception("المستخدمين غير موجودين");
    }

    // الخطوة 2: البحث عن محادثة موجودة أو إنشاء محادثة جديدة
    $checkConversation = $pdo->prepare("
        SELECT id FROM conversations
        WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
    ");
    $checkConversation->execute([$user1_id, $user2_id, $user2_id, $user1_id]);
    $existingConv = $checkConversation->fetch(PDO::FETCH_ASSOC);

    if ($existingConv) {
        $conversation_id = $existingConv['id'];
        $is_new_conversation = false;
    } else {
        $insertConversation = $pdo->prepare("
            INSERT INTO conversations (user1_id, user2_id, last_message_at)
            VALUES (?, ?, NOW())
        ");
        $insertConversation->execute([$user1_id, $user2_id]);
        $conversation_id = $pdo->lastInsertId();
        $is_new_conversation = true;
    }

    // الخطوة 3: إضافة رسائل تجريبية للمحادثة
    $messages = [
        [
            'sender_id' => $user1_id,
            'receiver_id' => $user2_id,
            'text' => 'السلام عليكم، عايز أشتري حساب فيفا'
        ],
        [
            'sender_id' => $user2_id,
            'receiver_id' => $user1_id,
            'text' => 'وعليكم السلام، أيوه في حسابات متاحة'
        ],
        [
            'sender_id' => $user1_id,
            'receiver_id' => $user2_id,
            'text' => 'كام سعر الحساب؟'
        ],
        [
            'sender_id' => $user2_id,
            'receiver_id' => $user1_id,
            'text' => 'الحساب ب 500 جنيه بس'
        ],
        [
            'sender_id' => $user1_id,
            'receiver_id' => $user2_id,
            'text' => 'تمام، ابعتلي البيانات'
        ],
        [
            'sender_id' => $user2_id,
            'receiver_id' => $user1_id,
            'text' => 'حول الفلوس الأول وبعدين هبعتلك البيانات'
        ],
        [
            'sender_id' => $user1_id,
            'receiver_id' => $user2_id,
            'text' => 'لأ أنا مش هحول إلا لما أشوف البيانات'
        ],
        [
            'sender_id' => $user2_id,
            'receiver_id' => $user1_id,
            'text' => 'طيب معلش مفيش صفقة'
        ],
        [
            'sender_id' => $user1_id,
            'receiver_id' => $user2_id,
            'text' => 'أنت نصاب يا عم!'
        ],
        [
            'sender_id' => $user2_id,
            'receiver_id' => $user1_id,
            'text' => 'أنت اللي نصاب!'
        ]
    ];

    $insertMessage = $pdo->prepare("
        INSERT INTO messages (sender_id, receiver_id, message_text, created_at)
        VALUES (?, ?, ?, NOW())
    ");

    foreach ($messages as $message) {
        $insertMessage->execute([
            $message['sender_id'],
            $message['receiver_id'],
            $message['text']
        ]);
    }

    // الخطوة 4: إنشاء بلاغ مرتبط بالمحادثة
    $insertReport = $pdo->prepare("
        INSERT INTO reports (
            reporter_id,
            reported_user_id,
            conversation_id,
            reason,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, 'pending', NOW())
    ");

    $report_reason = 'هذا المستخدم محتال ويحاول النصب. طلب مني تحويل الأموال قبل إعطائي بيانات الحساب، وعندما رفضت قام بإهانتي. الرجاء التحقيق في الأمر واتخاذ الإجراءات اللازمة.';

    $insertReport->execute([
        $user1_id,      // المبلغ
        $user2_id,      // المبلغ عنه
        $conversation_id,
        $report_reason
    ]);

    $report_id = $pdo->lastInsertId();

    $pdo->commit();

    // جلب بيانات البلاغ المُنشأ
    $getReport = $pdo->prepare("
        SELECT
            r.*,
            reporter.name as reporter_name,
            reporter.email as reporter_email,
            reported.name as reported_user_name,
            reported.email as reported_user_email
        FROM reports r
        LEFT JOIN users reporter ON r.reporter_id = reporter.id
        LEFT JOIN users reported ON r.reported_user_id = reported.id
        WHERE r.id = ?
    ");
    $getReport->execute([$report_id]);
    $report = $getReport->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء المحادثة والبلاغ بنجاح',
        'data' => [
            'conversation_id' => $conversation_id,
            'is_new_conversation' => $is_new_conversation,
            'messages_count' => count($messages),
            'report_id' => $report_id,
            'report' => $report,
            'users' => [
                'user1' => $user1,
                'user2' => $user2
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
