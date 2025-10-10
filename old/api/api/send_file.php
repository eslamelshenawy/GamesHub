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
    $csrf = $_POST['csrf_token'] ?? '';

    $csrf_ok = validate_csrf_token($csrf);
    if (!$csrf_ok || $to <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'طلب غير صالح']);
        exit;
    }

    // prevent sending files to self
    if ($from === $to) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لا يمكنك مراسلة نفسك']);
        exit;
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'لم يتم رفع أي ملف']);
        exit;
    }

    $allowed_ext = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','txt','mp4','webm','mov'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $file = $_FILES['file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        echo json_encode(['success' => false, 'error' => 'نوع الملف غير مدعوم']);
        exit;
    }
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'error' => 'حجم الملف كبير جداً']);
        exit;
    }
    $new_name = uniqid('chatfile_') . '.' . $ext;
    $target = '../uploads/' . $new_name;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        echo json_encode(['success' => false, 'error' => 'فشل رفع الملف']);
        exit;
    }
    $file_url = 'uploads/' . $new_name;
    // حفظ رسالة الملف في قاعدة البيانات
    $msg = '[file]' . $file_url;
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
    $stmt->execute([$from, $to, $msg]);
    echo json_encode(['success' => true, 'file' => $file_url]);
    exit;
}
