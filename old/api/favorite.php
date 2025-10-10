<?php
require_once 'db.php';
require_once 'security.php';
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

// فقط POST مسموح
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_id = isset($_POST['account_id']) ? intval($_POST['account_id']) : 0;
    $csrf       = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    // تحقق من CSRF وصلاحية account_id
    if (!validate_csrf_token($csrf) || $account_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'طلب غير صالح']);
        exit;
    }

    $user_id = $_SESSION['user_id'];

    // هل الحساب بالفعل في المفضلة؟
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND account_id = ?");
    $stmt->execute([$user_id, $account_id]);
    $favorite = $stmt->fetch();

    if ($favorite) {
        // موجود → احذف
        $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$favorite['id']]);
        echo json_encode([
            'success'   => true,
            'favorited' => false,
            'message'   => 'تمت الإزالة من المفضلة'
        ]);
    } else {
        // مش موجود → أضف
        $pdo->prepare("INSERT INTO favorites (user_id, account_id, created_at) VALUES (?, ?, NOW())")
            ->execute([$user_id, $account_id]);
        echo json_encode([
            'success'   => true,
            'favorited' => true,
            'message'   => 'تمت الإضافة إلى المفضلة'
        ]);
    }

    exit;
}

// أي طلب غير POST
http_response_code(405);
echo json_encode(['error' => 'طريقة الطلب غير مسموحة']);
exit;