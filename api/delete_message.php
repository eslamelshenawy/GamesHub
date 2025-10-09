<?php
// حذف رسالة من الدردشة
header('Content-Type: application/json');
require_once 'security.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'invalid_method']);
    exit;
}

$user_id = get_current_user_id();
if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'unauthorized']);
    exit;
}

$msg_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
if ($msg_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_message_id']);
    exit;
}

// تحقق أن الرسالة تخص المستخدم الحالي
$stmt = $pdo->prepare('SELECT sender_id FROM messages WHERE id = ?');
$stmt->execute([$msg_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row || $row['sender_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'not_allowed']);
    exit;
}

// حذف الرسالة
$stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
$stmt->execute([$msg_id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'delete_failed']);
}
?>

