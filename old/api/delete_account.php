<?php
require_once 'db.php';
require_once 'security.php';
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

// تأكد أن المستخدم يملك الإعلان
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
    echo json_encode(['error' => 'ليس لديك صلاحية لحذف هذا الإعلان']);
    exit;
}

// حذف الصور المرتبطة إن وُجدت
// جلب ملفات الصور المرتبطة لحذفها من القرص
$stmtImgs = $pdo->prepare('SELECT image_path FROM account_images WHERE account_id = ?');
$stmtImgs->execute([$account_id]);
$images = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);
$uploadsDir = realpath(__DIR__ . '/../uploads');
foreach ($images as $imgPath) {
    if (!$imgPath) continue;
    // بناء المسار المطلق المحتمل
    $candidate = realpath(__DIR__ . '/../' . ltrim($imgPath, '/\\')) ?: realpath($imgPath);
    if ($candidate && $uploadsDir && strpos($candidate, $uploadsDir) === 0) {
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    } else {
        // لا تحاول حذف ملفات خارج uploads، سجّل ذلك للمرجعة
        error_log("حاول حذف مسار صورة غير آمن أو غير موجود: $imgPath");
    }
}

// حذف سجلات الصور من قاعدة البيانات
$pdo->prepare('DELETE FROM account_images WHERE account_id = ?')->execute([$account_id]);
// حذف الإعلان
$pdo->prepare('DELETE FROM accounts WHERE id = ?')->execute([$account_id]);

echo json_encode(['success' => true, 'message' => 'تم حذف الإعلان']);
exit;