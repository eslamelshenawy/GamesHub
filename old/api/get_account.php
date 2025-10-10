<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'طلب غير صالح']);
    exit;
}
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$stmt = $pdo->prepare('SELECT a.*, GROUP_CONCAT(ai.image_path) as images, (SELECT 1 FROM favorites f WHERE f.user_id = ? AND f.account_id = a.id LIMIT 1) as is_favorite FROM accounts a LEFT JOIN account_images ai ON a.id = ai.account_id WHERE a.id = ? GROUP BY a.id');
$stmt->execute([$user_id, $id]);
$acc = $stmt->fetch();
if ($acc) {
    $acc['images'] = $acc['images'] ? explode(',', $acc['images']) : [];
    $acc['is_favorite'] = $acc['is_favorite'] ? true : false;
    echo json_encode($acc);
} else {
    echo json_encode(['error' => 'الحساب غير موجود']);
}
exit;