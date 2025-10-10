<?php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false, 'error'=>'غير مصرح']);
    exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['topup_id']) ? intval($data['topup_id']) : 0;
if ($id <= 0) {
    echo json_encode(['success'=>false, 'error'=>'طلب غير صالح']);
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM wallet_topups WHERE id = ?");
$stmt->execute([$id]);
$topup = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$topup || $topup['status'] !== 'pending') {
    echo json_encode(['success'=>false, 'error'=>'الطلب غير موجود أو تم مراجعته']);
    exit;
}
$pdo->prepare("UPDATE wallet_topups SET status = 'rejected', reviewed_at = NOW() WHERE id = ?")->execute([$id]);
echo json_encode(['success'=>true, 'message'=>'تم رفض الطلب']);
