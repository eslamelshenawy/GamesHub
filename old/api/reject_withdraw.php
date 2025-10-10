<?php
// api/reject_withdraw.php
require_once 'db.php';
require_once 'security.php';
ensure_session();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo json_encode(['success'=>false, 'error'=>'غير مصرح']);
    exit;
}
// قراءة البيانات من JSON
$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['withdraw_id']) ? intval($data['withdraw_id']) : 0;
if ($id <= 0) {
    echo json_encode(['success'=>false, 'error'=>'معرف الطلب غير صالح']);
    exit;
}
$stmt = $pdo->prepare('SELECT * FROM withdraw_requests WHERE id = ? AND status = "pending"');
$stmt->execute([$id]);
$request = $stmt->fetch();
if (!$request) {
    echo json_encode(['success'=>false, 'error'=>'الطلب غير موجود أو تم مراجعته']);
    exit;
}
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE withdraw_requests SET status = "rejected", processed_at = NOW() WHERE id = ?');
    $stmt->execute([$id]);
    // إعادة الرصيد للمستخدم
    $stmt = $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE user_id = ?');
    $stmt->execute([$request['amount'], $request['user_id']]);
    $pdo->commit();
    echo json_encode(['success'=>true, 'message'=>'تم رفض طلب السحب وإعادة الرصيد']);
} catch(Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false, 'error'=>'فشل التنفيذ: ' . $e->getMessage()]);
}
