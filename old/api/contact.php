<?php
// api/contact.php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'طريقة الطلب غير مدعومة']);
    exit;
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 30) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال اسم صحيح (2-30 حرف).']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'يرجى إدخال بريد إلكتروني صحيح.']);
    exit;
}
if ($message === '' || mb_strlen($message) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'يرجى كتابة رسالة لا تقل عن 10 أحرف.']);
    exit;
}

try {
    $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, message, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$name, $email, $message]);
    echo json_encode(['success' => true]);
    exit;
} catch (PDOException $e) {
    error_log('[contact] PDOException: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'خطأ في الخادم']);
    exit;
}
