<?php
// get_conversations.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$current_user = $_SESSION['user_id'];

// جلب قائمة المستخدمين الذين يوجد بينهم وبين المستخدم الحالي رسائل

$stmt = $pdo->prepare('
    SELECT u.id,
           u.name AS full_name,
           u.name AS name,
           COALESCE(NULLIF(u.image, ""), "uploads/default-avatar.svg") AS avatar,
           (SELECT message_text FROM messages WHERE (sender_id=u.id AND receiver_id=:me1) OR (sender_id=:me2 AND receiver_id=u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT created_at FROM messages WHERE (sender_id=u.id AND receiver_id=:me3) OR (sender_id=:me4 AND receiver_id=u.id) ORDER BY created_at DESC LIMIT 1) as last_time,
           (SELECT COUNT(*) FROM messages WHERE sender_id=u.id AND receiver_id=:me8 AND is_read=0) as unread_count
    FROM users u
    WHERE u.id != :me5 AND (
        EXISTS (SELECT 1 FROM messages WHERE sender_id=u.id AND receiver_id=:me6) OR
        EXISTS (SELECT 1 FROM messages WHERE sender_id=:me7 AND receiver_id=u.id)
    )
    ORDER BY last_time DESC
');

try {
    $stmt->execute([
        ':me1' => $current_user,
        ':me2' => $current_user,
        ':me3' => $current_user,
        ':me4' => $current_user,
        ':me5' => $current_user,
        ':me6' => $current_user,
        ':me7' => $current_user,
        ':me8' => $current_user
    ]);

    $users = $stmt->fetchAll();
    error_log('[get_conversations] current_user=' . $current_user . ' conversations_count=' . count($users));

    echo json_encode(['success' => true, 'conversations' => $users]);
} catch (Exception $e) {
    error_log('[get_conversations] error=' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في جلب المحادثات']);
}
