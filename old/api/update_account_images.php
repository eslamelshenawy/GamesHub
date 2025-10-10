<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'طريقة الطلب غير مسموحة']);
    exit;
}
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
$csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}
if ($account_id <= 0 || !validate_csrf_token($csrf)) {
    http_response_code(400);
    echo json_encode(['error' => 'طلب غير صالح']);
    exit;
}
// تحقق ملكية الإعلان
$stmt = $pdo->prepare('SELECT user_id FROM accounts WHERE id = ?');
$stmt->execute([$account_id]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'الإعلان غير موجود']);
    exit;
}
if (intval($row['user_id']) !== $user_id) {
    http_response_code(403);
    echo json_encode(['error' => 'ليس لديك صلاحية لتعديل هذا الإعلان']);
    exit;
}
// حذف الصور المطلوبة
if (!empty($_POST['delete_images'])) {
    $delete_images = json_decode($_POST['delete_images'], true);
    if (is_array($delete_images)) {
        foreach ($delete_images as $img) {
            $stmt = $pdo->prepare('DELETE FROM account_images WHERE account_id = ? AND image_path = ?');
            $stmt->execute([$account_id, $img]);
            $file = '../' . $img;
            if (file_exists($file)) @unlink($file);
        }
    }
}
// إضافة صور جديدة
$added = [];
if (!empty($_FILES['new_images']['name'][0])) {
    $max_images = 30;
    // احسب عدد الصور الحالية
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM account_images WHERE account_id = ?');
    $stmt->execute([$account_id]);
    $current_count = (int)$stmt->fetchColumn();
    $new_count = count($_FILES['new_images']['name']);
    if ($current_count + $new_count > $max_images) {
        http_response_code(400);
        echo json_encode(['error' => 'الحد الأقصى للصور هو 30 صورة فقط.']);
        exit;
    }
    $upload_dir = '../uploads/';
    foreach ($_FILES['new_images']['tmp_name'] as $key => $tmp_name) {
        $file_name = uniqid('img_') . '_' . basename($_FILES['new_images']['name'][$key]);
        $target = $upload_dir . $file_name;
        if (move_uploaded_file($tmp_name, $target)) {
            $img_path = 'uploads/' . $file_name;
            $stmt_img = $pdo->prepare('INSERT INTO account_images (account_id, image_path, uploaded_at) VALUES (?, ?, NOW())');
            $stmt_img->execute([$account_id, $img_path]);
            $added[] = $img_path;
        }
    }
}
echo json_encode(['success' => true, 'added' => $added]);
exit;
