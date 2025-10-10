<?php
require_once 'db.php';
require_once 'security.php';
header('Content-Type: application/json; charset=utf-8');
ensure_session();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    // تحقق من وجود الأعمدة المطلوبة
    $colsStmt = $pdo->query("DESCRIBE users");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $fields = [];
    $params = [];

    if ($action === 'online') {
        if (in_array('is_online', $cols)) {
            $fields[] = '`is_online` = 1';
        }
    } elseif ($action === 'offline') {
        if (in_array('is_online', $cols)) {
            $fields[] = '`is_online` = 0';
        }
        if (in_array('typing_to', $cols)) {
            $fields[] = '`typing_to` = NULL';
        }
    } elseif ($action === 'typing' && isset($_POST['to'])) {
        if (in_array('typing_to', $cols)) {
            $fields[] = '`typing_to` = ?';
            $params[] = intval($_POST['to']);
        }
    } elseif ($action === 'stop_typing') {
        if (in_array('typing_to', $cols)) {
            $fields[] = '`typing_to` = NULL';
        }
    } else {
        echo json_encode(['error' => 'طلب غير صالح']);
        exit;
    }

    if (count($fields) > 0) {
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $params[] = $user_id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'لا يوجد حقول لتحديثها']);
    }
} catch (Exception $e) {
    error_log('update_status.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم']);
}
