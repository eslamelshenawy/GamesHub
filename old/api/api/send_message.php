<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');


if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from = $_SESSION['user_id'];
    $to = isset($_POST['to']) ? intval($_POST['to']) : 0;
    $msg = isset($_POST['message']) ? trim($_POST['message']) : '';
    $csrf = $_POST['csrf_token'] ?? '';

    // Support alternative field name 'seller_id' when front-end provides it instead of 'to'
    if (($to <= 0 || empty($to)) && isset($_POST['seller_id'])) {
        $alt = intval($_POST['seller_id']);
        if ($alt > 0) {
            $to = $alt;
            error_log("[send_message] using seller_id fallback: to={$to}");
        }
    }

    // Debug logging to help diagnose empty/invalid responses during development
    error_log("[send_message] POST received: from={$from} to={$to} message=" . substr($msg,0,200));
    error_log("[send_message] csrf token present: " . (!empty($csrf) ? 'yes' : 'no'));

    $csrf_ok = validate_csrf_token($csrf);
    if (!$csrf_ok || $to <= 0 || empty($msg)) {
        error_log("[send_message] validation failed: csrf_ok=" . ($csrf_ok? '1':'0') . " to={$to} msg_len=" . strlen($msg));
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
        exit;
    }

    // prevent sending messages to self
    if ($from === $to) {
        error_log("[send_message] attempt to message self: user={$from}");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لا يمكنك مراسلة نفسك']);
        exit;
    }

    try {
        // تحقق أن المستخدم المستلم موجود
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$to]);
        if ($stmt->rowCount() === 0) {
            error_log("[send_message] recipient not found: to={$to}");
            echo json_encode(['success' => false, 'error' => 'المستخدم غير موجود']);
            exit;
        }

        // إدخال الرسالة
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read)
                               VALUES (?, ?, ?, NOW(), 0)");
        $stmt->execute([$from, $to, $msg]);

        echo json_encode(['success' => true, 'message' => $msg]);
    } catch (PDOException $e) {
            // Log detailed exception for debugging
            error_log('[send_message] PDOException: ' . $e->getMessage());
            if (method_exists($e, 'getTraceAsString')) {
                error_log('[send_message] Trace: ' . $e->getTraceAsString());
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    }
    exit;
}
