<?php
header('Content-Type: application/json');
require_once 'db.php';
require_once 'security.php';
ensure_session();

// هذا الملف للاختبار فقط - يضيف محادثة تجريبية

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    // جلب أول مستخدم آخر من قاعدة البيانات
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id != ? LIMIT 1");
    $stmt->execute([$current_user_id]);
    $other_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$other_user) {
        echo json_encode(['success' => false, 'error' => 'لا يوجد مستخدمين آخرين في قاعدة البيانات']);
        exit;
    }

    // إضافة رسالة تجريبية لإنشاء المحادثة
    $test_message = "مرحباً! هذه رسالة تجريبية 👋";
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->execute([$current_user_id, $other_user['id'], $test_message]);

    echo json_encode([
        'success' => true,
        'message' => 'تم إنشاء محادثة تجريبية',
        'other_user_id' => $other_user['id'],
        'other_user_name' => $other_user['name'],
        'conversation_link' => "messages.php?user_id=" . $other_user['id']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
